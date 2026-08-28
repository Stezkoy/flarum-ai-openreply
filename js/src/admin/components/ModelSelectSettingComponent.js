import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Select from 'flarum/common/components/Select';

export default class ModelSelectSettingComponent extends Component {
  view() {
    const current = this.attrs.bidi() || '';

    const freeModels = (app.data.settings['stezkoy-ai-openreply.free_models'] || '')
      .split('\n')
      .map((line) => line.trim())
      .filter(Boolean);

    const options = {
      '': app.translator.trans('stezkoy-ai-openreply.admin.settings.model_default_option'),
    };

    [...new Set([...freeModels, current].filter(Boolean))].forEach((model) => {
      options[model] = model;
    });

    return m('div.Form-group', [
      m('label', this.attrs.label),
      this.attrs.help && m('p.helpText', this.attrs.help),
      m(Select, {
        value: current,
        options,
        onchange: (value) => this.attrs.bidi(value),
      }),
    ]);
  }
}
