<?php

namespace PalPalych\AiTranslator\Console;

use October\Rain\Database\Collection;
use Site;
use Illuminate\Console\Command;
use October\Rain\Database\Model;
use October\Rain\Database\Traits\Multisite;
use System\Models\SiteDefinition;
use PalPalych\AiTranslator\Models\Job;
use PalPalych\AiTranslator\Classes\JobManager;
use PalPalych\AiTranslator\Classes\Contracts\PublishesAiTranslations;

class AutoTranslate extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'aitranslator:batch';

    /**
     * @var string The console command description.
     */
    protected $description = 'Automatically creates and processes translation jobs for a model and one or more target sites.';

    /**
     * @var string Signature
     * Example: php artisan aitranslator:batch "PalPalych\Stories\Models\Story" 2 --limit=10
     * Example: php artisan aitranslator:batch --all-sites --limit=10
     */
    protected $signature = 'aitranslator:batch
        {model? : The Model Class (e.g. PalPalych\Stories\Models\Story)}
        {target_site_id? : The ID of the Site you want to translate TO}
        {--all-sites : Translate to every non-primary site}
        {--list-models : List discovered AI translatable models and exit}
        {--auto-publish : Apply and publish translated records automatically when queued jobs complete}
        {--limit=10 : How many records to process per target site}
        {--delay=60 : Seconds to wait after each queued translation job}';

    public function handle()
    {
        if ($this->option('list-models')) {
            $this->listModelChoices();
            return;
        }

        $modelClass = $this->resolveModelClass();
        $limit = (int) $this->option('limit');
        $delay = (int) $this->option('delay');
        $autoPublish = (bool) $this->option('auto-publish');

        if (!$modelClass) {
            return;
        }

        if (!class_exists($modelClass)) {
            $this->error("Model class [$modelClass] not found.");
            return;
        }

        if (!$this->isAiTranslatableModel($modelClass)) {
            $this->error("Model class [$modelClass] does not implement AiTranslatableModel.");
            return;
        }

        if ($autoPublish && !$this->isAutoPublishableModel($modelClass)) {
            $this->error("Model class [$modelClass] must implement " . PublishesAiTranslations::class . " when using --auto-publish.");
            return;
        }

        $primarySite = SiteDefinition::where('is_primary', true)->first();
        if (!$primarySite) {
            $this->error("No Primary Site found.");
            return;
        }

        $targetSites = $this->resolveTargetSites($primarySite);
        if ($targetSites->count() === 0) {
            $this->error("No target sites selected.");
            return;
        }

        $this->info("Source: {$primarySite->name} ({$primarySite->locale})");
        $this->info("Model: {$modelClass}");
        $this->info("Batch Size: {$limit} per target site");
        $this->info("Mode: " . ($autoPublish ? 'Queue with auto-publish' : 'Queue for review'));
        $this->info("Delay: {$delay} seconds after each queued translation job");

        foreach ($targetSites as $targetSite) {
            $this->processTargetSite($modelClass, $primarySite, $targetSite, $limit, $delay, $autoPublish);
        }

        $this->info("Batch completed.");
    }

    protected function processTargetSite(string $modelClass, SiteDefinition $primarySite, SiteDefinition $targetSite, int $limit, int $delay, bool $autoPublish): void
    {
        $this->newLine();
        $this->info("Target: {$targetSite->name} ({$targetSite->locale})");

        $records = $this->findRecordsForTargetSite($modelClass, $primarySite, $targetSite, $limit);

        if ($records->count() === 0) {
            $this->info("No untranslated records found.");
            return;
        }

        $this->info("Found {$records->count()} records to process...");

        $jobManager = new JobManager();

        $bar = $this->output->createProgressBar($records->count());
        $bar->start();

        foreach ($records as $record) {
            try {
                Site::withContext($primarySite->id, function() use ($jobManager, $record, $targetSite, $autoPublish) {
                    $job = $jobManager->createJob($record, $targetSite->locale);

                    $job->target_site_id = $targetSite->id;
                    $job->save();

                    $jobManager->dispatchJob($job, $autoPublish);
                });

                if ($delay > 0) {
                    sleep($delay);
                }

            } catch (\Exception $e) {
                $this->error("\nError processing ID {$record->id} for site {$targetSite->id}: " . $e->getMessage());
                \Log::error("AiTranslator Batch Error: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    protected function findRecordsForTargetSite(string $modelClass, SiteDefinition $primarySite, SiteDefinition $targetSite, int $limit): Collection
    {
        $records = new Collection();
        $existingJobIds = Job::where('translatable_type', $modelClass)
            ->where('target_site_id', $targetSite->id)
            ->pluck('translatable_id')
            ->toArray();
        $existingJobIds = array_flip($existingJobIds);

        $page = 0;
        $pageSize = max($limit * 5, 50);

        do {
            $batch = new Collection();
            Site::withContext($primarySite->id, function() use ($modelClass, $page, $pageSize, &$batch) {
                $batch = $modelClass::orderBy('id', 'asc')
                    ->skip($page * $pageSize)
                    ->take($pageSize)
                    ->get();
            });

            foreach ($batch as $record) {
                if (isset($existingJobIds[$record->id])) {
                    continue;
                }

                if ($this->hasTranslationForSite($record, $targetSite->id)) {
                    continue;
                }

                $records->push($record);

                if ($records->count() >= $limit) {
                    break 2;
                }
            }

            $page++;
        } while ($batch->count() === $pageSize);

        return $records;
    }

    protected function resolveModelClass(): ?string
    {
        $modelClass = $this->argument('model');

        if ($modelClass) {
            return $modelClass;
        }

        if (!$this->input->isInteractive()) {
            $this->error("Model class is required when running non-interactively.");
            return null;
        }

        $models = $this->discoverTranslatableModels();
        if (empty($models)) {
            $this->error("No AI translatable models found.");
            return null;
        }

        return $this->choice('Which model do you want to translate?', $models);
    }

    protected function resolveTargetSites(SiteDefinition $primarySite): Collection
    {
        if ($this->option('all-sites')) {
            return SiteDefinition::where('is_primary', false)->get();
        }

        $targetSiteId = $this->argument('target_site_id');

        if (!$targetSiteId && $this->input->isInteractive()) {
            $siteOptions = SiteDefinition::where('is_primary', false)
                ->get()
                ->mapWithKeys(function($site) {
                    return [$site->id => "{$site->name} ({$site->locale})"];
                })
                ->toArray();

            $choice = $this->choice('Which site do you want to translate to?', array_values($siteOptions));
            $targetSiteId = array_search($choice, $siteOptions);
        }

        $targetSiteId = (int) $targetSiteId;

        if ($primarySite->id === $targetSiteId) {
            $this->error("Target site cannot be the Primary site.");
            return new Collection();
        }

        $targetSite = SiteDefinition::find($targetSiteId);
        if (!$targetSite) {
            $this->error("Target Site ID [$targetSiteId] not found.");
            return new Collection();
        }

        return new Collection([$targetSite]);
    }

    protected function hasTranslationForSite(Model $sourceRecord, int $targetSiteId): bool
    {
        if (!in_array(Multisite::class, class_uses_recursive($sourceRecord))) {
            return false;
        }

        $targetRecord = null;
        Site::withGlobalContext(function() use ($sourceRecord, $targetSiteId, &$targetRecord) {
            $targetRecord = $sourceRecord->findForSite($targetSiteId);
        });

        if (!$targetRecord) {
            return false;
        }

        $fields = $sourceRecord->getAiTranslatableFields();
        if (empty($fields)) {
            return false;
        }

        $hasDifferentField = false;

        foreach ($fields as $field) {
            $targetValue = trim((string) $targetRecord->getAttribute($field));

            if ($targetValue === '') {
                return false;
            }

            $hasDifferentField = true;
        }

        return $hasDifferentField;
    }

    protected function isAiTranslatableModel(string $modelClass): bool
    {
        if (!class_exists($modelClass)) {
            return false;
        }

        $model = new $modelClass();

        return $model instanceof Model
            && in_array(\PalPalych\AiTranslator\Behaviors\AiTranslatableModel::class, (array) $model->implement);
    }

    protected function isAutoPublishableModel(string $modelClass): bool
    {
        if (!class_exists($modelClass)) {
            return false;
        }

        return (new $modelClass()) instanceof PublishesAiTranslations;
    }

    protected function listModelChoices(): void
    {
        $models = $this->discoverTranslatableModels();

        if (empty($models)) {
            $this->info('No AI translatable models found.');
            return;
        }

        foreach ($models as $model) {
            $this->line($model);
        }
    }

    protected function discoverTranslatableModels(): array
    {
        $models = [];
        $directory = base_path('plugins');

        if (!is_dir($directory)) {
            return $models;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            if (strpos($path, DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR) === false) {
                continue;
            }

            $contents = file_get_contents($path);
            if (strpos($contents, 'AiTranslatableModel') === false) {
                continue;
            }

            if (
                preg_match('/namespace\s+([^;]+);/', $contents, $namespaceMatch) &&
                preg_match('/class\s+([A-Za-z0-9_]+)/', $contents, $classMatch)
            ) {
                $models[] = trim($namespaceMatch[1]) . '\\' . $classMatch[1];
            }
        }

        sort($models);

        return array_values(array_unique($models));
    }
}
