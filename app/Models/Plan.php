<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'code',
        'version',
        // legacy
        'business_type',
        // new
        'allowed_business_types',
        'description',
        'price',
        'price_beauty',
        'price_dental',
        'currency',
        'seats',
        'staff_limit',
        'duration_days',
        'locations',
        'features',
        'is_active',
        'is_visible',
        'sort_order',
    ];

    protected $casts = [
        'allowed_business_types' => 'array',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_visible' => 'boolean',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Business types we support in code.
     * MVP uses: salon | clinic
     */
    public static function normalizeBusinessType(string $type): string
    {
        $t = strtolower(trim($type));
        return in_array($t, ['clinic', 'dental'], true) ? 'clinic' : 'salon';
    }

    public function allowsBusinessType(string $type): bool
    {
        $type = self::normalizeBusinessType($type);
        $allowed = $this->allowed_business_types;

        // fallback to legacy business_type
        if (!is_array($allowed) || count($allowed) === 0) {
            if ($this->business_type === null) return true;
            if ($this->business_type === 'beauty' || $this->business_type === 'salon') return $type === 'salon';
            if ($this->business_type === 'dental' || $this->business_type === 'clinic') return $type === 'clinic';
            return true;
        }

        return in_array($type, $allowed, true);
    }

    public function staffLimit(): int
    {
        return (int)($this->staff_limit ?? ($this->features['staff_limit'] ?? $this->seats ?? 0));
    }

    /**
     * Pricing helper.
     * We still keep legacy price_beauty/price_dental for now.
     */
    public function getPriceForBusinessType(string $type): int
    {
        $type = self::normalizeBusinessType($type);

        // if specific price exists by type, use it.
        if ($type === 'clinic') {
            if ($this->price_dental !== null) return (int)$this->price_dental;
        } else {
            if ($this->price_beauty !== null) return (int)$this->price_beauty;
        }

        return (int)($this->price ?? 0);
    }

    public function getFeaturesList(): array
    {
        $f = is_array($this->features) ? $this->features : [];

        // Normalize & guarantee keys
        $out = [
            'staff_limit' => (int)($f['staff_limit'] ?? $this->staffLimit()),
            'sms_reminders' => $f['sms_reminders'] ?? 50,
            'analytics' => (bool)($f['analytics'] ?? false),
            'clinic_patient_card' => (bool)($f['clinic_patient_card'] ?? false),
            'api_access' => (bool)($f['api_access'] ?? false),
            'priority_support' => (bool)($f['priority_support'] ?? false),
            'dedicated_manager' => (bool)($f['dedicated_manager'] ?? false),
        ];

        return $out;
    }
}
