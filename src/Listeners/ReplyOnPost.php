<?php

namespace Stezkoy\FlarumAIOpenReply\Listeners;

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

        $discussion = $event->post->discussion;
        $enabledTagIds = $this->settings->get('stezkoy-ai-openreply.enabled-tags', '[]');

        if ($enabledTagIds = json_decode($enabledTagIds, true))
        {
            $tagIds = Arr::pluck($discussion->tags, 'id');

            if (!array_intersect($enabledTagIds, $tagIds))
                return;
        }

        $event->actor->assertCan('useAIAssistant', $discussion);

        $replyOnDiscussionStart = $this->settings->get('stezkoy-ai-openreply.enable_on_discussion_started', true);
        $assistantId = $this->settings->get('stezkoy-ai-openreply.user_prompt');

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

        if ($discussion->posts->count() == 1)
            $op = $event->actor->id; //$discussion->firstPost is null when discussion is started :(
        else
        {
            if ($replyOnDiscussionStart)
                return; //only reply on discussion start, not on subsequent posts

            $op = $discussion->firstPost->user->id;

            if ($op != $event->actor->id)
                return; //only reply to posts made by OP
        }

        $this->queue->push(new Reply(
            $discussion->id,
            $assistantId,
            (string)$event->post->content,
            $discussion->title,
        ));
    }
}