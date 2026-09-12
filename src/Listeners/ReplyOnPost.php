<?php

namespace Stezkoy\FlarumAIOpenReply\Listeners;

use Flarum\Discussion\Discussion;
use Flarum\Post\Event\Posted;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Support\Arr;
use Stezkoy\FlarumAIOpenReply\Job\Reply;
use Psr\Log\LoggerInterface;

class ReplyOnPost
{
    public function __construct(
        protected Queue $queue,
        protected SettingsRepositoryInterface $settings,
        protected LoggerInterface $logger,
    ) {
    }

    public function handle(Posted $event): void
    {
        if (!$event->actor)
            return;

        $assistantId = $this->settings->get('stezkoy-ai-openreply.user_prompt');

        // Never reply to the assistant's own posts. The Reply job dispatches
        // its Posted event with the assistant user as the actor, so without
        // this guard the assistant would answer itself endlessly whenever
        // "reply to all" is enabled.
        if (!empty($assistantId) && (int)$event->actor->id === (int)$assistantId)
            return;

        $discussion = $event->post->discussion;

        $enabledTagIds = json_decode((string)$this->settings->get('stezkoy-ai-openreply.enabled-tags', '[]'), true);

        if (is_array($enabledTagIds) && $enabledTagIds !== [])
        {
            // flarum-tags extension not available — no filtering.
            if (!class_exists('Flarum\Tags\Tag'))
                return;

            $tagIds = Arr::pluck($discussion->tags, 'id');

            if (!array_intersect($enabledTagIds, $tagIds))
                return;
        }

        if (!$event->actor->can('useAIAssistant', $discussion))
            return;

        $replyOnDiscussionStart = $this->settings->get('stezkoy-ai-openreply.enable_on_discussion_started', true);

        if (empty($assistantId))
        {
            $this->logger->error('AI assistant: No assistant user set');
            return;
        }

        $assistant = User::find($assistantId);

        if ($assistant === null)
        {
            $this->logger->error("AI assistant: No assistant user found with ID $assistantId");
            return;
        }

        // The discussion-starting post is number 1 (assigned by Post::boot's
        // creating observer). Reading ->number avoids the posts relation,
        // which would lazy-load every post of the discussion just to count them.
        if ((int)$event->post->number !== 1)
        {
            if ($replyOnDiscussionStart)
                return; //only reply on discussion start, not on subsequent posts

            $replyToAll = $this->settings->get('stezkoy-ai-openreply.reply_to_all_in_discussion', false);

            if (!$replyToAll) {
                $op = $discussion->firstPost->user->id;

                if ($op != $event->actor->id)
                    return; //only reply to posts made by OP
            }
        }

        $timeout = $this->jobTimeout();

        $this->queue->push(new Reply(
            $discussion->id,
            $assistantId,
            $this->buildContext($discussion, (string)$event->post->content),
            $discussion->title,
            $timeout,
        ));
    }

    private function buildContext(Discussion $discussion, string $content): string
    {
        $lines = [];

        if ($discussion->title !== '')
            $lines[] = '[Discussion: '.$discussion->title.']';

        if (class_exists('Flarum\Tags\Tag') && $discussion->tags !== null)
        {
            $names = Arr::pluck($discussion->tags, 'name');

            if ($names !== [])
                $lines[] = '[Tags: '.implode(', ', $names).']';
        }

        if ($lines === [])
            return $content;

        return implode("\n", $lines)."\n\n".$content;
    }

    /**
     * The queue worker's --timeout (default 60s) is shorter than the worst-case
     * AI generation: each opencode call can run up to the client's 600s request
     * timeout, and retries on top of that add more. Compute a job timeout that
     * covers the full retry envelope so the worker doesn't kill the job mid-call
     * with a TimeoutExceededException.
     */
    private function jobTimeout(): int
    {
        $attempts = max(1, (int)$this->settings->get('stezkoy-ai-openreply.retry_attempts', 1));
        $delay = max(0, (int)$this->settings->get('stezkoy-ai-openreply.retry_delay_seconds', 1));

        // 600s per request timeout (see OpencodeClient) + buffer.
        return ($attempts * 600) + (($attempts - 1) * $delay) + 30;
    }
}