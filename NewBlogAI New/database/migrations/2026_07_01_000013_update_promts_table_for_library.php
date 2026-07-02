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
        if (Schema::hasTable('promts')) {
            Schema::table('promts', function (Blueprint $table) {
                if (!Schema::hasColumn('promts', 'category')) {
                    $table->string('category')->nullable()->after('name');
                }
                if (!Schema::hasColumn('promts', 'variables')) {
                    $table->json('variables')->nullable()->after('promt');
                }
                if (!Schema::hasColumn('promts', 'version')) {
                    $table->string('version')->default('v1.0')->after('variables');
                }
                if (!Schema::hasColumn('promts', 'status')) {
                    $table->string('status')->default('active')->after('version'); // active, inactive
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('promts')) {
            Schema::table('promts', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('promts', 'category')) $columns[] = 'category';
                if (Schema::hasColumn('promts', 'variables')) $columns[] = 'variables';
                if (Schema::hasColumn('promts', 'version')) $columns[] = 'version';
                if (Schema::hasColumn('promts', 'status')) $columns[] = 'status';

                if (count($columns) > 0) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
