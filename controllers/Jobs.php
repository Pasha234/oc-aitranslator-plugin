<?php namespace PalPalych\AiTranslator\Controllers;

use Flash;
use BackendMenu;
use Backend\Facades\Backend;
use Backend\Classes\FormField;
use Backend\Classes\Controller;
use Backend\FormWidgets\RichEditor;
use PalPalych\Aitranslator\Models\Job;
use PalPalych\Aitranslator\Models\Job\JobStatus;
use PalPalych\AiTranslator\Classes\TranslationService;

/**
 * Jobs Back-end Controller
 */
class Jobs extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class,
    ];

    public $listConfig = 'config_list.yaml';

    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('PalPalych.AiTranslator', 'aitranslator', 'jobs');

        $this->addCss('/plugins/palpalych/aitranslator/assets/css/jjsonviewer.css');
        $this->addJs('/plugins/palpalych/aitranslator/assets/js/jjsonviewer.js');
    }

    public function update($recordId = null)
    {
        $model = Job::with('fields')->find($recordId);

        if ($model && $model->fields) {
            $widgets = [];

            foreach ($model->fields as $field) {
                $fieldName = "translation_data[{$field->id}]";

                $formField = new FormField($fieldName, 'Translation');
                $formField->id = 'field-translation-' . $field->id;

                $formField->value = $field->final_value ?? $field->ai_value;

                $config = [
                    'model' => $field,
                    'attribute' => 'final_value',
                    'legacyMode' => true,
                    'size' => 'huge',
                    'toolbarButtons' => 'bold,italic,underline,formatOL,formatUL,insertLink,html',
                ];

                $widget = $this->makeFormWidget(RichEditor::class, $formField, $config);
                $widget->bindToController();

                $widgets[$field->id] = $widget;
            }

            $this->vars['translationWidgets'] = $widgets;
        }

        return $this->asExtension('FormController')->update($recordId);
    }


    public function update_onSave($recordId = null, $context = null)
    {
        $result = $this->asExtension('FormController')->update_onSave($recordId, $context);

        $data = post('translation_data');

        if (is_array($data)) {
            foreach ($data as $id => $content) {
                \PalPalych\AiTranslator\Models\FieldTranslation::where('id', $id)
                    ->update(['final_value' => $content]);
            }
        }

        Flash::success('Translation fields updated successfully.');

        return $result;
    }

    public function onReject($recordId = null)
    {
        $job = Job::findOrFail($recordId);
        $job->status = JobStatus::rejected;
        $job->save();

        Flash::warning('Translation rejected.');

        return redirect()->refresh();
    }

    /**
     * Handle the Approve Button
     */
    public function onApprove($recordId = null)
    {
        $this->update_onSave($recordId);

        $job = Job::findOrFail($recordId);

        try {
            $service = new TranslationService();
            $targetRecord = $service->applyJobToTarget($job);

            Flash::success('Translation approved and applied successfully!');

            return Backend::redirect('palpalych/aitranslator/jobs');

        } catch (\Exception $e) {
            Flash::error('Error applying translation: ' . $e->getMessage());
            return;
        }
    }
}
