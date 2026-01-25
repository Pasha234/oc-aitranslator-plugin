<?php

namespace PalPalych\AiTranslator\Classes;

use PalPalych\AiTranslator\Classes\Drivers\ClaudeDriver;
use PalPalych\AiTranslator\Classes\Drivers\DummyDriver;

class LlmManager
{
    public static function driver($driverName = 'claude')
    {
        switch ($driverName) {
            case 'claude':
                return new ClaudeDriver();

            case 'dummy':
                return new DummyDriver();

            default:
                throw new \Exception("Driver {$driverName} not supported.");
        }
    }
}
