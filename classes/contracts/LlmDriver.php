<?php

namespace PalPalych\AiTranslator\Classes\Contracts;

use PalPalych\AiTranslator\Classes\Dto\TranslationRequestDto;
use PalPalych\AiTranslator\Classes\Dto\TranslationResponseDto;

interface LlmDriver
{
    /**
     * @param TranslationRequestDto $request
     * @return TranslationResponseDto
     */
    public function translate(TranslationRequestDto $request): TranslationResponseDto;
}
