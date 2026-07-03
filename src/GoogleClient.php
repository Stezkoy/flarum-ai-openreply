<?php

namespace MichaelBelgium\FlarumAIAutoReply;

use GuzzleHttp\RequestOptions;

class GoogleClient extends Platform
{
    public function completions(array $messages): ?string
    {
        if ($this->client === null)
            return null;

        $options = [
            'messages' => $messages,
            'max_completion_tokens' => $this->maxTokens,
            'model' => $this->resolveModel(),
            'temperature' => $this->temperature,
        ];

        $response = $this->client->post('https://generativelanguage.googleapis.com/v1beta/openai/chat/completions', [
            RequestOptions::JSON => $options
        ]);

        $json = json_decode($response->getBody()->getContents(), true);

        return $json['choices'][0]['message']['content'];
    }

    protected function getDefaultModel(): string
    {
        return 'gemini-2.5-flash-lite';
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ];
    }
}