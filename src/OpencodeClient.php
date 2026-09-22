<?php

namespace Stezkoy\FlarumAIOpenReply;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;

/**
 * Talks to a headless `opencode serve` HTTP server (opencode 2.x JSON API
 * under /api/*).
 *
 * Each discussion gets its own session, created with `{ title?, agent?,
 * model? }`. The agent (an id from GET /api/agent) and the model
 * (`{ id, providerID }`) come from the extension settings; before every
 * prompt they are synced to the session, so the latest settings always apply.
 *
 * A reply is produced asynchronously: the prompt is queued with
 * POST /api/session/:id/prompt, the server is asked to block until the
 * session is idle again, and the latest assistant message is read back.
 *
 * @see https://opencode.ai/v2/docs/server/
 */
class OpencodeClient
{
    protected ?Client $client = null;
    protected string $url = '';
    protected ?array $agents = null;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected LoggerInterface $logger
    ) {
        $url = (string)$this->settings->get('stezkoy-ai-openreply.opencode_url', 'http://localhost:49374');

        if (empty($url)) {
            $this->logger->error('[AI Open-Reply] opencode server URL is not configured.');
            return;
        }

        $options = [
            RequestOptions::TIMEOUT => 600,
            RequestOptions::CONNECT_TIMEOUT => 5,
            RequestOptions::HTTP_ERRORS => false,
        ];

        $password = $this->settings->get('stezkoy-ai-openreply.opencode_password');
        $username = $this->settings->get('stezkoy-ai-openreply.opencode_username', 'opencode') ?: 'opencode';

        if (!empty($password)) {
            $options[RequestOptions::AUTH] = [$username, $password];
        }

        $this->url = rtrim($url, '/');
        $this->client = new Client($options);
    }

    public function createSession(string $title): ?string
    {
        if ($this->client === null)
            return null;

        $body = ['title' => $title];

        $agent = $this->resolveAgent((string)$this->settings->get('stezkoy-ai-openreply.opencode_agent', ''));

        if ($agent !== '')
            $body['agent'] = $agent;

        $model = $this->parseModel($this->configuredModel());

        if ($model !== null)
            $body['model'] = $model;

        $payload = $this->requestJson('POST', '/api/session', $body);

        $sessionId = $payload['data']['id'] ?? null;

        if ($sessionId !== null && (string)$this->settings->get('stezkoy-ai-openreply.opencode_system_prompt', '') !== '')
            $this->syncPersona($sessionId);

        return $sessionId;
    }

    public function health(): bool
    {
        if ($this->client === null)
            return false;

        return $this->requestJson('GET', '/api/info', []) !== null;
    }

    /**
     * Returns the model the opencode server uses by default, as
     * `providerID/modelID`, or null when the server is unreachable.
     */
    public function serverDefaultModel(): ?string
    {
        if ($this->client === null)
            return null;

        $payload = $this->requestJson('GET', '/api/model/default', []);
        $model = $payload['data'] ?? null;

        if (!is_array($model))
            return null;

        $provider = (string)($model['providerID'] ?? '');
        $id = (string)($model['id'] ?? '');

        if ($provider === '' || $id === '')
            return null;

        return $provider.'/'.$id;
    }

    public function configuredModel(): string
    {
        return (string)$this->settings->get('stezkoy-ai-openreply.model', '');
    }

    /**
     * Returns a flat list of free models known to the opencode server, or
     * null when the server is unreachable.
     *
     * The list is derived from `GET /api/model` plus `GET /api/model/default`.
     * Only models advertised with no cost on any tier are included. Each
     * entry is:
     *
     *     ['id' => 'provider/model', 'name' => ..., 'status' => ..., 'isDefault' => bool]
     *
     * "isDefault" marks the server's default model (from /api/model/default).
     *
     * @return array{models?: array<int, array{id: string, name: string, status: string, isDefault: bool}>, reachable?: bool, default?: ?string}|null
     */
    public function models(): ?array
    {
        if ($this->client === null)
            return null;

        $payload = $this->requestJson('GET', '/api/model', []);

        if ($payload === null || !is_array($payload['data'] ?? null))
            return null;

        $default = $this->serverDefaultModel() ?? '';

        $models = [];

        foreach ($payload['data'] as $model) {
            if (!is_array($model) || !$this->isFreeModel($model))
                continue;

            $providerId = (string)($model['providerID'] ?? '');
            $modelId = (string)($model['id'] ?? '');

            if ($providerId === '' || $modelId === '')
                continue;

            $id = $providerId.'/'.$modelId;

            $models[] = [
                'id' => $id,
                'name' => (string)($model['name'] ?? $modelId),
                'status' => (string)($model['status'] ?? 'active'),
                'isDefault' => $id === $default,
            ];
        }

        // Deterministic order: the server default first, then alphabetical.
        usort($models, function (array $a, array $b): int {
            if ($a['isDefault'] !== $b['isDefault'])
                return $a['isDefault'] ? -1 : 1;

            return strcmp($a['id'], $b['id']);
        });

        return [
            'models' => $models,
            'reachable' => true,
            'default' => $default !== '' ? $default : null,
        ];
    }

    /**
     * A model is treated as free when it has no cost tiers, or every tier is
     * free on both input and output (opencode 2.x reports cost as a list).
     */
    private function isFreeModel(array $model): bool
    {
        $cost = $model['cost'] ?? null;

        if (!is_array($cost))
            return false;

        if ($cost === [])
            return true;

        foreach ($cost as $tier) {
            if (!is_array($tier))
                return false;

            $input = $tier['input'] ?? null;
            $output = $tier['output'] ?? null;

            if (!is_numeric($input) || !is_numeric($output))
                return false;

            if ((float)$input !== 0.0 || (float)$output !== 0.0)
                return false;
        }

        return true;
    }

    /**
     * Returns the total number of sessions currently on the opencode server
     * (all of them, including ones not created by this extension), or null
     * when the server is unreachable.
     */
    public function sessionCount(): ?int
    {
        if ($this->client === null)
            return null;

        $payload = $this->requestJson('GET', '/api/session', []);

        return is_array($payload['data'] ?? null) ? count($payload['data']) : null;
    }

    public function deleteSession(string $sessionId): bool
    {
        if ($this->client === null)
            return false;

        // A 404 just means the session is already gone (e.g. it was closed by a
        // TTL/limit or externally), which is normal and not worth logging as an error.
        // The endpoint returns 204 with an empty body on success, which requestJson()
        // tolerates when $soft is enabled.
        $this->requestJson('DELETE', '/api/session/'.rawurlencode($sessionId), [], true);

        return true;
    }

    /**
     * Bounded bulk variant of deleteSession() for the admin "close all
     * sessions" action, which runs inside a single web request: each DELETE
     * gets a short timeout, there are NO retries, and the loop stops at the
     * first failed call or when the time budget runs out — so a wedged
     * server cannot stall the admin request. A 404 counts as deleted: the
     * session is already gone.
     *
     * @return array{deleted: string[], stoppedEarly: bool}
     */
    public function deleteSessions(array $sessionIds, float $timeBudgetSeconds = 20.0, int $perCallTimeout = 15): array
    {
        if ($this->client === null)
            return ['deleted' => [], 'stoppedEarly' => true];

        $deleted = [];
        $deadline = microtime(true) + $timeBudgetSeconds;

        foreach ($sessionIds as $sessionId)
        {
            if (!is_string($sessionId) || $sessionId === '')
                continue;

            if (microtime(true) >= $deadline)
                return ['deleted' => $deleted, 'stoppedEarly' => true];

            try {
                $response = $this->client->request('DELETE', $this->url.'/api/session/'.rawurlencode($sessionId), [
                    RequestOptions::TIMEOUT => $perCallTimeout,
                ]);

                $status = $response->getStatusCode();

                // The session is already gone — as good as deleted.
                if ($status === 404)
                {
                    $deleted[] = $sessionId;
                    continue;
                }

                if ($status >= 400)
                    return ['deleted' => $deleted, 'stoppedEarly' => true];

                $deleted[] = $sessionId;
            } catch (\Throwable $e) {
                // Transport failure (connection refused, timeout, ...):
                // deleting the rest is pointless until the server is back.
                return ['deleted' => $deleted, 'stoppedEarly' => true];
            }
        }

        return ['deleted' => $deleted, 'stoppedEarly' => false];
    }

    /**
     * Returns the agents currently known to the opencode server (GET /api/agent),
     * or null when the server is unreachable. Each entry has at least an "id".
     */
    public function agents(): ?array
    {
        if ($this->client === null)
            return null;

        if ($this->agents === null) {
            $payload = $this->requestJson('GET', '/api/agent', []);
            $this->agents = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        }

        return $this->agents;
    }

    public function reply(string $sessionId, string $text): ?string
    {
        if ($this->client === null)
            return null;

        $escaped = rawurlencode($sessionId);

        $this->syncSessionConfig($sessionId);

        $prompt = $this->requestJson('POST', '/api/session/'.$escaped.'/prompt', ['text' => $text]);

        if ($prompt === null)
            return null;

        $this->waitForIdle($sessionId);

        $messages = $this->requestJson('GET', '/api/session/'.$escaped.'/message', []);

        return $messages === null ? null : $this->extractText($messages);
    }

    /**
     * Keeps the session's agent and model in line with the current settings
     * (opencode 2.x applies them per session, not per message, so they are
     * bumped here when an admin changes them between replies).
     */
    private function syncSessionConfig(string $sessionId): void
    {
        $info = $this->requestJson('GET', '/api/session/'.rawurlencode($sessionId), []);

        if ($info === null || !is_array($info['data'] ?? null))
            return;

        $data = $info['data'];

        $model = $this->parseModel($this->configuredModel());

        if ($model !== null) {
            $currentId = is_array($data['model'] ?? null) ? (string)($data['model']['id'] ?? '') : '';
            $currentProvider = is_array($data['model'] ?? null) ? (string)($data['model']['providerID'] ?? '') : '';

            if ($currentId !== $model['id'] || $currentProvider !== $model['providerID']) {
                $this->requestJson('POST', '/api/session/'.rawurlencode($sessionId).'/model', ['model' => $model]);
            }
        }

        $agent = $this->resolveAgent((string)$this->settings->get('stezkoy-ai-openreply.opencode_agent', ''));

        if ($agent !== '' && (string)($data['agent'] ?? '') !== $agent) {
            $this->requestJson('POST', '/api/session/'.rawurlencode($sessionId).'/agent', ['agent' => $agent]);
        }

        $this->syncPersona($sessionId);
    }

    /**
     * Keeps the session's persona (the admin's system-prompt setting) in line
     * with the current settings. opencode 2.x stores such instructions per
     * session via the experimental instructions-entries API and feeds them to
     * the model as part of its context — so each discussion can carry its own
     * persona without touching the server config.
     */
    private function syncPersona(string $sessionId): void
    {
        $persona = (string)$this->settings->get('stezkoy-ai-openreply.opencode_system_prompt', '');
        $escaped = rawurlencode($sessionId);

        if ($persona !== '') {
            $this->requestJson('PUT', '/api/experimental/session/'.$escaped.'/instructions/entries/persona', ['value' => $persona], true);
            return;
        }

        // Persona cleared: drop the entry if it exists (a missing key is a
        // benign 404, which the soft call tolerates).
        $this->requestJson('DELETE', '/api/experimental/session/'.$escaped.'/instructions/entries/persona', [], true);
    }

    /**
     * Blocks until the session finished processing the current prompt. The
     * wait endpoint is the normal path; if it is unavailable (it is
     * experimental), fall back to a bounded poll of the message list.
     */
    private function waitForIdle(string $sessionId): void
    {
        $escaped = rawurlencode($sessionId);

        $wait = $this->requestJson('POST', '/api/experimental/session/'.$escaped.'/wait', []);

        if ($wait !== null)
            return;

        for ($i = 0; $i < 24; $i++) {
            $this->sleep(5);

            $messages = $this->requestJson('GET', '/api/session/'.$escaped.'/message', []);

            if ($messages !== null && $this->assistantFinished($messages))
                return;
        }
    }

    private function assistantFinished(array $payload): bool
    {
        $messages = $payload['data'] ?? null;

        if (!is_array($messages))
            return false;

        foreach ($messages as $message) {
            if (!is_array($message))
                continue;

            if (($message['type'] ?? null) !== 'assistant')
                continue;

            if (is_string($message['finish'] ?? null) && $message['finish'] !== '')
                return true;
        }

        return false;
    }

    /**
     * The opencode server only knows agents defined in its config (opencode.json).
     * An unknown name makes it reject the whole prompt, so we check the id
     * against GET /api/agent and fall back to the default agent if it is not
     * among the known ones.
     */
    private function resolveAgent(string $agent): string
    {
        if ($agent === '')
            return '';

        $known = $this->agents();

        if ($known === null)
        {
            $this->logger->warning('[AI Open-Reply] Could not fetch the agent list from the opencode server; using the default agent.');
            return '';
        }

        foreach ($known as $candidate)
        {
            if (($candidate['id'] ?? null) === $agent)
                return $agent;
        }

        $this->logger->warning(
            '[AI Open-Reply] Agent "'.$agent.'" is not defined on the opencode server (see GET /api/agent); using the default agent.'
        );

        return '';
    }

    private function retryAttempts(): int
    {
        $attempts = (int)$this->settings->get('stezkoy-ai-openreply.retry_attempts', 1);

        return max(1, min(10, $attempts));
    }

    private function retryDelaySeconds(): int
    {
        $delay = (int)$this->settings->get('stezkoy-ai-openreply.retry_delay_seconds', 1);

        return max(0, min(120, $delay));
    }

    private function parseModel(?string $model): ?array
    {
        if (empty($model))
            return null;

        $parts = explode('/', $model);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            $this->logger->warning(
                '[AI Open-Reply] Invalid model "'.$model.'" (expected "provider/model"); using the server default.'
            );
            return null;
        }

        // opencode 2.x identifies a model as `{ id, providerID }`.
        return ['id' => $parts[1], 'providerID' => $parts[0]];
    }

    private function requestJson(string $method, string $path, array $body, bool $soft = false): ?array
    {
        $attempts = $this->retryAttempts();
        $delay = $this->retryDelaySeconds();

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $options = [];

                if ($body !== [])
                    $options[RequestOptions::JSON] = $body;

                $response = $this->client->request($method, $this->url.$path, $options);

                $status = $response->getStatusCode();

                // The v2 API answers 2xx-only endpoints (DELETE, PATCH, the
                // wait polling hook) with 204 and an empty body.
                if ($status === 204)
                    return ['ok' => true];

                if ($status === 401 && $attempt < $attempts) {
                    // Transient 401s right after the server starts up are a
                    // known opencode quirk; retry like any other hiccup.
                    $this->logger->warning("[AI Open-Reply] opencode {$method} {$path} returned 401; retrying {$attempt}/{$attempts}...");
                    $this->sleep($delay);
                    continue;
                }

                if ($status >= 400) {
                    if ($soft && $status === 404) {
                        return null;
                    }

                    if ($status >= 500) {
                        // Retryable server error (opencode is probably busy/crashing).
                        if ($attempt < $attempts) {
                            $this->logger->warning("[AI Open-Reply] opencode {$method} {$path} failed with HTTP {$status}; retrying {$attempt}/{$attempts}...");
                            $this->sleep($delay);
                            continue;
                        }
                        $errorBody = (string)$response->getBody();
                        $this->logger->error("[AI Open-Reply] opencode {$method} {$path} failed ({$status}): ".$errorBody);
                        return null;
                    }

                    // Other 4xx client errors are not retryable, but we still log them.
                    $errorBody = (string)$response->getBody();
                    $this->logger->error("[AI Open-Reply] opencode {$method} {$path} failed ({$status}): ".$errorBody);
                    return null;
                }

                $responseBody = (string)$response->getBody();

                $json = json_decode($responseBody, true);

                if (!is_array($json)) {
                    $preview = mb_substr(trim(preg_replace('/\s+/', ' ', $responseBody)), 0, 500);
                    $this->logger->error(
                        '[AI Open-Reply] opencode responded with an invalid JSON payload (status '.$status.'). Body: '
                        .($preview !== '' ? $preview : '(empty)')
                    );
                    return null;
                }

                return $json;
            } catch (\Throwable $e) {
                // Network-level failure (connection refused, DNS, timeout, etc.) — retryable.
                if ($attempt < $attempts) {
                    $this->logger->warning("[AI Open-Reply] opencode request {$method} {$path} failed: ".$e->getMessage().'; retrying '.$attempt.'/'.$attempts.'...');
                    $this->sleep($delay);
                    continue;
                }
                $this->logger->error("[AI Open-Reply] opencode request {$method} {$path} failed: ".$e->getMessage());
                return null;
            }
        }

        return null;
    }

    private function sleep(int $seconds): void
    {
        if ($seconds > 0)
            usleep($seconds * 1000000);
    }

    /**
     * Extracts the assistant's newest text reply from a
     * GET /api/session/:id/message payload (`{ data: [...] }`).
     */
    private function extractText(array $payload): ?string
    {
        $messages = $payload['data'] ?? null;

        if (!is_array($messages))
            return null;

        $latest = null;
        $latestCreated = -1.0;

        foreach ($messages as $message) {
            if (!is_array($message))
                continue;

            if (($message['type'] ?? null) !== 'assistant')
                continue;

            $time = $message['time'] ?? null;
            $created = is_array($time) ? (float)($time['created'] ?? 0) : 0.0;

            if ($created >= $latestCreated) {
                $latest = $message;
                $latestCreated = $created;
            }
        }

        if ($latest === null)
            return null;

        $texts = [];

        foreach (($latest['content'] ?? []) as $part) {
            if (!is_array($part))
                continue;

            if (($part['type'] ?? null) !== 'text')
                continue;

            $text = trim((string)($part['text'] ?? ''));

            if ($text === '')
                continue;

            $texts[] = $text;
        }

        return $texts === [] ? null : implode("\n\n", $texts);
    }
}