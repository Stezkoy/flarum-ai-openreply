<?php

namespace Stezkoy\FlarumAIOpenReply\Job;

use Carbon\Carbon;
use Flarum\Post\CommentPost;
use Flarum\Queue\AbstractJob;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
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
        private readonly int $timeout = 0,
    ) {
    }

    /**
     * The Flarum queue worker runs with a --timeout (default 60s). Generating an
     * AI reply is a single synchronous HTTP call to the opencode server that can
     * legitimately take far longer (the client allows up to 600s per request, and
     * retries add more). Without this method the worker's timeout alarm kills the
     * job mid-request with a TimeoutExceededException. We return the budget that
     * was captured at dispatch time (which already covers the retry envelope).
     */
    public function timeout(): int
    {
        return $this->timeout > 0 ? $this->timeout : 600;
    }

    public function handle(
        OpencodeClient $client,
        SettingsRepositoryInterface $settings,
        LoggerInterface $logger,
        Dispatcher $events
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
                $sessionId = $client->createSession($this->discussionTitle);

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

            $assistant = User::find($this->assistantId);

            if ($assistant === null)
            {
                $logger->error('[AI Open-Reply] Assistant user '.$this->assistantId.' no longer exists; reply skipped.');
                return;
            }

            $post = new CommentPost();
            $post->discussion_id = $this->discussionId;
            $post->created_at = Carbon::now();
            $post->user_id = $this->assistantId;
            $post->content = $content;

            $post->save();

            // CommentPost::boot() raises a Posted event during save (its
            // creating observer) and leaves it pending on the model. The API
            // layer releases those events via HasHooks::dispatchEventsFor();
            // a queue job must do it itself, so that core listeners update the
            // discussion (comment_count, last post, participant count), the
            // assistant's stats, and subscribers receive notifications. The
            // assistant user is passed as the actor — the same semantics a
            // user-authored post has.
            foreach ($post->releaseEvents() as $event)
            {
                if (property_exists($event, 'actor') && !$event->actor)
                    $event->actor = $assistant;

                $events->dispatch($event);
            }

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
