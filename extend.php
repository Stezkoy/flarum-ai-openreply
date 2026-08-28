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
use Stezkoy\FlarumAIOpenReply\Api\Controller\CloseAllSessionsController;
use Stezkoy\FlarumAIOpenReply\Api\Controller\HealthController;
use Stezkoy\FlarumAIOpenReply\Api\Controller\SessionCountController;
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
        ->default('stezkoy-ai-openreply.opencode_username', 'opencode')
        ->default('stezkoy-ai-openreply.enable_on_discussion_started', true)
        ->default('stezkoy-ai-openreply.user_prompt_badge_text', 'Assistant')
        ->default('stezkoy-ai-openreply.max_active_sessions', 10)
        ->default('stezkoy-ai-openreply.max_messages_per_session', 15)
        ->default('stezkoy-ai-openreply.session_ttl_days', 3)
        ->serializeToForum('aiAssistantUserId', 'stezkoy-ai-openreply.user_prompt')
        ->serializeToForum('aiAssistantBadgeText', 'stezkoy-ai-openreply.user_prompt_badge_text'),

    new Extend\Routes('api')
        ->post('/ai-openreply/health', 'ai-openreply.health', HealthController::class)
        ->post('/ai-openreply/close-all', 'ai-openreply.close-all', CloseAllSessionsController::class)
        ->post('/ai-openreply/count', 'ai-openreply.count', SessionCountController::class),

    new Extend\Event()
        ->listen(Posted::class, ReplyOnPost::class),

    new Extend\Policy()
        ->modelPolicy(Discussion::class, DiscussionPolicy::class),
];