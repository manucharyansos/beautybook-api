<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToSalon
{
    public static function bootBelongsToSalon(): void
    {
        static::creating(function ($model) {
            $user = auth()->user();
            if (!$user) return;
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) return;

            if (empty($model->salon_id)) {
                $model->salon_id = $user->salon_id;
            }
        });

    }
}
