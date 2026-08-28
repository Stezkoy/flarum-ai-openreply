<?php

namespace Stezkoy\FlarumAIOpenReply\Job;

use Carbon\Carbon;
use Flarum\Post\CommentPost;
use Flarum\Queue\AbstractJob;
use Flarum\Settings\SettingsRepositoryInterface;
use Stezkoy\FlarumAIOpenReply\OpencodeClient;
use Stezkoy\FlarumAIOpenReply\OpencodeSession;
use Psr\Log\LoggerInterface;

class Reply extends AbstractJob
{
    public function __construct(
        private readonly int $discussionId,
        private readonly int $assistantId,
        private readonly string $postText,
        private readonly string $discussionTitle,
    ) {
    }

    public function handle(
        OpencodeClient $client,
        SettingsRepositoryInterface $settings,
        LoggerInterface $logger
    ) {
        try
        {
            $session = OpencodeSession::query()->firstOrCreate([
                'discussion_id' => $this->discussionId,
            ]);

            if (empty($session->session_id))
            {
                $sessionId = $client->createSession(
                    $this->discussionTitle,
                    $settings->get('stezkoy-ai-openreply.opencode_agent') ?: null,
                    $settings->get('stezkoy-ai-openreply.model') ?: null,
                );

                if (empty($sessionId))
                    return;

                $session->session_id = $sessionId;
                $session->save();
            }

            $content = $client->reply(
                $session->session_id,
                $this->postText,
            );

            if (empty($content))
                return;

            $post = new CommentPost();
            $post->discussion_id = $this->discussionId;
            $post->created_at = Carbon::now();
            $post->user_id = $this->assistantId;
            $post->content = $content;

            $post->save();
        } catch (\Throwable $e) {
            $logger->error('[AI Open-Reply] Error while generating reply: ' . $e->getMessage());
        }
    }
}