<?php

/*
 * This file is part of stezkoy/flarum-ai-openreply.
 *
 * Copyright (c) 2023 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Stezkoy\FlarumAIOpenReply;

use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Post\Event\Posted;
use Stezkoy\FlarumAIOpenReply\Access\DiscussionPolicy;
use Stezkoy\FlarumAIOpenReply\Listeners\ReplyOnPost;

return [
    new Extend\Frontend('forum')
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    new Extend\Frontend('admin')
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    new Extend\Settings()
        ->default('stezkoy-ai-openreply.opencode_url', 'http://localhost:4096')
        ->default('stezkoy-ai-openreply.enable_on_discussion_started', true)
        ->default('stezkoy-ai-openreply.user_prompt_badge_text', 'Assistant')
        ->serializeToForum('aiAssistantUserId', 'stezkoy-ai-openreply.user_prompt')
        ->serializeToForum('aiAssistantBadgeText', 'stezkoy-ai-openreply.user_prompt_badge_text'),

    new Extend\Event()
        ->listen(Posted::class, ReplyOnPost::class),

    new Extend\Policy()
        ->modelPolicy(Discussion::class, DiscussionPolicy::class),
];