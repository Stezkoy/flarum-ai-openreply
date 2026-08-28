import app from 'flarum/admin/app';
import AIOpenReplySettingsPage from './components/AIOpenReplySettingsPage';

app.initializers.add('stezkoy-ai-openreply', () => {
  app.registry
    .for('stezkoy-ai-openreply')
    .registerPage(AIOpenReplySettingsPage);
});
