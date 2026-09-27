<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan metadata the field app needs (V11.b, CLA-609).
 *
 * The office contract's `DocumentFile` carries neither: it is a table row with a
 * name, a size and a revision, because the office only *lists* documents. The
 * field app instead *renders* the drawing and caches it for offline use, so its
 * `Plan` needs to know what the file is (MIME) and how long it is (pages).
 *
 * `pages` defaults to 1: the demo plans are single-sheet drawings, and the field
 * app's own fixture declares `pages: 1` for the very same documents.
 *
 * No `url` column: it is always a signed temporary URL, built on read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knx_documents', function (Blueprint $table): void {
            $table->string('mime_type', 100)->nullable()->after('kind');
            $table->unsignedSmallInteger('pages')->default(1)->after('size_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('knx_documents', function (Blueprint $table): void {
            $table->dropColumn(['mime_type', 'pages']);
        });
    }
};
