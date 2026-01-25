<?php

namespace PalPalych\AiTranslator\Classes\Dto;

class TranslationResponseDto
{
    public function __construct(
        public readonly array $translatedFields,
        public readonly string $responseString,
    ) {}
}
