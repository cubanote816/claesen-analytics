<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fo_luminaire_types', function (Blueprint $table) {
            $table->string('product_family')->nullable()->after('name');
            $table->string('model_reference')->nullable()->after('product_family');
            $table->text('typical_application')->nullable()->after('model_reference');
            $table->text('image_source_url')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('fo_luminaire_types', function (Blueprint $table) {
            $table->dropColumn([
                'product_family',
                'model_reference',
                'typical_application',
                'image_source_url',
            ]);
        });
    }
};
