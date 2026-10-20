<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function ($query) {
            $query->where($query->getModel()->getTable() . '.tenant_id', app('tenant_id'));
        });

        static::creating(function ($model) {
            $model->tenant_id = app('tenant_id');
            if (!$model->getKey()) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}