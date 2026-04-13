<?php

namespace MichaelBelgium\FlarumAIAutoReply\Job;

use Carbon\Carbon;
use Flarum\Post\CommentPost;
use Flarum\Queue\AbstractJob;
use GuzzleHttp\Exception\ClientException;
use MichaelBelgium\FlarumAIAutoReply\AnthropicClient;
use MichaelBelgium\FlarumAIAutoReply\GoogleClient;
use MichaelBelgium\FlarumAIAutoReply\IPlatform;
use MichaelBelgium\FlarumAIAutoReply\OpenAIClient;
use MichaelBelgium\FlarumAIAutoReply\OpenrouterClient;
use Psr\Log\LoggerInterface;

class Reply extends AbstractJob
{
    public function __construct(
        private readonly string $platform,
        private readonly int $discussionId,
        private readonly int $assistantId,
        private readonly array $conversation,
    ) {
    }

    public function handle(
        LoggerInterface $logger,
        OpenAIClient $openAIClient,
        AnthropicClient $anthropicClient,
        OpenrouterClient $openrouterClient,
        GoogleClient $googleClient,
    ) {
        /** @var IPlatform $client */
        $client = match($this->platform) {
            'anthropic' => $anthropicClient,
            'openrouter' => $openrouterClient,
            'google' => $googleClient,
            default => $openAIClient,
        };

        try
        {
            $content = $client->completions($this->conversation);

            $post = new CommentPost();
            $post->discussion_id = $this->discussionId;
            $post->created_at = Carbon::now();
            $post->user_id = $this->assistantId;
            $post->content = $content;

            $post->save();
        } catch (ClientException $e) {
            $response = json_decode($e->getResponse()->getBody());
            $logger->error('[AI-AutoReply] Client error while generating reply: ' .$response);
        } catch (\Exception $e) {
            $logger->error('[AI-AutoReply] Error while generating reply: ' . $e->getMessage());
        }
    }
}