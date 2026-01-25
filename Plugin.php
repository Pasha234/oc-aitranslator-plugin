<?php namespace PalPalych\AiTranslator;

use Backend;
use System\Classes\PluginBase;
use PalPalych\AiTranslator\Models\Settings;
use PalPalych\AiTranslator\Console\AutoTranslate;

/**
 * Plugin Information File
 *
 * @link https://docs.octobercms.com/3.x/extend/system/plugins.html
 */
class Plugin extends PluginBase
{
    /**
     * pluginDetails about this plugin.
     */
    public function pluginDetails()
    {
        return [
            'name' => 'AiTranslator',
            'description' => 'No description provided yet...',
            'author' => 'PalPalych',
            'icon' => 'icon-leaf'
        ];
    }

    /**
     * register method, called when the plugin is first registered.
     */
    public function register()
    {
        $this->registerConsoleCommand('aitranslator:batch', AutoTranslate::class);
    }

    /**
     * boot method, called right before the request route.
     */
    public function boot()
    {
        //
    }

    /**
     * registerComponents used by the frontend.
     */
    public function registerComponents()
    {
        return []; // Remove this line to activate

        return [
            'PalPalych\AiTranslator\Components\MyComponent' => 'myComponent',
        ];
    }

    /**
     * registerPermissions used by the backend.
     */
    public function registerPermissions()
    {
        return [
            'palpalych.aitranslator.manage_settings' => [
                'tab' => 'AiTranslator',
                'label' => 'Manage settings'
            ],
        ];
    }

    /**
     * registerNavigation used by the backend.
     */
    public function registerNavigation()
    {
        return [
            'aitranslator' => [
                'label' => 'ИИ Переводы',
                'url' => Backend::url('palpalych/aitranslator/jobs'),
                'icon' => 'icon-language',
                'permissions' => ['palpalych.aitranslator.*'],
                'order' => 500,
            ],
        ];
    }

    /**
     * registerSettings
     */
    public function registerSettings()
    {
        return [
            'settings' => [
                'label' => "Переводы",
                'description' => "Управление переводами",
                'category' => 'Переводы',
                'icon' => 'icon-language',
                'class' => Settings::class,
                'order' => 500,
                'permissions' => ['palpalych.aitranslator.manage_settings']
            ],
            'prompts' => [
                'label'       => 'Промпты',
                'description' => 'Управление промптами для переводов.',
                'category'    => 'Переводы',
                'icon'        => 'icon-file-text-o',
                'url'         => Backend::url('palpalych/aitranslator/prompts'),
                'order'       => 600,
                'keywords'    => 'aitranslator prompts',
                'permissions' => ['palpalych.aitranslator.manage_settings']
            ],
        ];
    }
}
