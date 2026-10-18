<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row app settings (seeded by its migration).
 */
class Setting extends Model
{
    /**
     * @var list<string>
     */
    public const ALERT_MODES = ['vibrate', 'sound', 'both'];

    private const MPS_TO_KMH = 3.6;

    private const MPS_TO_MPH = 2.236936;

    /**
     * @var list<string>
     */
    protected $fillable = ['unit', 'speed_limit_kmh', 'road_limit_alerts', 'alert_mode', 'alert_volume', 'tank_capacity_l', 'refuel_reminders', 'auto_record', 'auto_record_mode', 'oil_change_interval_km', 'oil_change_interval_months'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'speed_limit_kmh' => 'integer',
            'road_limit_alerts' => 'boolean',
            'alert_volume' => 'integer',
            'tank_capacity_l' => 'float',
            'refuel_reminders' => 'boolean',
            'auto_record' => 'boolean',
            'oil_change_interval_km' => 'integer',
            'oil_change_interval_months' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate([]);
    }

    public function alertVibrates(): bool
    {
        return in_array($this->alert_mode, ['vibrate', 'both'], true);
    }

    public function alertPlaysSound(): bool
    {
        return in_array($this->alert_mode, ['sound', 'both'], true);
    }

    public function isImperial(): bool
    {
        return $this->unit === 'mph';
    }

    /**
     * Convert meters per second to the display unit.
     */
    public function speed(float $metersPerSecond): float
    {
        return $metersPerSecond * ($this->isImperial() ? self::MPS_TO_MPH : self::MPS_TO_KMH);
    }

    /**
     * Convert meters to the display unit (km or mi).
     */
    public function distance(float $meters): float
    {
        return $meters / ($this->isImperial() ? 1609.344 : 1000);
    }

    public function speedUnit(): string
    {
        return $this->isImperial() ? 'mph' : 'km/h';
    }

    public function distanceUnit(): string
    {
        return $this->isImperial() ? 'mi' : 'km';
    }

    /**
     * Whether a speed in m/s exceeds the personal limit (0 = off) or, when
     * road alerts are on, the posted road limit. Both limits are in km/h.
     */
    public function isOverLimit(float $metersPerSecond, ?int $roadLimitKmh = null): bool
    {
        $kilometersPerHour = $metersPerSecond * self::MPS_TO_KMH;
        $isOverPersonalLimit = $this->speed_limit_kmh > 0 && $kilometersPerHour > $this->speed_limit_kmh;
        $isOverRoadLimit = $this->road_limit_alerts && $roadLimitKmh !== null && $kilometersPerHour > $roadLimitKmh;

        return $isOverPersonalLimit || $isOverRoadLimit;
    }

    /**
     * A km/h value (speed limit) expressed in the display unit, rounded.
     */
    public function fromKmh(float $kilometersPerHour): int
    {
        return (int) round($this->speed($kilometersPerHour / self::MPS_TO_KMH));
    }

    /**
     * A display-unit speed converted to km/h, rounded.
     */
    public function toKmh(float $displaySpeed): int
    {
        return (int) round($displaySpeed / $this->speed(1) * self::MPS_TO_KMH);
    }
}
