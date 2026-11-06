<?php

namespace App\Models;

use App\Enums\IntervalUnit;
use App\Enums\NotifyFrom;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanVersion extends Model
{
    use HasFactory;

    /**
     * Minutes-per-unit divisors used to convert `interval_minutes` (the
     * canonical stored value) to/from the unit the user picked in the UI.
     * A month is normalized to 30 days for storage/display purposes only —
     * the scheduler's Months branch schedules calendar-correct occurrences.
     */
    private const UNIT_MINUTES = [
        IntervalUnit::Hours->value => 60,
        IntervalUnit::Days->value => 1440,
        IntervalUnit::Weeks->value => 10080,
        IntervalUnit::Months->value => 43200,
    ];

    protected $fillable = [
        'plan_id',
        'effective_from',
        'interval_minutes',
        'interval_unit',
        'times',
        'weekdays',
        'anchor_date',
        'notify_from',
        'duration_minutes',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'anchor_date' => 'date',
        'interval_minutes' => 'integer',
        'interval_unit' => IntervalUnit::class,
        'times' => 'array',
        'weekdays' => 'array',
        'notify_from' => NotifyFrom::class,
        'duration_minutes' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * The latest version whose `effective_from` is on or before the given
     * date — the recurrence rule that was (or is) active on that date.
     */
    public static function activeFor(Plan $plan, CarbonInterface $date): ?self
    {
        return $plan->versions()
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * `interval_minutes` converted to the display unit the user picked
     * (e.g. 180 minutes, Hours unit → 3).
     */
    public function intervalValue(): float
    {
        return $this->interval_minutes / self::UNIT_MINUTES[$this->interval_unit->value];
    }

    /**
     * Set `interval_minutes` (and `interval_unit`) from a value expressed in
     * the given display unit (e.g. 3, Hours → 180 minutes stored).
     */
    public function setIntervalFromValue(float $value, IntervalUnit $unit): void
    {
        $this->interval_unit = $unit;
        $this->interval_minutes = (int) round($value * self::UNIT_MINUTES[$unit->value]);
    }
}
