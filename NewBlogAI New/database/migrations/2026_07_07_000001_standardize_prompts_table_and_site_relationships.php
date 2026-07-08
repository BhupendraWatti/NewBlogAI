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
        // 1. Rename table promts to prompts.
        if (Schema::hasTable('promts')) {
            Schema::rename('promts', 'prompts');
        }

        // 2. Rename the promt column in prompts table to prompt.
        if (Schema::hasTable('prompts')) {
            Schema::table('prompts', function (Blueprint $table) {
                if (Schema::hasColumn('prompts', 'promt') && ! Schema::hasColumn('prompts', 'prompt')) {
                    $table->renameColumn('promt', 'prompt');
                }
            });
        }

        // 3. Drop the promt_id column and foreign key from sites table.
        // And drop selected_topics and slot columns from sites table.
        if (Schema::hasTable('sites')) {
            $foreignKeys = Schema::getForeignKeys('sites');
            $hasForeignKey = false;
            foreach ($foreignKeys as $foreignKey) {
                if (isset($foreignKey['columns']) && in_array('promt_id', $foreignKey['columns'])) {
                    $hasForeignKey = true;
                    break;
                }
            }

            Schema::table('sites', function (Blueprint $table) use ($hasForeignKey) {
                if ($hasForeignKey) {
                    $table->dropForeign(['promt_id']);
                }

                $dropColumns = [];
                if (Schema::hasColumn('sites', 'promt_id')) {
                    $dropColumns[] = 'promt_id';
                }
                if (Schema::hasColumn('sites', 'selected_topics')) {
                    $dropColumns[] = 'selected_topics';
                }
                if (Schema::hasColumn('sites', 'slot')) {
                    $dropColumns[] = 'slot';
                }

                if (! empty($dropColumns)) {
                    $table->dropColumn($dropColumns);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sites')) {
            Schema::table('sites', function (Blueprint $table) {
                if (! Schema::hasColumn('sites', 'promt_id')) {
                    $table->unsignedBigInteger('promt_id')->nullable();
                }
                if (! Schema::hasColumn('sites', 'selected_topics')) {
                    $table->text('selected_topics')->nullable();
                }
                if (! Schema::hasColumn('sites', 'slot')) {
                    $table->string('slot')->nullable();
                }
            });
        }

        if (Schema::hasTable('prompts')) {
            Schema::table('prompts', function (Blueprint $table) {
                if (Schema::hasColumn('prompts', 'prompt') && ! Schema::hasColumn('prompts', 'promt')) {
                    $table->renameColumn('prompt', 'promt');
                }
            });
            Schema::rename('prompts', 'promts');
        }
    }
};
