<?php

namespace PalPalych\AiTranslator\Classes\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PalPalych\AiTranslator\Classes\TranslationService;

class ProcessTranslationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public int $jobId
    ) {
        $this->onQueue('aitranslator');
    }

    public function handle(TranslationService $service): void
    {
        try {
            $service->processJob($this->jobId);
        } finally {
            $delay = (int) config('palpalych.aitranslator::queue_delay', 60);
            if ($delay > 0) {
                sleep($delay);
            }
        }
    }
}
