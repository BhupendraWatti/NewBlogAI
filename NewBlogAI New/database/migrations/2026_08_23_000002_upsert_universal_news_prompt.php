<?php

declare(strict_types=1);

use App\Modules\PromptManager\Support\UniversalNewsPrompt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('prompts')->updateOrInsert(
            ['name' => UniversalNewsPrompt::NAME],
            [
                'prompt' => UniversalNewsPrompt::template(),
                'variables' => json_encode(UniversalNewsPrompt::variables(), JSON_THROW_ON_ERROR),
                'version' => UniversalNewsPrompt::VERSION,
                'status' => 'active',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('prompts')
            ->where('name', UniversalNewsPrompt::NAME)
            ->where('version', UniversalNewsPrompt::VERSION)
            ->update([
                'prompt' => 'Write a factual news article about {{topic}} for {{website}} in {{language}}. Use only the supplied research context.',
                'variables' => json_encode(['topic', 'website', 'language', 'research_context'], JSON_THROW_ON_ERROR),
                'version' => 'v1.0',
                'updated_at' => now(),
            ]);
    }
};
