<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('website_projects', function (Blueprint $table) {
            $table->string('category')->nullable()->after('slug'); // Enum: sport, industrial, public
            $table->string('location')->nullable()->after('category');
            $table->integer('year')->nullable()->after('location');
            $table->string('client')->nullable()->after('year');
            $table->json('description')->nullable()->after('title'); // Replaces content? Keeping both for now or just adding description
            $table->boolean('published')->default(false)->after('seo_tags');
            $table->boolean('featured')->default(false)->after('published');
            $table->integer('order_index')->default(0)->after('featured');
            $table->softDeletes()->after('updated_at');

            $table->index(['published', 'featured']);
            $table->index('category');
            $table->index('year');
            $table->index('order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('website_projects', function (Blueprint $table) {});
    }
};
