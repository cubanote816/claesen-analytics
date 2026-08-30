<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fo_geocoding_cache', function (Blueprint $table) {
            $table->id();
            // sha1 de la direccion normalizada (lowercase) usada como query a Google —
            // clave real del cache, independiente de a que Complex termine asociada.
            $table->string('address_hash', 40)->unique();
            $table->text('address');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            // status de la respuesta de Google (OK, ZERO_RESULTS, REQUEST_DENIED, etc.) —
            // se cachea tambien el resultado negativo para no re-intentar dia tras dia
            // direcciones que Google nunca va a poder resolver.
            $table->string('status', 30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fo_geocoding_cache');
    }
};
