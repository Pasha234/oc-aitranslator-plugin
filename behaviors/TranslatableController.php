<?php

namespace PalPalych\AiTranslator\Behaviors;

use DB;
use App;
use Backend;
use Exception;
use October\Rain\Database\Model;
use PalPalych\AiTranslator\Models\Job;
use Backend\Classes\ControllerBehavior;
use PalPalych\AiTranslator\Classes\JobManager;
use PalPalych\AiTranslator\Models\FieldTranslation;
use PalPalych\AiTranslator\Classes\TranslationService;
use PalPalych\AiTranslator\Classes\TranslationFieldFormatter;

class TranslatableController extends ControllerBehavior
{
    /**
     * @var \Backend\Classes\Controller controller reference
     */
    protected $controller;

    /**
     * @inheritDoc
     */
    protected $requiredProperties = ['formConfig'];

    /**
     * @var array requiredConfig that must exist when applying the primary config file
     */
    protected $requiredConfig = ['modelClass', 'form'];

    /**
     * @var Model model used by the form
     */
    protected $model;

    /**
     * __construct the behavior
     * @param Backend\Classes\Controller $controller
     */
    public function __construct($controller)
    {
        parent::__construct($controller);

        // Build configuration
        $this->setConfig($controller->formConfig, $this->requiredConfig);

        $this->addJs('/modules/backend/assets/foundation/controls/popup/popup.js');
        $this->addJs('/plugins/palpalych/aitranslator/assets/js/translator.js');
    }

    /**
     * createModel internal method used to prepare the form model object.
     *
     * @return \October\Rain\Database\Model
     */
    protected function createModel()
    {
        return App::make($this->config->modelClass);
    }


    public function update_onInitAiTranslation($recordId = null)
    {
        $targetSiteId = post('target_site_id');
        $targetLocale = post('target_locale');
        $modelClass = $this->config->modelClass;

        if (!$modelClass || !is_string($modelClass)) {
            throw new Exception('Model class is not set');
        }

        $model = $modelClass::find($recordId);

        if (!$model) {
            throw new Exception('Model not found');
        }

        $jobManager = new JobManager();
        $job = $jobManager->createJob($model, $targetLocale);

        $job->target_site_id = $targetSiteId;
        $job->save();

        $service = new TranslationService();
        $service->processJob($job->id);
        $formatter = new TranslationFieldFormatter();

        return $this->makePartial('$/palpalych/aitranslator/assets/html/_review_popup.htm', [
            'job' => $job,
            'editorOptions' => config('editor.html_defaults.editor_options'),
            'fieldFormatter' => $formatter,
        ]);
    }

    public function update_onApplyTranslation($recordId = null)
    {
        $fields = post('fields');
        $jobId = post('job_id');
        $targetRecord = null;

        $job = Job::findOrFail($jobId);

        DB::transaction(function() use ($fields, $jobId, &$targetRecord) {
            $formatter = new TranslationFieldFormatter();
            $fieldTranslations = FieldTranslation::where('job_id', $jobId)
                ->whereIn('id', array_keys((array) $fields))
                ->get()
                ->keyBy('id');

            foreach ((array) $fields as $fieldId => $translation) {
                $field = $fieldTranslations->get($fieldId);
                if (!$field) {
                    continue;
                }

                $field->final_value = $formatter->sanitize($field->field_name, $translation);
                $field->save();
            }

            $service = new TranslationService();
            $targetRecord = $service->applyTranslation($jobId);
        });

        if ($targetRecord) {
            $redirectUrl = Backend::url(
                $this->config->defaultRedirect . "/update/{$targetRecord->id}?_site_id={$job->target_site_id}"
            );
            return redirect($redirectUrl);
        }
    }

    public function renderAiTranslateButton()
    {
        return $this->controller->makePartial('$/palpalych/aitranslator/behaviors/translatablecontroller/_toolbar_button.htm');
    }
}
