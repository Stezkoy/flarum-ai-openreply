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
    .setting(() => {
      const freeModels = {
        '': app.translator.trans('stezkoy-ai-openreply.admin.settings.model_default_option'),
        'opencode/big-pickle': 'Big Pickle',
        'opencode/mimo-v2.5-free': 'MiMo-V2.5 Free',
        'opencode/hy3-free': 'Hy3 Free',
        'opencode/ling-3.0-flash-fin-free': 'Ling 3.0 Flash Fin Free',
        'opencode/nemotron-3-ultra-free': 'Nemotron 3 Ultra Free',
        'opencode/nemotron-3.5-lightning-free': 'Nemotron 3.5 Lightning Free',
        'opencode/muse-spark-1.2-contributor-free': 'Muse Spark 1.2 Contributor Free',
      };

      return {
        setting: 'stezkoy-ai-openreply.model',
        type: 'select',
        options: freeModels,
        label: app.translator.trans('stezkoy-ai-openreply.admin.settings.model_label'),
        help: app.translator.trans('stezkoy-ai-openreply.admin.settings.model_help'),
      };
    })
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
      setting: 'stezkoy-ai-openreply.max_active_sessions',
      type: 'number',
      label: app.translator.trans('stezkoy-ai-openreply.admin.settings.max_active_sessions_label'),
      help: app.translator.trans('stezkoy-ai-openreply.admin.settings.max_active_sessions_help'),
    }))
    .setting(() => ({
      setting: 'stezkoy-ai-openreply.max_messages_per_session',
      type: 'number',
      label: app.translator.trans('stezkoy-ai-openreply.admin.settings.max_messages_per_session_label'),
      help: app.translator.trans('stezkoy-ai-openreply.admin.settings.max_messages_per_session_help'),
    }))
    .setting(() => ({
      setting: 'stezkoy-ai-openreply.session_ttl_days',
      type: 'number',
      label: app.translator.trans('stezkoy-ai-openreply.admin.settings.session_ttl_days_label'),
      help: app.translator.trans('stezkoy-ai-openreply.admin.settings.session_ttl_days_help'),
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
