<?php

namespace PalPalych\AiTranslator\Classes\Contracts;

use PalPalych\AiTranslator\Models\Job;
use System\Models\SiteDefinition;

interface PublishesAiTranslations
{
    public function publishAiTranslation(Job $job, SiteDefinition $targetSite): void;
}
