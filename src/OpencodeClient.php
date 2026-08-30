<?php

namespace Stezkoy\FlarumAIOpenReply;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;

/**
 * Talks to a headless `opencode serve` HTTP server (opencode 1.x API).
 *
 * A session is created with only `{ parentID?, title? }`. The agent and
 * model are applied per-message on `POST /session/:id/message` (agent as a
 * string, model as `{ providerID, modelID }`), so new discussions pick up
 * the latest settings.
 *
 * @see https://opencode.ai/docs/server/
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
        $url = (string)$this->settings->get('stezkoy-ai-openreply.opencode_url', 'http://localhost:4096');

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

        $payload = $this->requestJson('POST', '/session', ['title' => $title]);

        return $payload['id'] ?? null;
    }

    public function health(): bool
    {
        if ($this->client === null)
            return false;

        $payload = $this->requestJson('GET', '/global/health', []);

        return is_array($payload) && !empty($payload['healthy']);
    }

    public function configuredModel(): string
    {
        return (string)$this->settings->get('stezkoy-ai-openreply.model', '');
    }

    /**
     * Returns the `GET /provider` payload (connected providers + default models),
     * or null when the server is unreachable.
     *
     * @return array{all?: array, default?: array, connected?: array}|null
     */
    public function providers(): ?array
    {
        if ($this->client === null)
            return null;

        return $this->requestJson('GET', '/provider', []);
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

        $payload = $this->requestJson('GET', '/session', []);

        return is_array($payload) ? count($payload) : null;
    }

    public function deleteSession(string $sessionId): bool
    {
        if ($this->client === null)
            return false;

        // A 404 just means the session is already gone (e.g. it was closed by a
        // TTL/limit or externally), which is normal and not worth logging as an error.
        // The endpoint also returns a bare `true` on success instead of JSON, which
        // requestJson() tolerates when $soft is enabled.
        $this->requestJson('DELETE', '/session/'.rawurlencode($sessionId), [], true);

        return true;
    }

    /**
     * Returns the agents currently known to the opencode server (GET /agent),
     * or null when the server is unreachable. Each entry has at least a "name".
     */
    public function agents(): ?array
    {
        if ($this->client === null)
            return null;

        if ($this->agents === null)
            $this->agents = $this->requestJson('GET', '/agent', []);

        return $this->agents;
    }

    public function reply(string $sessionId, string $text): ?string
    {
        if ($this->client === null)
            return null;

        $path = '/session/'.rawurlencode($sessionId).'/message';

        $body = [
            'parts' => [
                ['type' => 'text', 'text' => $text],
            ],
        ];

        $agent = $this->resolveAgent((string)$this->settings->get('stezkoy-ai-openreply.opencode_agent', ''));
        $model = $this->parseModel($this->configuredModel());
        $system = (string)$this->settings->get('stezkoy-ai-openreply.opencode_system_prompt', '');

        if ($agent !== '')
            $body['agent'] = $agent;

        if ($model !== null)
            $body['model'] = $model;

        if ($system !== '')
            $body['system'] = $system;

        $payload = $this->requestJson('POST', $path, $body);

        return $payload === null ? null : $this->extractText($payload);
    }

    /**
     * The opencode server only knows agents defined in its config (opencode.json).
     * An unknown name makes it reject the whole message with HTTP 500, so we check
     * the name against GET /agent and fall back to the default agent if it is not
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
            if (($candidate['name'] ?? null) === $agent)
                return $agent;
        }

        $this->logger->warning(
            '[AI Open-Reply] Agent "'.$agent.'" is not defined on the opencode server (see GET /agent); using the default agent.'
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

        return ['providerID' => $parts[0], 'modelID' => $parts[1]];
    }

    private function requestJson(string $method, string $path, array $body, bool $soft = false): ?array
    {
        $attempts = $this->retryAttempts();
        $delay = $this->retryDelaySeconds();

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->client->request($method, $this->url.$path, [
                    RequestOptions::JSON => $body,
                ]);

                $status = $response->getStatusCode();

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

                    // 4xx client errors are not retryable, but we still log them.
                    $errorBody = (string)$response->getBody();
                    $this->logger->error("[AI Open-Reply] opencode {$method} {$path} failed ({$status}): ".$errorBody);
                    return null;
                }

                $responseBody = (string)$response->getBody();

                $json = json_decode($responseBody, true);

                if (!is_array($json)) {
                    // Some endpoints (e.g. DELETE /session/:id) return a bare
                    // boolean on success instead of JSON. Only endpoints that
                    // opt in via $soft may return a non-object body.
                    if ($soft && is_bool($json)) {
                        return ['ok' => $json];
                    }

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

    private function extractText(array $payload): ?string
    {
        $parts = $payload['parts'] ?? [];
        $texts = [];

        foreach ($parts as $part) {
            if (($part['type'] ?? null) !== 'text')
                continue;
            if (!empty($part['synthetic']))
                continue;

            $text = trim((string)($part['text'] ?? ''));

            if ($text === '')
                continue;

            $texts[] = $text;
        }

        return $texts === [] ? null : implode("\n\n", $texts);
    }
}