<?php

namespace PalPalych\AiTranslator\Console;

use October\Rain\Database\Collection;
use Site;
use Illuminate\Console\Command;
use System\Models\SiteDefinition;
use PalPalych\AiTranslator\Models\Job;
use PalPalych\AiTranslator\Classes\JobManager;
use PalPalych\AiTranslator\Classes\TranslationService;

class AutoTranslate extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'aitranslator:batch';

    /**
     * @var string The console command description.
     */
    protected $description = 'Automatically creates and processes translation jobs for a specific model and target site.';

    /**
     * @var string Signature
     * Example: php artisan aitranslator:batch "PalPalych\Stories\Models\Story" 2 --limit=10
     */
    protected $signature = 'aitranslator:batch
        {model : The Model Class (e.g. PalPalych\Stories\Models\Story)}
        {target_site_id : The ID of the Site you want to translate TO}
        {--limit=10 : How many records to process}';

    public function handle()
    {
        $modelClass = $this->argument('model');
        $targetSiteId = (int) $this->argument('target_site_id');
        $limit = (int) $this->option('limit');

        if (!class_exists($modelClass)) {
            $this->error("Model class [$modelClass] not found.");
            return;
        }

        $targetSite = SiteDefinition::find($targetSiteId);
        if (!$targetSite) {
            $this->error("Target Site ID [$targetSiteId] not found.");
            return;
        }

        $primarySite = SiteDefinition::where('is_primary', true)->first();
        if (!$primarySite) {
            $this->error("No Primary Site found.");
            return;
        }

        if ($primarySite->id === $targetSiteId) {
            $this->error("Target site cannot be the Primary site.");
            return;
        }

        $this->info("Source: {$primarySite->name} ({$primarySite->locale})");
        $this->info("Target: {$targetSite->name} ({$targetSite->locale})");
        $this->info("Batch Size: {$limit}");

        $existingJobIds = Job::where('translatable_type', $modelClass)
            ->where('target_site_id', $targetSiteId)
            ->pluck('translatable_id')
            ->toArray();

        $records = new Collection();
        \Site::withContext($primarySite->id, function() use ($modelClass, $limit, $existingJobIds, &$records) {
            $records = $modelClass::whereNotIn('id', $existingJobIds)
                ->orderBy('created_at', 'desc') // Newest first? Or 'asc' for oldest.
                ->take($limit)
                ->get();
        });

        if ($records->count() === 0) {
            $this->info("No untranslated records found.");
            return;
        }

        $this->info("Found {$records->count()} records to process...");

        $jobManager = new JobManager();
        $service = new TranslationService();

        $bar = $this->output->createProgressBar($records->count());
        $bar->start();

        foreach ($records as $record) {
            try {
                Site::withContext($primarySite->id, function() use ($jobManager, $service, $record, $targetSite, $targetSiteId) {
                    $job = $jobManager->createJob($record, $targetSite->locale);

                    $job->target_site_id = $targetSiteId;
                    $job->save();

                    $service->processJob($job->id);
                });

            } catch (\Exception $e) {
                $this->error("\nError processing ID {$record->id}: " . $e->getMessage());
                \Log::error("AiTranslator Batch Error: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Batch completed.");
    }
}
