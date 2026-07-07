<?php

declare(strict_types=1);

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cache de resultados de Google Geocoding, independiente de Complex.lat/lng.
 *
 * Modules\FieldOps\Services\GeocodingService consulta esta tabla antes de llamar
 * a Google — sobrevive a un reset/reseed de fo_complexes (migrate:fresh, dev DB
 * limpiada, etc.), asi una direccion real ya resuelta una vez nunca se vuelve a
 * pagar/consultar solo porque la fila de Complex que la referenciaba desaparecio.
 */
class GeocodingCache extends Model
{
    protected $table = 'fo_geocoding_cache';

    protected $fillable = [
        'address_hash',
        'address',
        'lat',
        'lng',
        'status',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];
}
