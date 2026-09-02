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
            ->where('version', 'v2.1')
            ->update([
                'prompt' => StandardUniversalNewsPromptV2::TEXT,
                'version' => 'v2.2',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Prompt content is user-editable; do not overwrite later changes.
    }
};
