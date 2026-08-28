import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';

export default [
  new Extend.Admin()
    .setting(() => ({
      setting: 'stezkoy-ai-openreply.opencode_url',
      type: 'text',
      label: app.translator.trans('stezkoy-ai-openreply.admin.settings.opencode_url_label'),
      help: app.translator.trans('stezkoy-ai-openreply.admin.settings.opencode_url_help'),
      placeholder: 'http://localhost:4096',
    }))
    .setting(() => ({
      setting: 'stezkoy-ai-openreply.opencode_password',
      type: 'password',
      label: app.translator.trans('stezkoy-ai-openreply.admin.settings.opencode_password_label'),
      help: app.translator.trans('stezkoy-ai-openreply.admin.settings.opencode_password_help'),
    }))
    .setting(() => ({
      setting: 'stezkoy-ai-openreply.opencode_agent',
      type: 'text',
      label: app.translator.trans('stezkoy-ai-openreply.admin.settings.opencode_agent_label'),
      help: app.translator.trans('stezkoy-ai-openreply.admin.settings.opencode_agent_help'),
    }))
    .setting(() => ({
      setting: 'stezkoy-ai-openreply.free_models',
      type: 'textarea',
      rows: 3,
      label: app.translator.trans('stezkoy-ai-openreply.admin.settings.free_models_label'),
      help: app.translator.trans('stezkoy-ai-openreply.admin.settings.free_models_help'),
    }))
    .setting(() => ({
      setting: 'stezkoy-ai-openreply.model',
      type: 'text',
      placeholder: 'provider/model',
      label: app.translator.trans('stezkoy-ai-openreply.admin.settings.model_label'),
      help: app.translator.trans('stezkoy-ai-openreply.admin.settings.model_help'),
    }))
    .setting(() => ({
      setting: 'stezkoy-ai-openreply.user_prompt',
      type: 'number',
      required: true,
      label: app.translator.trans('stezkoy-ai-openreply.admin.settings.user_prompt_label'),
      help: app.translator.trans('stezkoy-ai-openreply.admin.settings.user_prompt_help'),
    }))
    .setting(() => ({
      setting: 'stezkoy-ai-openreply.user_prompt_badge_text',
      type: 'text',
      label: app.translator.trans('stezkoy-ai-openreply.admin.settings.user_prompt_badge_label'),
      help: app.translator.trans('stezkoy-ai-openreply.admin.settings.user_prompt_badge_help'),
    }))
    .setting(() => ({
      setting: 'stezkoy-ai-openreply.enable_on_discussion_started',
      type: 'boolean',
      label: app.translator.trans('stezkoy-ai-openreply.admin.settings.enable_on_discussion_started_label'),
      help: app.translator.trans('stezkoy-ai-openreply.admin.settings.enable_on_discussion_started_help'),
    }))
    .setting(() => ({
      type: 'flarum-tags.select-tags',
      setting: 'stezkoy-ai-openreply.enabled-tags',
      label: app.translator.trans('stezkoy-ai-openreply.admin.settings.enabled_tags_label'),
      help: app.translator.trans('stezkoy-ai-openreply.admin.settings.enabled_tags_help'),
      options: {
        requireParentTag: false,
        limits: {
          max: {
            secondary: 0,
          },
        },
      },
    }))
    .permission(
      () => ({
        label: app.translator.trans('stezkoy-ai-openreply.admin.permissions.use_ai_assistant_label'),
        icon: 'fas fa-robot',
        permission: 'discussion.useAIAssistant',
        allowGuest: false,
      }),
      'start'
    ),
];
