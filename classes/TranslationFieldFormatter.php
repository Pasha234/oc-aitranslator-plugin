<?php

namespace PalPalych\AiTranslator\Classes;

class TranslationFieldFormatter
{
    public function getRichTextFields(): array
    {
        return array_values(array_unique(array_map(
            'strval',
            (array) config('palpalych.aitranslator::rich_text_fields', ['body'])
        )));
    }

    public function isRichText(string $fieldName): bool
    {
        return in_array($fieldName, $this->getRichTextFields(), true);
    }

    public function sanitize(string $fieldName, $value)
    {
        if ($value === null || $this->isRichText($fieldName)) {
            return $value;
        }

        $value = (string) $value;
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $value);
        $value = preg_replace('/<\/\s*(p|div|li|h[1-6])\s*>/i', "\n", $value);
        $value = strip_tags($value);
        $value = preg_replace("/[ \t]+\n/", "\n", $value);
        $value = preg_replace("/\n{3,}/", "\n\n", $value);

        return trim($value);
    }
}
