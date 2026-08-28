import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import PostUser from 'flarum/forum/components/PostUser';

app.initializers.add('stezkoy/flarum-ai-openreply', () => {
  extend(PostUser.prototype, 'view', function (view) {
    const user = this.attrs.post.user();

    if (!user || app.forum.attribute('aiAssistantUserId') !== user.id()) return;

    view.children.push(
      <div className="UserPromo-badge">
        <div className="badge">{app.forum.attribute('aiAssistantBadgeText')}</div>
      </div>
    );
  });
});
