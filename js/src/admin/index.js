import app from 'flarum/admin/app';
import Select from 'flarum/common/components/Select';

export { default as extend } from './extend';

const models = {
    openai: {
        name: 'Open AI',
        modelsUrl: 'https://platform.openai.com/docs/models/overview',
        keysUrl: 'https://platform.openai.com/account/api-keys',
        defaultModel: 'gpt-5.4-mini',
    },
    anthropic: {
        name: 'Anthropic',
        modelsUrl: 'https://docs.claude.com/en/docs/about-claude/models/overview',
        keysUrl: 'https://console.anthropic.com/settings/keys',
        defaultModel: 'claude-haiku-4-5'
    },
    openrouter: {
        name: 'OpenRouter',
        modelsUrl: 'https://openrouter.ai/models',
        keysUrl: 'https://openrouter.ai/settings/keys',
        defaultModel: 'openrouter/auto',
    },
    google: {
        name: 'Google (Gemini)',
        modelsUrl: 'https://ai.google.dev/gemini-api/docs/models',
        keysUrl: 'https://aistudio.google.com/api-keys',
        defaultModel: 'gemini-3.1-flash-lite-preview',
    },
};

const modelNames = Object.entries(models).reduce((result, [key, value]) => {
    result[key] = value.name;
    return result;
}, {});


app.initializers.add('michaelbelgium/flarum-ai-autoreply', () => {
    const savedPlatform = app.data.settings['michaelbelgium-ai-autoreply.platform'] || 'openai';
    let selectedModel = models[savedPlatform];

    app.registry
        .for('michaelbelgium-ai-autoreply')
        .registerSetting(function () {
            return m('.Form-group', [
                m('label', app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.platform_label')),
                m('.helpText', app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.platform_help')),
                Select.component({
                    value: this.setting('michaelbelgium-ai-autoreply.platform')(),
                    options: modelNames,
                    onchange: (value) => {
                        selectedModel = models[value];
                        this.setting('michaelbelgium-ai-autoreply.platform')(value);
                    }
                })
            ]);
        })
        .registerSetting(function () {
            return m('.Form-group', [
                m('label', app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.api_key_label')),
                m('.helpText', app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.api_key_help', {
                    a: <a href={selectedModel.keysUrl} target="_blank" rel="noopener" />,
                    platform: selectedModel.name,
                })),
                m('input.FormControl', {
                    type: 'text',
                    bidi: this.setting('michaelbelgium-ai-autoreply.api_key'),
                    placeholder: 'sk-...',
                    required: true,
                }),
            ]);
        })
        .registerSetting(function () {
            return m('.Form-group', [
                m('label', app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.model_label')),
                m('.helpText', app.translator.trans('michaelbelgium-ai-autoreply.admin.settings.model_help', {
                    a: <a href={selectedModel.modelsUrl} target="_blank" rel="noopener" />,
                    platform: selectedModel.name,
                    model: selectedModel.defaultModel,
                })),
                m('input.FormControl', {
                    type: 'text',
                    bidi: this.setting('michaelbelgium-ai-autoreply.model'),
                    placeholder: selectedModel.defaultModel,
                }),
            ]);
        })
});
