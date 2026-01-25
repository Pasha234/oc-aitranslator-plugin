<?php

namespace PalPalych\AiTranslator\Classes;

use DB;
use Model;
use Exception;
use PalPalych\AiTranslator\Models\Job;
use October\Rain\Database\Traits\Multisite;
use PalPalych\AiTranslator\Models\Settings;
use PalPalych\Aitranslator\Models\Job\JobStatus;
use PalPalych\AiTranslator\Classes\Dto\ContentDto;
use PalPalych\AiTranslator\Classes\Dto\TranslationRequestDto;

class TranslationService
{
    /**
     * Process a Job ID: Fetch DB data -> Call AI -> Save DB data
     */
    public function processJob(int $jobId)
    {
        /** @var Job $job */
        $job = Job::with(['fields', 'prompt'])->find($jobId);

        if (!$job) {
            throw new Exception('Job not found');
        }

        $job->status = JobStatus::processing;
        $job->save();

        try {
            $dataPayload = $job->fields->pluck('original_value', 'field_name')->toArray();

            $customInstructions = $job->prompt ? $job->prompt->system_instruction : '';

            $driverName = Settings::get('default_driver');

            $driver = LlmManager::driver($driverName);

            $translatedData = $driver->translate(
                new TranslationRequestDto(
                    new ContentDto($dataPayload),
                    $job->source_locale,
                    $job->target_locale,
                    $customInstructions
                )
            );

            $job->driver = $driverName;
            $job->driver_response = $translatedData->responseString;

            DB::transaction(function() use ($job, $translatedData) {
                foreach ($job->fields as $field) {
                    $key = $field->field_name;

                    if (isset($translatedData->translatedFields[$key])) {
                        $value = $translatedData->translatedFields[$key];
                        $field->ai_value = $value;
                        $field->final_value = $value;
                        $field->save();
                    }
                }

                $job->status = JobStatus::review;
                $job->save();
            });

        } catch (\Exception $e) {
            $job->status = JobStatus::failed;
            $job->error_message = $e->getMessage();
            $job->save();
            throw $e;
        }
    }

    public function applyTranslation($jobId)
    {
        /** @var ?Job */
        $job = Job::with('fields')->find($jobId);

        if (!$job) {
            throw new Exception('Job is not found');
        }

        $sourceModel = $job->translatable;

        $targetRecord = $this->getOrInitTargetRecord($sourceModel, $job->target_site_id);

        foreach ($job->fields as $field) {
            $val = $field->final_value ?? $field->ai_value;

            $targetRecord->setAttribute($field->field_name, $val);
        }

        \Site::withGlobalContext(function () use (&$targetRecord) {
            $targetRecord->save();
        });

        $job->status = JobStatus::applied;
        $job->save();

        return $targetRecord;
    }

    public function getOrInitTargetRecord($sourceModel, $targetSiteId)
    {
        if (!in_array(Multisite::class, class_uses_recursive($sourceModel))) {
            throw new Exception('This model does not support Multisite.');
        }

        $targetRecord = null;

        \Site::withGlobalContext(function () use ($sourceModel, $targetSiteId, &$targetRecord) {
            /** @var \October\Rain\Database\Model|\October\Rain\Database\Traits\Multisite $sourceModel */
            $targetRecord = $sourceModel->findOrCreateForSite($targetSiteId);
            $targetRecord->save();
        });

        if (!$targetRecord instanceof Model) {
            throw new Exception('Unexpected error');
        }

        return $targetRecord;
    }

    public function applyJobToTarget(Job $job)
    {
        if (!$job->translatable) {
            throw new Exception("Original record not found (it may have been deleted).");
        }

        $sourceModel = $job->translatable;
        $targetSiteId = $job->target_site_id;

        $usesMultisite = in_array(Multisite::class, class_uses_recursive($sourceModel));

        if (!$usesMultisite) {
            throw new Exception("The model " . get_class($sourceModel) . " does not use the Multisite trait.");
        }

        $targetRecord = null;
        \Site::withGlobalContext(function() use ($sourceModel, $targetSiteId, &$targetRecord) {
            $targetRecord = $sourceModel->findOrCreateForSite($targetSiteId);
        });

        if (!$targetRecord) {
            throw new Exception("Could not create target record for Site ID: $targetSiteId");
        }

        foreach ($job->fields as $field) {
            $value = $field->final_value ?? $field->ai_value;
            $targetRecord->{$field->field_name} = $value;
        }

        if (in_array(\October\Rain\Database\Traits\Sluggable::class, class_uses_recursive($targetRecord)) && isset($targetRecord->slug)) {
            $slugExists = $sourceModel::withoutGlobalScopes()
                ->where('site_id', $targetSiteId)
                ->where('slug', $targetRecord->slug)
                ->where('id', '!=', $targetRecord->id)
                ->exists();

            if ($slugExists) {
                $targetRecord->slug = $targetRecord->slug . '-' . $targetSiteId;
            }
        }

        \Site::withContext($targetSiteId, function() use ($targetRecord) {
            $targetRecord->save();
        });

        $job->status = JobStatus::applied;
        $job->save();

        return $targetRecord;
    }
}
