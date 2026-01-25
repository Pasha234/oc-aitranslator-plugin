<?php

namespace PalPalych\AiTranslator\Models;

use Model;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @mixin Builder
 *
 * @property-read int $id
 *
 * @property string $job_id
 * @property ?string $field_name
 * @property ?string $original_value
 * @property ?string $ai_value
 * @property ?string $final_value
 * @property bool $is_modified
 *
 * @property-read Job $job
 *
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
class FieldTranslation extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table name
     */
    public $table = 'palpalych_aitranslator_field_translations';

    /**
     * @var array rules for validation
     */
    public $rules = [];

    public $belongsTo = [
        'job' => Job::class
    ];
}
