<?php

namespace PalPalych\AiTranslator\Behaviors;

use October\Rain\Extension\ExtensionBase;

class AiTranslatableModel extends ExtensionBase
{
    protected $parent;

    public function __construct($parent)
    {
        $this->parent = $parent;
    }

    public function getAiTranslatableFields()
    {
        if (property_exists($this->parent, 'aiTranslatable')) {
            return $this->parent->aiTranslatable;
        }

        // Fallback to RainLab.Translate fields if available, otherwise empty
        return property_exists($this->parent, 'translatable')
            ? $this->parent->translatable
            : [];
    }
}
