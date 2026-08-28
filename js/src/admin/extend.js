import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';

export default [
  new Extend.Admin()
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
