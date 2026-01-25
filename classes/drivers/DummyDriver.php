<?php

namespace PalPalych\AiTranslator\Classes\Drivers;

use Log;
use PalPalych\AiTranslator\Classes\Contracts\LlmDriver;
use PalPalych\AiTranslator\Classes\Dto\TranslationRequestDto;
use PalPalych\AiTranslator\Classes\Dto\TranslationResponseDto;

class DummyDriver implements LlmDriver
{
    public function __construct()
    {
        // No API key or setup needed for the dummy driver
    }

    /**
     * @param TranslationRequestDto $request
     * @return TranslationResponseDto
     */
    public function translate(TranslationRequestDto $request): TranslationResponseDto
    {
        Log::info('DummyDriver::translate() called.', [
            'source_lang' => $request->sourceLang,
            'target_lang' => $request->targetLang,
            'fields_count' => count($request->content->fields)
        ]);

        $translatedFields = [];

        foreach ($request->content->fields as $key => $value) {
            // We prepend the target language to verify it works visually
            $translatedFields[$key] = "[DUMMY {$request->targetLang}] " . $value;
        }

        return new TranslationResponseDto($translatedFields, (string) json_encode($translatedFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}
