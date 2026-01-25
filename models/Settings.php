<?php

namespace PalPalych\AiTranslator\Models;

use PalPalych\AiTranslator\Models\Prompt;
use System\Models\SettingModel;

/**
 * @property string $anthropic_api_key
 * @property string $default_driver
 * @property int $default_prompt_id
 */
class Settings extends SettingModel
{
    /**
     * @var string settingsCode is a unique code for this object
     */
    public $settingsCode = 'ai_translator_settings';

    /**
     * @var mixed settingsFields definition file
     */
    public $settingsFields = 'fields.yaml';

    /**
     * initSettingsData
     */
    public function initSettingsData()
    {
        $this->anthropic_api_key = config('palpalych.aitranslator::anthropic_api_key');
        $this->default_driver = config('palpalych.aitranslator::default_driver');
        $this->default_prompt_id = config('palpalych.aitranslator::default_prompt_id');
    }

    /**
     * @return array
     */
    public function getDefaultDriverOptions(): array
    {
        $drivers = config('palpalych.aitranslator::drivers');

        // Returns an array of ['driver_key' => 'Driver Name']
        return is_array($drivers) ? $drivers : [];
    }

    /**
     * @return array
     */
    public function getDefaultPromptOptions(): array
    {
        return Prompt::all()->pluck('name', 'id')->toArray();
    }
}
