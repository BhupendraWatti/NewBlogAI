<?php

declare(strict_types=1);

use App\Modules\PromptManager\Support\StandardUniversalNewsPromptV2;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('prompts')
            ->where('name', 'Standard Universal Article Generator')
            ->update([
                'prompt' => StandardUniversalNewsPromptV2::TEXT,
                'version' => 'v2.0',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Prompt Library content is user-editable. Do not destroy subsequent
        // user changes during rollback by restoring an obsolete template.
    }
};
