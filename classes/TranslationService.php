<?php

namespace PalPalych\AiTranslator\Classes;

use DB;
use Model;
use Exception;
use October\Rain\Support\Str;
use PalPalych\AiTranslator\Models\Job;
use October\Rain\Database\Traits\Multisite;
use PalPalych\AiTranslator\Models\Settings;
use PalPalych\AiTranslator\Models\Job\JobStatus;
use PalPalych\AiTranslator\Classes\Dto\ContentDto;
use PalPalych\AiTranslator\Classes\Dto\TranslationRequestDto;
use PalPalych\AiTranslator\Classes\Exceptions\PrimarySlugUnavailableException;
use System\Models\SiteDefinition;

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

        $translatedFieldNames = [];
        foreach ($job->fields as $field) {
            $val = $field->final_value ?? $field->ai_value;

            $targetRecord->setAttribute($field->field_name, $val);
            $translatedFieldNames[] = $field->field_name;
        }

        $this->refreshSlugForTranslation($targetRecord, $sourceModel, $job->target_site_id, $translatedFieldNames);

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

        $translatedFieldNames = [];
        foreach ($job->fields as $field) {
            $value = $field->final_value ?? $field->ai_value;
            $targetRecord->{$field->field_name} = $value;
            $translatedFieldNames[] = $field->field_name;
        }

        $this->refreshSlugForTranslation($targetRecord, $sourceModel, $targetSiteId, $translatedFieldNames);

        \Site::withContext($targetSiteId, function() use ($targetRecord) {
            $targetRecord->save();
        });

        $job->status = JobStatus::applied;
        $job->save();

        return $targetRecord;
    }

    protected function refreshSlugForTranslation(Model $targetRecord, Model $sourceModel, $targetSiteId, array $translatedFieldNames): void
    {
        if (!$this->modelHasAttribute($targetRecord, 'slug')) {
            return;
        }

        $slugSourceFields = $this->getSlugSourceFields($targetRecord);
        if (!$slugSourceFields) {
            return;
        }

        $translatedFieldNames = array_flip($translatedFieldNames);
        $sourceValues = [];

        foreach ($slugSourceFields as $fieldName) {
            if (!isset($translatedFieldNames[$fieldName])) {
                continue;
            }

            $value = $targetRecord->getAttribute($fieldName);
            if ($value !== null && trim((string) $value) !== '') {
                $sourceValues[] = $value;
            }
        }

        if (!$sourceValues) {
            return;
        }

        $targetSite = SiteDefinition::find($targetSiteId);
        $targetLocale = $targetSite?->locale ?: 'en';
        $slug = Str::slug(
            mb_substr(implode(' ', $sourceValues), 0, 175),
            '-',
            $targetLocale
        );

        if ($slug === '') {
            $slug = $this->getPrimarySiteSlug($sourceModel, $targetSiteId);
        }

        $targetRecord->slug = $this->makeUniqueSlug($sourceModel, $targetRecord, $targetSiteId, $slug);
    }

    protected function getPrimarySiteSlug(Model $sourceModel, $targetSiteId): string
    {
        $primarySite = SiteDefinition::where('is_primary', true)->first();

        if (!$primarySite) {
            throw new PrimarySlugUnavailableException(
                'Cannot generate a fallback slug because the primary site is not configured.'
            );
        }

        if ((int) $primarySite->id === (int) $targetSiteId) {
            throw new PrimarySlugUnavailableException(
                'Cannot generate a slug for the primary site from its own untranslated record.'
            );
        }

        $primaryRecord = null;
        \Site::withGlobalContext(function () use ($sourceModel, $primarySite, &$primaryRecord) {
            $primaryRecord = $sourceModel->findForSite($primarySite->id);
        });

        $primarySlug = trim((string) $primaryRecord?->getAttribute('slug'));
        if ($primarySlug === '') {
            throw new PrimarySlugUnavailableException(
                "The primary site translation for this record does not have a slug yet."
            );
        }

        return mb_substr($primarySlug, 0, 175);
    }

    protected function getSlugSourceFields(Model $targetRecord): array
    {
        return array_values(array_filter(['title', 'name'], function ($field) use ($targetRecord) {
            return $this->modelHasAttribute($targetRecord, $field);
        }));
    }

    protected function modelHasAttribute(Model $model, string $attribute): bool
    {
        return array_key_exists($attribute, $model->getAttributes()) || $model->getAttribute($attribute) !== null;
    }

    protected function makeUniqueSlug(Model $sourceModel, Model $targetRecord, $targetSiteId, string $slug): string
    {
        $baseSlug = $slug;
        $counter = 1;

        while ($this->slugExists($sourceModel, $targetRecord, $targetSiteId, $slug)) {
            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }

        return $slug;
    }

    protected function slugExists(Model $sourceModel, Model $targetRecord, $targetSiteId, string $slug): bool
    {
        $query = $sourceModel::withoutGlobalScopes()
            ->where('slug', $slug)
            ->where($targetRecord->getKeyName(), '!=', $targetRecord->getKey());

        if (\Schema::hasColumn($targetRecord->getTable(), 'site_id')) {
            $query->where('site_id', $targetSiteId);
        }

        return $query->exists();
    }
}
