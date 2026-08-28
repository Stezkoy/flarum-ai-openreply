import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Switch from 'flarum/common/components/Switch';
import Button from 'flarum/common/components/Button';

const PREFIX = 'stezkoy-ai-openreply';

const FREE_MODEL_IDS = [
  'opencode/big-pickle',
  'opencode/mimo-v2.5-free',
  'opencode/hy3-free',
  'opencode/nemotron-3-ultra-free',
  'opencode/nemotron-3.5-lightning-free',
  'opencode/muse-spark-1.2-contributor-free',
];

const FREE_MODEL_LABELS = {
  'opencode/big-pickle': 'Big Pickle',
  'opencode/mimo-v2.5-free': 'MiMo-V2.5 Free',
  'opencode/hy3-free': 'Hy3 Free',
  'opencode/nemotron-3-ultra-free': 'Nemotron 3 Ultra Free',
  'opencode/nemotron-3.5-lightning-free': 'Nemotron 3.5 Lightning Free',
  'opencode/muse-spark-1.2-contributor-free': 'Muse Spark 1.2 Contributor Free',
};

export default class AIOpenReplySettingsPage extends ExtensionPage {
  content() {
    return m(
      '.ExtensionPage-settings',
      m('.container', [
        m('.AIOpenReplySettings', [
          this._group('opencode_url_label', 'opencode_url_help', 'input', 'opencode_url', {
            placeholder: 'http://localhost:4096',
          }),
          this._group('opencode_username_label', 'opencode_username_help', 'input', 'opencode_username', {
            placeholder: 'opencode',
          }),
          this._group('opencode_password_label', 'opencode_password_help', 'input', 'opencode_password', {
            type: 'password',
          }),
          this._group('opencode_agent_label', 'opencode_agent_help', 'input', 'opencode_agent'),
          this._modelGroup(),
          this._actionsGroup(),
          this._group('user_prompt_label', 'user_prompt_help', 'input', 'user_prompt', {
            type: 'number',
            required: true,
          }),
          this._group('user_prompt_badge_label', 'user_prompt_badge_help', 'input', 'user_prompt_badge_text'),
          this._switchGroup(),

          m('.Form-group', [
            m('label', app.translator.trans(PREFIX + '.admin.settings.limits_label')),
            this._numberGroup('max_active_sessions_label', 'max_active_sessions_help', 'max_active_sessions'),
            this._numberGroup('max_messages_per_session_label', 'max_messages_per_session_help', 'max_messages_per_session'),
            this._numberGroup('session_ttl_days_label', 'session_ttl_days_help', 'session_ttl_days'),
          ]),

          this._tagsGroup(),

          m('.Form-group.Form-controls', this.submitButton()),
        ]),
      ])
    );
  }

  _actionsGroup() {
    return m('.Form-group', [
      m('label', app.translator.trans(PREFIX + '.admin.settings.actions_label')),
      m('.ButtonGroup', [
        Button.component(
          {
            className: 'Button',
            loading: this.loadingHealth,
            onclick: () => this.checkConnection(),
          },
          app.translator.trans(PREFIX + '.admin.settings.check_connection_label')
        ),
        Button.component(
          {
            className: 'Button Button--danger',
            loading: this.loadingCloseAll,
            onclick: () => this.closeAll(),
          },
          app.translator.trans(PREFIX + '.admin.settings.close_all_sessions_label')
        ),
      ]),
      m('p.helpText', this.statusMessage || app.translator.trans(PREFIX + '.admin.settings.actions_help')),
    ]);
  }

  _group(labelKey, helpKey, inputType, setting, extra = {}) {
    return m('.Form-group', [
      m('label', app.translator.trans(PREFIX + '.admin.settings.' + labelKey)),
      m(inputType + '.FormControl', Object.assign(
        {
          bidi: this.setting(PREFIX + '.' + setting, this._default(setting)),
          placeholder: extra.placeholder,
        },
        extra.type ? { type: extra.type } : {},
        extra.required ? { required: true } : {}
      )),
      m('p.helpText', app.translator.trans(PREFIX + '.admin.settings.' + helpKey)),
    ]);
  }

  _modelGroup() {
    const current = this.setting(PREFIX + '.model')() || '';
    const isPreset = FREE_MODEL_IDS.includes(current);
    const isDefault = current === '';

    const selectValue = isPreset ? current : isDefault ? '' : '__custom__';

    return m('.Form-group', [
      m('label', app.translator.trans(PREFIX + '.admin.settings.model_label')),
      m(
        'select.FormControl',
        {
          value: selectValue,
          onchange: (e) => {
            this.setting(PREFIX + '.model')(e.target.value);
            m.redraw();
          },
        },
        [
          m('option', { value: '' }, app.translator.trans(PREFIX + '.admin.settings.model_default_option')),
          ...FREE_MODEL_IDS.map((id) => m('option', { value: id }, FREE_MODEL_LABELS[id])),
          m('option', { value: '__custom__' }, app.translator.trans(PREFIX + '.admin.settings.model_custom_option')),
        ]
      ),
      isPreset || isDefault
        ? m('p.helpText', app.translator.trans(PREFIX + '.admin.settings.model_help'))
        : m('p.helpText', app.translator.trans(PREFIX + '.admin.settings.model_custom_help')),
      m('input.FormControl.AIOpenReplyCustomModel', {
        type: 'text',
        placeholder: 'provider/model',
        style: selectValue === '__custom__' ? '' : 'display: none;',
        bidi: this.setting(PREFIX + '.model', ''),
      }),
    ]);
  }

  _numberGroup(labelKey, helpKey, setting) {
    return m('.Form-group', [
      m('label', app.translator.trans(PREFIX + '.admin.settings.' + labelKey)),
      m('input.FormControl', {
        type: 'number',
        bidi: this.setting(PREFIX + '.' + setting, this._default(setting)),
      }),
      m('p.helpText', app.translator.trans(PREFIX + '.admin.settings.' + helpKey)),
    ]);
  }

  _switchGroup() {
    return m('.Form-group', [
      m(
        Switch,
        {
          state: this.setting(PREFIX + '.enable_on_discussion_started', '1')() === '1',
          onchange: (value) => {
            this.setting(PREFIX + '.enable_on_discussion_started')(value ? '1' : '');
          },
        },
        app.translator.trans(PREFIX + '.admin.settings.enable_on_discussion_started_label')
      ),
      m('p.helpText', app.translator.trans(PREFIX + '.admin.settings.enable_on_discussion_started_help')),
    ]);
  }

  _tagsGroup() {
    const selectedIds = this._selectedTagIds();
    const allTags = app.store.all('tags');

    return m('.Form-group', [
      m('label', app.translator.trans(PREFIX + '.admin.settings.enabled_tags_label')),
      m('.AIOpenReplyTagList', [
        allTags.length === 0
          ? m('p.helpText', app.translator.trans(PREFIX + '.admin.settings.enabled_tags_empty'))
          : allTags.map((tag) => {
              const id = String(tag.id());
              const checked = selectedIds.includes(id);
              return m(
                'button.AIOpenReplyTag',
                {
                  type: 'button',
                  className: checked ? 'selected' : '',
                  onclick: () => this._toggleTag(id),
                },
                tag.name()
              );
            }),
      ]),
      m('p.helpText', app.translator.trans(PREFIX + '.admin.settings.enabled_tags_help')),
    ]);
  }

  _toggleTag(id) {
    const selected = this._selectedTagIds();
    const index = selected.indexOf(id);
    if (index === -1) {
      selected.push(id);
    } else {
      selected.splice(index, 1);
    }
    this.setting(PREFIX + '.enabled-tags')(JSON.stringify(selected));
    m.redraw();
  }

  _selectedTagIds() {
    let selected = [];
    try {
      selected = JSON.parse(this.setting(PREFIX + '.enabled-tags', '[]')() || '[]');
    } catch (e) {
      selected = [];
    }
    return selected.map(String);
  }

  _default(setting) {
    const defaults = {
      opencode_url: 'http://localhost:4096',
      opencode_username: 'opencode',
      opencode_password: '',
      opencode_agent: '',
      model: '',
      user_prompt: '',
      user_prompt_badge_text: 'Assistant',
      enable_on_discussion_started: '1',
      max_active_sessions: '10',
      max_messages_per_session: '15',
      session_ttl_days: '3',
    };

    return defaults[setting] || '';
  }

  checkConnection() {
    this.loadingHealth = true;
    this.statusMessage = null;
    m.redraw();

    app.request({
      url: app.forum.attribute('apiUrl') + '/ai-openreply/health',
      method: 'POST',
      errorHandler: () => {},
    })
      .then((data) => {
        this.statusMessage = this._connectionMessage(data);
      })
      .catch(() => {
        this.statusMessage = app.translator.trans(PREFIX + '.admin.settings.connection_fail');
      })
      .then(() => {
        this.loadingHealth = false;
        m.redraw();
      });
  }

  _connectionMessage(data) {
    if (!data.healthy) {
      return app.translator.trans(PREFIX + '.admin.settings.connection_fail');
    }

    const model = (data.model || '').trim();

    if (model !== '') {
      return app.translator.trans(PREFIX + '.admin.settings.connection_success_with_model', { model });
    }

    if (data.serverDefaultModel && data.serverDefaultModel.model) {
      return app.translator.trans(PREFIX + '.admin.settings.connection_success_default_model', {
        model: data.serverDefaultModel.provider + '/' + data.serverDefaultModel.model,
      });
    }

    return app.translator.trans(PREFIX + '.admin.settings.connection_success_no_model');
  }

  closeAll() {
    this.loadingCloseAll = true;
    m.redraw();

    app.request({
      url: app.forum.attribute('apiUrl') + '/ai-openreply/close-all',
      method: 'POST',
      errorHandler: () => {},
    })
      .then((data) => {
        this.statusMessage = app.translator.trans(PREFIX + '.admin.settings.sessions_closed', {
          count: data.closed,
        });
      })
      .catch(() => {
        this.statusMessage = app.translator.trans(PREFIX + '.admin.settings.sessions_close_fail');
      })
      .then(() => {
        this.loadingCloseAll = false;
        m.redraw();
      });
  }
}
