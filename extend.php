<?php

/*
 * This file is part of datlechin/flarum-chatgpt.
 *
 * Copyright (c) 2023 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace MichaelBelgium\FlarumAIAutoReply;

use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Post\Event\Posted;
use MichaelBelgium\FlarumAIAutoReply\Access\DiscussionPolicy;
use MichaelBelgium\FlarumAIAutoReply\Listeners\ReplyOnPost;

return [
    new Extend\Frontend('forum')
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    new Extend\Frontend('admin')
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    new Extend\Settings()
        ->default('michaelbelgium-ai-autoreply.platform', 'openai')
        ->default('michaelbelgium-ai-autoreply.enable_on_discussion_started', true)
        ->default('michaelbelgium-ai-autoreply.user_prompt_badge_text', 'Assistant')
        ->serializeToForum('chatGptUserPromptId', 'michaelbelgium-ai-autoreply.user_prompt')
        ->serializeToForum('chatGptBadgeText', 'michaelbelgium-ai-autoreply.user_prompt_badge_text'),

    new Extend\Event()
        ->listen(Posted::class, ReplyOnPost::class),

    new Extend\Policy()
        ->modelPolicy(Discussion::class, DiscussionPolicy::class),
];