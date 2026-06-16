<?php

namespace PalPalych\AiTranslator\Models;

use Model;
use Carbon\CarbonInterface;
use October\Rain\Support\Arr;
use October\Rain\Database\Collection;
use Illuminate\Database\Eloquent\Builder;
use PalPalych\AiTranslator\Models\Job\JobStatus;

/**
 * @mixin Builder
 *
 * @property-read int $id
 *
 * @property string $translatable_type
 * @property int $translatable_id
 * @property ?string $source_locale
 * @property ?string $target_locale
 * @property ?int $target_site_id
 * @property ?int $prompt_id
 * @property JobStatus $status
 * @property ?string $error_message
 * @property ?string $driver
 * @property ?string $driver_response
 *
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 *
 * @property-read Prompt $prompt
 * @property-read Collection<int, FieldTranslation> $fields
 * @property-read ?Model $translatable
 *
 * @method Builder published()
 */
class Job extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string table name
     */
    public $table = 'palpalych_aitranslator_jobs';

    /**
     * @var array rules for validation
     */
    public $rules = [];

    public $hasMany = [
        'fields' => FieldTranslation::class,
    ];

    public $belongsTo = [
        'prompt' => Prompt::class,
    ];

    public $morphTo = [
        'translatable' => []
    ];

    protected $casts = [
        'status' => JobStatus::class,
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', JobStatus::applied);
    }

    public function getStatusOptions(): array
    {
        return Arr::map(Arr::keyBy(JobStatus::cases(), 'value'), function (JobStatus $value, string $key) {
            return __('palpalych.aitranslator::lang.job_status.' . $value->name);
        });
    }

    public function getTranslatableTypeOptions()
    {
        return self::select('translatable_type')
            ->whereNotNull('translatable_type')
            ->distinct()
            ->lists('translatable_type', 'translatable_type');
    }

    public function getStatusNameAttribute(): string
    {
        return __('palpalych.aitranslator::lang.job_status.' . $this->status->name);
    }

    public function translatable()
    {
        return $this->morphTo()->withoutGlobalScopes();
    }
}
