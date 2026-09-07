import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Switch from 'flarum/common/components/Switch';
import Button from 'flarum/common/components/Button';

const PREFIX = 'stezkoy-ai-openreply';

// The standard agents that ship with opencode (as shown by GET /agent on a
// stock server). "default" means: let the server pick the agent of the model.
const BUILTIN_AGENT_IDS = ['build', 'plan'];

export default class AIOpenReplySettingsPage extends ExtensionPage {
  oninit(vnode) {
    super.oninit(vnode);

    this.customModel = undefined;
    this.freeModels = [];
    this.modelsUnreachable = false;
    this.loadingModels = false;
  }

  content() {
    return m(
      '.ExtensionPage-settings',
      m('.container', [
        m('.AIOpenReplySettings', [
          m('.AIOpenReplyHero', [
            m('p', app.translator.trans(PREFIX + '.admin.settings.opencode_intro')),
            m('a.AIOpenReplyLink', { href: 'https://opencode.ai/', target: '_blank', rel: 'noopener' }, 'https://opencode.ai/'),
          ]),
          this._group('opencode_url_label', 'opencode_url_help', 'input', 'opencode_url', {
            placeholder: 'http://localhost:4096',
          }),
          this._group('opencode_username_label', 'opencode_username_help', 'input', 'opencode_username', {
            placeholder: 'opencode',
          }),
          this._group('opencode_password_label', 'opencode_password_help', 'input', 'opencode_password', {
            type: 'password',
          }),
          this._agentGroup(),
          this._group('opencode_system_prompt_label', 'opencode_system_prompt_help', 'textarea', 'opencode_system_prompt', {
            rows: 3,
            placeholder: app.translator.trans(PREFIX + '.admin.settings.opencode_system_prompt_placeholder'),
          }),
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

          m('.Form-group', [
            m('label', app.translator.trans(PREFIX + '.admin.settings.retry_label')),
            this._numberGroup('retry_attempts_label', 'retry_attempts_help', 'retry_attempts'),
            this._numberGroup('retry_delay_seconds_label', 'retry_delay_seconds_help', 'retry_delay_seconds'),
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
            className: 'Button',
            loading: this.loadingCount,
            onclick: () => this.checkSessionCount(),
          },
          app.translator.trans(PREFIX + '.admin.settings.session_count_label')
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
        extra.required ? { required: true } : {},
        extra.rows ? { rows: extra.rows } : {}
      )),
      m('p.helpText', app.translator.trans(PREFIX + '.admin.settings.' + helpKey)),
    ]);
  }

  _agentGroup() {
    const current = this.setting(PREFIX + '.opencode_agent')() || '';

    // A previously saved custom agent id is preserved as an extra option so
    // the select never silently misrepresents the stored value. New values
    // can only be the presets below.
    const unknown = current !== '' && !BUILTIN_AGENT_IDS.includes(current);

    return m('.Form-group', [
      m('label', app.translator.trans(PREFIX + '.admin.settings.opencode_agent_label')),
      m(
        'select.FormControl',
        {
          value: current,
          onchange: (e) => {
            this.setting(PREFIX + '.opencode_agent')(e.target.value);
            m.redraw();
          },
        },
        [
          m('option', { value: '' }, app.translator.trans(PREFIX + '.admin.settings.opencode_agent_default_option')),
          ...BUILTIN_AGENT_IDS.map((id) => m('option', { value: id }, id)),
          ...(unknown ? [m('option', { value: current }, current)] : []),
        ]
      ),
      m('p.helpText', app.translator.trans(PREFIX + '.admin.settings.opencode_agent_help')),
    ]);
  }

  _modelGroup() {
    let current = this.setting(PREFIX + '.model')() || '';

    // A legacy build accidentally persisted the "__custom__" marker as the
    // model value; treat it as an empty custom input.
    if (current === '__custom__') current = '';

    return m('.Form-group', [
      m('label', app.translator.trans(PREFIX + '.admin.settings.model_label')),
      m('input.FormControl.AIOpenReplyCustomModel', {
        type: 'text',
        placeholder: 'provider/model',
        value: current,
        oninput: (e) => {
          this.setting(PREFIX + '.model')(e.target.value);
        },
      }),
      m('p.helpText', app.translator.trans(PREFIX + '.admin.settings.model_help')),
      Button.component(
        {
          className: 'Button',
          loading: this.loadingModels,
          onclick: () => this._loadModels(),
        },
        app.translator.trans(PREFIX + '.admin.settings.model_load_button')
      ),
      this._modelListNote(),
    ]);
  }

  _modelListNote() {
    if (this.loadingModels) {
      return m('p.helpText', app.translator.trans(PREFIX + '.admin.settings.model_loading'));
    }

    if (this.modelsUnreachable) {
      return m('p.helpText', app.translator.trans(PREFIX + '.admin.settings.model_load_fail'));
    }

    if (this.freeModels.length === 0) {
      return m('p.helpText', app.translator.trans(PREFIX + '.admin.settings.model_no_free'));
    }

    return m('.AIOpenReplyModelList', [
      m('p.helpText', app.translator.trans(PREFIX + '.admin.settings.model_list_hint')),
      m(
        'ul',
        this.freeModels.map((model) =>
          m(
            'li',
            m(
              'button.Button',
              {
                title: model.id,
                onclick: () => {
                  this.setting(PREFIX + '.model')(model.id);
                  m.redraw();
                },
              },
              [
                m('code', model.id),
                model.name && model.name !== model.id ? m('span', model.name) : null,
              ]
            )
          )
        )
      ),
    ]);
  }

  _loadModels() {
    this.loadingModels = true;
    m.redraw();

    app.request({
      url: app.forum.attribute('apiUrl') + '/ai-openreply/models',
      method: 'GET',
      errorHandler: () => {},
    })
      .then((data) => {
        this.freeModels = Array.isArray(data.models)
          ? data.models.filter((model) => model && typeof model.id === 'string')
          : [];
        this.modelsUnreachable = data.reachable === false;
      })
      .catch(() => {
        this.freeModels = [];
        this.modelsUnreachable = true;
      })
      .then(() => {
        this.loadingModels = false;
        m.redraw();
      });
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
    // When flarum-tags is enabled we use its official custom setting component
    // ("flarum-tags.select-tags"). It loads the FULL tag list (all levels) via
    // app.tagList.load(['parent']) and renders the standard tag-selection modal,
    // unlike app.store.all('tags') which only holds whatever happened to be
    // loaded into the store in the current admin session.
    if (app.data.extensions && app.data.extensions['flarum-tags']) {
      return this.buildSettingComponent({
        type: 'flarum-tags.select-tags',
        setting: PREFIX + '.enabled-tags',
        label: app.translator.trans(PREFIX + '.admin.settings.enabled_tags_label'),
        help: app.translator.trans(PREFIX + '.admin.settings.enabled_tags_help'),
      });
    }

    // Fallback: flarum-tags is not installed/enabled.
    return m('.Form-group', [
      m('label', app.translator.trans(PREFIX + '.admin.settings.enabled_tags_label')),
      m('p.helpText', app.translator.trans(PREFIX + '.admin.settings.enabled_tags_empty')),
    ]);
  }

  _default(setting) {
    const defaults = {
      opencode_url: 'http://localhost:4096',
      opencode_username: 'opencode',
      opencode_password: '',
      opencode_agent: '',
      opencode_system_prompt: '',
      model: '',
      user_prompt: '',
      user_prompt_badge_text: 'Assistant',
      enable_on_discussion_started: '1',
      max_active_sessions: '10',
      max_messages_per_session: '15',
      session_ttl_days: '3',
      retry_attempts: '1',
      retry_delay_seconds: '1',
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
      .then(() => {
        this.statusMessage = app.translator.trans(PREFIX + '.admin.settings.sessions_closed');
      })
      .catch(() => {
        this.statusMessage = app.translator.trans(PREFIX + '.admin.settings.sessions_close_fail');
      })
      .then(() => {
        this.loadingCloseAll = false;
        m.redraw();
      });
  }

  checkSessionCount() {
    this.loadingCount = true;
    m.redraw();

    app.request({
      url: app.forum.attribute('apiUrl') + '/ai-openreply/count',
      method: 'POST',
      errorHandler: () => {},
    })
      .then((data) => {
        this.statusMessage = app.translator.trans(PREFIX + '.admin.settings.session_count_result', {
          total: data.total ?? 0,
          extension: data.extension ?? 0,
        });
      })
      .catch(() => {
        this.statusMessage = app.translator.trans(PREFIX + '.admin.settings.session_count_fail');
      })
      .then(() => {
        this.loadingCount = false;
        m.redraw();
      });
  }
}
