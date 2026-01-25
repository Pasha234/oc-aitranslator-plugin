<?php

namespace PalPalych\AiTranslator\Models;

use Model;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Str;

/**
 * @mixin Builder
 *
 * @property-read int $id
 *
 * @property ?string $name
 * @property ?string $system_instruction
 *
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 *
 * @property-read ?string $short_instruction
 */
class Prompt extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table name
     */
    public $table = 'palpalych_aitranslator_prompts';

    /**
     * @var array rules for validation
     */
    public $rules = [];

    public function getShortInstructionAttribute()
    {
        return Str::limit($this->system_instruction, 100);
    }
}
