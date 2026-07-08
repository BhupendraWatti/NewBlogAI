<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add file_size and site_id columns to media_items for storage-quota enforcement.
     *
     * file_size: bytes of the stored asset (0 for external/CDN URLs that are not stored locally).
     * site_id:   denormalised from generated_contents for efficient per-customer SUM queries.
     */
    public function up(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->unsignedBigInteger('file_size')->default(0)->after('url')
                  ->comment('Stored file size in bytes; 0 for external/CDN assets.');
            $table->unsignedBigInteger('site_id')->nullable()->after('generated_content_id')
                  ->comment('Denormalised from generated_contents.site_id for fast storage-quota queries.');

            $table->index('site_id', 'media_items_site_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->dropIndex('media_items_site_id_index');
            $table->dropColumn(['file_size', 'site_id']);
        });
    }
};
