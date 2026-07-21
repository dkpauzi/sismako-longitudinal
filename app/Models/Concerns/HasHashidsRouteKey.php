<?php

namespace App\Models\Concerns;

use App\Support\RouteKeyCodec;
use Illuminate\Database\Eloquent\Builder;

/**
 * Obfuscasi route key tanpa kolom/dependency DB (Option A).
 *
 * - getRouteKey(): id integer → token pendek non-sekuensial untuk URL.
 * - resolveRouteBindingQuery(): token → id, dipakai baik oleh route model binding
 *   Laravel MAUPUN Filament (Filament v3 memanggil resolveRouteBindingQuery via
 *   resolveRecordRouteBinding). Field non-null (binding kolom eksplisit) tetap
 *   berperilaku default.
 *
 * PENTING: hanya getRouteKey/route binding yang berubah. getKey() (PK) tetap
 * integer, sehingga relasi, FK, find(), dan eager-load TIDAK terpengaruh.
 */
trait HasHashidsRouteKey
{
    public function getRouteKey(): string
    {
        return RouteKeyCodec::for(static::class)->encode((int) $this->getKey());
    }

    public function resolveRouteBindingQuery($query, $value, $field = null): Builder
    {
        if ($field !== null) {
            return $query->where($field, $value);
        }

        $id = RouteKeyCodec::for(static::class)->decode((string) $value);

        // Token tak valid → paksa 0 baris (404 yang anggun), bukan error.
        return $query->where($this->getKeyName(), $id ?? -1);
    }
}
