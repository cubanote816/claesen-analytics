<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->enum('category', ['commercial', 'transactional'])
                  ->default('commercial')
                  ->after('body');
            $table->json('variables')->nullable()->after('category');
            $table->unsignedTinyInteger('version')->default(1)->after('variables');
            $table->foreignId('parent_id')
                  ->nullable()
                  ->after('version')
                  ->constrained('email_templates')
                  ->nullOnDelete();
            $table->foreignId('created_by')
                  ->nullable()
                  ->after('parent_id')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['created_by']);
            $table->dropColumn(['category', 'variables', 'version', 'parent_id', 'created_by']);
        });
    }
};
