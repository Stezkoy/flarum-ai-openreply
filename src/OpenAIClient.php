<?php

namespace MichaelBelgium\FlarumAIAutoReply;

use GuzzleHttp\RequestOptions;

class OpenAIClient extends Platform
{
    public function completions(array $messages): ?string
    {
        if ($this->client === null)
            return null;

        if ($this->systemPrompt !== null)
        {
            $messages = [
                ['role' => 'developer', 'content' => $this->systemPrompt],
                ...$messages,
            ];
        }

        $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
            RequestOptions::JSON => [
                'model' => $this->resolveModel(),
                'messages' => $messages,
                'max_completion_tokens' => $this->maxTokens,
                'temperature' => $this->temperature ?? 1,
            ]
        ]);

        $json = json_decode((string)$response->getBody(), true);

        return $json['choices'][0]['message']['content'];
    }

    protected function getDefaultModel(): string
    {
        return 'gpt-5-mini';
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->apiKey}",
        ];
    }
}
