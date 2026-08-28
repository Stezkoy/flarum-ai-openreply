import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import FormGroup from 'flarum/common/components/FormGroup';
import ModelSelectSettingComponent from './components/ModelSelectSettingComponent';

export { default as extend } from './extend';

app.initializers.add('stezkoy/flarum-ai-openreply', () => {
  extend(FormGroup.prototype, 'customFieldComponents', function (items) {
    items.add('stezkoy-ai-openreply.model-select', (attrs) => m(ModelSelectSettingComponent, attrs));
  });
});
