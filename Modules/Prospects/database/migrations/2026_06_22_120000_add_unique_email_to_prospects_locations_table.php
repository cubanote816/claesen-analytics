<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Normalizar todos los emails existentes a minúsculas y sin espacios
        DB::table('prospects_locations')
            ->whereNotNull('email')
            ->update(['email' => DB::raw('LOWER(TRIM(email))')]);

        // 2. Eliminar duplicados ya normalizados (mantener el id más antiguo)
        $duplicates = DB::table('prospects_locations')
            ->select('email', DB::raw('MIN(id) as min_id'))
            ->whereNotNull('email')
            ->groupBy('email')
            ->havingRaw('COUNT(id) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('prospects_locations')
                ->where('email', $duplicate->email)
                ->where('id', '!=', $duplicate->min_id)
                ->delete();
        }

        // 3. Crear el constraint unique
        Schema::table('prospects_locations', function (Blueprint $table) {
            $table->unique('email', 'prospects_locations_email_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('prospects_locations', function (Blueprint $table) {
            $table->dropUnique('prospects_locations_email_unique');
        });
    }
};
