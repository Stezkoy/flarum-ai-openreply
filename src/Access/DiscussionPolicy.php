<?php

namespace Stezkoy\FlarumAIOpenReply\Access;

use Flarum\Discussion\Discussion;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class DiscussionPolicy extends AbstractPolicy
{
    public function useAIAssistant(User $actor, Discussion $discussion): bool
    {
        return $actor->hasPermission('discussion.useAIAssistant');
    }
}