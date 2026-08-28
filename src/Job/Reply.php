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
            $maxActive = (int)$settings->get('stezkoy-ai-openreply.max_active_sessions', 10);
            $maxMessages = (int)$settings->get('stezkoy-ai-openreply.max_messages_per_session', 15);
            $maxTtlDays = (int)$settings->get('stezkoy-ai-openreply.session_ttl_days', 3);

            $session = OpencodeSession::query()
                ->where('discussion_id', $this->discussionId)
                ->first();

            if ($session !== null && $maxTtlDays > 0 && $session->updated_at !== null)
            {
                $idleSince = Carbon::parse($session->updated_at);
                if ($idleSince->lt(Carbon::now()->subDays($maxTtlDays)))
                {
                    $client->deleteSession($session->session_id);
                    $session->delete();
                    $session = null;
                }
            }

            if ($session !== null && $maxMessages > 0 && (int)$session->message_count >= $maxMessages)
            {
                $client->deleteSession($session->session_id);
                $session->delete();
                $session = null;
            }

            if ($session === null && $maxActive > 0)
            {
                $count = OpencodeSession::query()->count();

                if ($count >= $maxActive)
                {
                    $toEvict = $count - $maxActive + 1;

                    $stale = OpencodeSession::query()
                        ->orderBy('updated_at')
                        ->limit($toEvict)
                        ->get();

                    foreach ($stale as $staleSession)
                    {
                        $client->deleteSession($staleSession->session_id);
                        $staleSession->delete();
                    }
                }
            }

            if ($session === null)
            {
                $sessionId = $client->createSession(
                    $this->discussionTitle,
                    $settings->get('stezkoy-ai-openreply.opencode_agent') ?: null,
                    $settings->get('stezkoy-ai-openreply.model') ?: null,
                );

                if (empty($sessionId))
                    return;

                $session = new OpencodeSession();
                $session->discussion_id = $this->discussionId;
                $session->session_id = $sessionId;
                $session->message_count = 0;
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

            $replyOnDiscussionStart = $settings->get('stezkoy-ai-openreply.enable_on_discussion_started', true);

            if ($replyOnDiscussionStart)
            {
                $client->deleteSession($session->session_id);
                $session->delete();
                return;
            }

            $session->message_count = (int)$session->message_count + 1;
            $session->save();
        } catch (\Throwable $e) {
            $logger->error('[AI Open-Reply] Error while generating reply: ' . $e->getMessage());
        }
    }
}
