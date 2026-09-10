import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import PostUser from 'flarum/forum/components/PostUser';

app.initializers.add('stezkoy/flarum-ai-openreply', () => {
  extend(PostUser.prototype, 'view', function (view) {
    const user = this.attrs.post.user();

    if (!user || app.forum.attribute('aiAssistantUserId') !== user.id()) return;
    if (!app.forum.attribute('aiAssistantBadgeEnabled')) return;

    const badgeText = String(app.forum.attribute('aiAssistantBadgeText') || '').trim();

    // An enabled badge with empty text would render as a bare strip — skip it.
    if (!badgeText) return;

    view.children.push(
      <div className="UserPromo-badge">
        <div className="badge">{badgeText}</div>
      </div>
    );
  });
});
