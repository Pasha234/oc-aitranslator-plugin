<?php

namespace PalPalych\AiTranslator\Classes\Dto;

use PalPalych\AiTranslator\Classes\Dto\ContentDto;

class TranslationRequestDto
{
    public function __construct(
        public readonly ContentDto $content,
        public readonly string $sourceLang,
        public readonly string $targetLang,
        public readonly string $customInstructions
    ) {
    }
}
