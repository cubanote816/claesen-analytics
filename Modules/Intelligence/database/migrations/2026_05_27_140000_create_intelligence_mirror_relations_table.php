<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirror of CAFCA's relation table (clients/customers).
     * Required so offer simulations can be associated with a real CAFCA client
     * without querying SQL Server at runtime.
     */
    public function up(): void
    {
        Schema::create('intelligence_mirror_relations', function (Blueprint $table) {
            // Matches relation.id from SQL Server (integer PK)
            $table->integer('id')->primary();

            $table->string('name')->nullable()->index();
            $table->string('zipcode', 15)->nullable()->index();
            $table->string('city')->nullable();
            $table->string('country', 5)->nullable()->default('BE');

            // Language code drives offer output language (nl → Dutch, en/fr → English)
            $table->string('language', 5)->nullable()->default('nl');

            $table->string('vat_number', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('contact_name')->nullable();

            $table->timestamps();

            $table->index(['country', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_mirror_relations');
    }
};
