<?php

namespace PalPalych\AiTranslator\Classes\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PalPalych\AiTranslator\Classes\TranslationService;
use PalPalych\AiTranslator\Classes\Contracts\PublishesAiTranslations;
use PalPalych\AiTranslator\Models\Job;
use PalPalych\AiTranslator\Models\Job\JobStatus;
use System\Models\SiteDefinition;

class ProcessTranslationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public int $jobId,
        public bool $autoPublish = false
    ) {
        $this->onQueue('aitranslator');
    }

    public function handle(TranslationService $service): void
    {
        try {
            $job = $this->getJob();

            if ($this->shouldProcessTranslation($job)) {
                $service->processJob($this->jobId);
                $job = $this->getJob();
            }

            if ($this->autoPublish) {
                $this->applyAndPublish($service, $job);
            }
        } finally {
            $delay = (int) config('palpalych.aitranslator::queue_delay', 60);
            if ($delay > 0) {
                sleep($delay);
            }
        }
    }

    protected function getJob(): Job
    {
        $job = Job::with(['fields', 'translatable'])->find($this->jobId);
        if (!$job) {
            throw new \Exception("Job [{$this->jobId}] was not found.");
        }

        return $job;
    }

    protected function shouldProcessTranslation(Job $job): bool
    {
        if ($job->status === JobStatus::failed && $this->autoPublish) {
            return !$this->hasTranslatedFields($job);
        }

        return in_array($job->status, [
            JobStatus::pending,
            JobStatus::processing,
            JobStatus::failed,
        ], true);
    }

    protected function applyAndPublish(TranslationService $service, Job $job): void
    {
        $translationApplied = false;
        $targetSite = SiteDefinition::find($job->target_site_id);
        if (!$targetSite) {
            $e = new \Exception("Target Site ID [{$job->target_site_id}] not found.");
            if ($this->hasTranslatedFields($job)) {
                $this->markPublishFailed($e);
            }

            throw $e;
        }

        if ($job->status === JobStatus::review) {
            $targetRecord = $service->applyJobToTarget($job);
            $job = $this->getJob();
            $translationApplied = true;
        } elseif ($job->status === JobStatus::applied) {
            $targetRecord = $service->getOrInitTargetRecord($job->translatable, $job->target_site_id);
            if (!$this->targetHasTranslatedFields($job, $targetRecord)) {
                $targetRecord = $service->applyJobToTarget($job);
                $job = $this->getJob();
            }
            $translationApplied = true;
        } elseif ($job->status === JobStatus::failed && $this->hasTranslatedFields($job)) {
            $targetRecord = $service->getOrInitTargetRecord($job->translatable, $job->target_site_id);
            if (!$this->targetHasTranslatedFields($job, $targetRecord)) {
                $targetRecord = $service->applyJobToTarget($job);
                $job = $this->getJob();
            } else {
                $job = $this->markTranslationApplied();
            }
            $translationApplied = true;
        } else {
            throw new \Exception("Job [{$job->id}] cannot be auto-published from status [{$job->status->name}].");
        }

        try {
            if (!$targetRecord instanceof PublishesAiTranslations) {
                throw new \Exception("Target record must implement " . PublishesAiTranslations::class . " to auto-publish.");
            }

            \Site::withContext($targetSite->id, function() use ($targetRecord, $job, $targetSite) {
                $targetRecord->publishAiTranslation($job, $targetSite);
            });

            $this->markPublishSucceeded();
        } catch (\Exception $e) {
            if ($translationApplied) {
                $this->markPublishFailed($e);
            }

            throw $e;
        }
    }

    protected function hasTranslatedFields(Job $job): bool
    {
        if ($job->fields->isEmpty()) {
            return false;
        }

        foreach ($job->fields as $field) {
            if ($field->final_value === null && $field->ai_value === null) {
                return false;
            }
        }

        return true;
    }

    protected function targetHasTranslatedFields(Job $job, $targetRecord): bool
    {
        foreach ($job->fields as $field) {
            $expectedValue = $field->final_value ?? $field->ai_value;
            $targetValue = $targetRecord->getAttribute($field->field_name);

            if ((string) $targetValue !== (string) $expectedValue) {
                return false;
            }
        }

        return true;
    }

    protected function markPublishFailed(\Exception $e): void
    {
        $job = Job::find($this->jobId);
        if (!$job) {
            return;
        }

        $job->status = JobStatus::failed;
        $job->error_message = $e->getMessage();
        $job->save();
    }

    protected function markTranslationApplied(): Job
    {
        $job = $this->getJob();
        $job->status = JobStatus::applied;
        $job->error_message = null;
        $job->save();

        return $this->getJob();
    }

    protected function markPublishSucceeded(): void
    {
        $job = Job::find($this->jobId);
        if (!$job) {
            return;
        }

        $job->status = JobStatus::applied;
        $job->error_message = null;
        $job->save();
    }
}
