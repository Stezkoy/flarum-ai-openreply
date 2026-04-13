import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';

export default [
    new Extend.Admin()
        .setting(() => ({
            setting: 'michaelbelgium-ai-autoreply.max_tokens',
            type: 'number',
            label: app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.max_tokens_label'),
            help: app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.max_tokens_help', {
                a: <a href="https://help.openai.com/en/articles/4936856" target="_blank" rel="noopener" />,
            }),
        })).setting(() => ({
            setting: 'michaelbelgium-ai-autoreply.temperature',
            type: 'number',
            step: 0.1,
            label: app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.temperature_label'),
            help: app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.temperature_help'),
        })).setting(() => ({
            setting: 'michaelbelgium-ai-autoreply.system_prompt',
            type: 'textarea',
            rows: 5,
            label: app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.system_prompt_label'),
            help: app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.system_prompt_help'),
            placeholder: app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.system_prompt_placeholder'),
        })).setting(() => ({
            setting: 'michaelbelgium-ai-autoreply.user_prompt',
            type: 'number',
            required: true,
            label: app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.user_prompt_label'),
            help: app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.user_prompt_help'),
        })).setting(() => ({
            setting: 'michaelbelgium-ai-autoreply.user_prompt_badge_text',
            type: 'text',
            label: app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.user_prompt_badge_label'),
            help: app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.user_prompt_badge_help'),
        })).setting(() => ({
            setting: 'michaelbelgium-ai-autoreply.enable_on_discussion_started',
            type: 'boolean',
            label: app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.enable_on_discussion_started_label'),
            help: app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.enable_on_discussion_started_help'),
        })).setting(() => ({
            type: 'flarum-tags.select-tags',
            setting: 'michaelbelgium-ai-autoreply.enabled-tags',
            label: app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.enabled_tags_label'),
            help: app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.enabled_tags_help'),
            options: {
                requireParentTag: false,
                limits: {
                    max: {
                        secondary: 0,
                    },
                },
            },
        })).permission(() => ({
            label: app.translator.trans('michaelbelgium-ai-autoreply.admin.permissions.use_chatgpt_assistant_label'),
            icon: 'fas fa-comment',
            permission: 'discussion.useChatGPTAssistant',
            allowGuest: false,
        }), 'start')
]