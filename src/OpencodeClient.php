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

        if (!empty($password)) {
            $options[RequestOptions::AUTH] = ['opencode', $password];
        }

        $this->url = rtrim($url, '/');
        $this->client = new Client($options);
    }

    public function createSession(string $title, ?string $agent = null, ?string $model = null): ?string
    {
        if ($this->client === null)
            return null;

        $body = [
            'title' => $title,
            // Deny every tool so the assistant only ever answers with text.
            'permission' => [
                ['permission' => '*', 'pattern' => '*', 'action' => 'deny'],
            ],
        ];

        if (!empty($agent))
            $body['agent'] = $agent;

        $parsedModel = $this->parseModel($model);

        if ($parsedModel !== null)
            $body['model'] = $parsedModel;

        $payload = $this->requestJson('POST', '/session', $body);

        return $payload['id'] ?? null;
    }

    public function deleteSession(string $sessionId): bool
    {
        if ($this->client === null)
            return false;

        $this->requestJson('DELETE', '/session/'.rawurlencode($sessionId), []);

        return true;
    }

    public function reply(string $sessionId, string $text): ?string
    {
        if ($this->client === null)
            return null;

        $path = '/session/'.rawurlencode($sessionId).'/message';

        $payload = $this->requestJson('POST', $path, [
            'parts' => [
                ['type' => 'text', 'text' => $text],
            ],
        ]);

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

        return ['providerID' => $parts[0], 'id' => $parts[1]];
    }

    private function requestJson(string $method, string $path, array $body): ?array
    {
        try {
            $response = $this->client->request($method, $this->url.$path, [
                RequestOptions::JSON => $body,
            ]);

            $status = $response->getStatusCode();

            if ($status >= 400) {
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