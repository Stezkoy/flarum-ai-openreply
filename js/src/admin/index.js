import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import AIOpenReplySettingsPage from './components/AIOpenReplySettingsPage';

app.initializers.add('stezkoy-ai-openreply', () => {
  app.registry
    .for('stezkoy-ai-openreply')
    .registerPage(AIOpenReplySettingsPage);
});

const extend = [
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

export { extend };
