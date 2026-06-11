<?php

namespace PalPalych\AiTranslator\Classes;

use Exception;
use October\Rain\Database\Model;
use PalPalych\AiTranslator\Behaviors\AiTranslatableModel;
use PalPalych\AiTranslator\Models\FieldTranslation;
use PalPalych\AiTranslator\Models\Job;
use PalPalych\Aitranslator\Models\Job\JobStatus;
use PalPalych\AiTranslator\Models\Settings;
use PalPalych\AiTranslator\Classes\Jobs\ProcessTranslationJob;

class JobManager
{
    public function createJob(Model $model, $targetLocale, $promptId = null)
    {
        if (!in_array(AiTranslatableModel::class, $model->implement)) {
            throw new Exception('Model does not have AiTranslatableModel behavior');
        }
        $fieldsToTranslate = $model->getAiTranslatableFields();

        if (empty($fieldsToTranslate)) {
            throw new Exception("No translatable fields configured for this model.");
        }

        if (!$promptId) {
            $promptId = Settings::get('default_prompt_id');
        }

        $job = new Job();
        $job->translatable_type = get_class($model);
        $job->translatable_id = $model->id;
        $job->source_locale = \App::getLocale();
        $job->target_locale = $targetLocale;
        $job->prompt_id = $promptId;
        $job->status = JobStatus::pending;
        $job->save();

        foreach ($fieldsToTranslate as $fieldName) {
            if (!$model->attributes[$fieldName]) continue;

            $field = new FieldTranslation();
            $field->job_id = $job->id;
            $field->field_name = $fieldName;

            $field->original_value = $model->getAttribute($fieldName);

            $field->save();
        }

        return $job;
    }

    public function dispatchJob(Job $job, bool $autoPublish = false): void
    {
        ProcessTranslationJob::dispatch($job->id, $autoPublish);
    }
}
