<?php

namespace Stezkoy\FlarumAIOpenReply;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;

/**
 * Talks to a headless `opencode serve` HTTP server (opencode 1.x API).
 *
 * Model and agent are fixed at session creation time: the opencode 1.x
 * server does not accept them per-message. Tools are denied with an
 * allowlist of zero permissions so the assistant only ever replies with
 * text.
 *
 * @see https://opencode.ai/docs/server/
 */
class OpencodeClient
{
    protected ?Client $client = null;
    protected string $url = '';

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

    public function createSession(string $title, ?string $agent = null): ?string
    {
        if ($this->client === null)
            return null;

        $body = [
            'title' => $title,
        ];

        if (!empty($agent))
            $body['agent'] = $agent;

        $payload = $this->requestJson('POST', '/session', $body);

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
        $this->requestJson('DELETE', '/session/'.rawurlencode($sessionId), [], true);

        return true;
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

        $agent = (string)$this->settings->get('stezkoy-ai-openreply.opencode_agent', '');
        $model = $this->parseModel($this->configuredModel());

        if ($agent !== '')
            $body['agent'] = $agent;

        if ($model !== null)
            $body['model'] = $model;

        $payload = $this->requestJson('POST', $path, $body);

        return $payload === null ? null : $this->extractText($payload);
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
        try {
            $response = $this->client->request($method, $this->url.$path, [
                RequestOptions::JSON => $body,
            ]);

            $status = $response->getStatusCode();

            if ($status >= 400) {
                if ($soft && $status === 404) {
                    return null;
                }

                $errorBody = (string)$response->getBody();
                $this->logger->error("[AI Open-Reply] opencode {$method} {$path} failed ({$status}): ".$errorBody);
                return null;
            }

            $json = json_decode((string)$response->getBody(), true);

            if (!is_array($json)) {
                $this->logger->error('[AI Open-Reply] opencode responded with an invalid JSON payload.');
                return null;
            }

            return $json;
        } catch (\Throwable $e) {
            $this->logger->error("[AI Open-Reply] opencode request {$method} {$path} failed: ".$e->getMessage());
            return null;
        }
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