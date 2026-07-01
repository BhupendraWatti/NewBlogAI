<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Seed Prompts
        $prompt1 = \App\Models\Promt::create([
            'name' => 'Newsletter Compiler',
            'promt' => 'Synthesize the latest news on this topic into an engaging weekly newsletter format. Use markdown.'
        ]);

        $prompt2 = \App\Models\Promt::create([
            'name' => 'Tech Blog Writer',
            'promt' => 'Write a deeply analytical blog post regarding the latest AI releases. Keep the tone professional.'
        ]);

        // Seed Topics
        \App\Models\Topic::create(['name' => 'Generative AI']);
        \App\Models\Topic::create(['name' => 'Venture Capital']);
        \App\Models\Topic::create(['name' => 'Healthy Living']);

        // Seed Keys
        $key1 = \App\Models\keys::create([
            'name' => 'Primary OpenAI Key',
            'key' => 'sk-proj-xxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
        ]);

        $key2 = \App\Models\keys::create([
            'name' => 'Claude Anthropic Key',
            'key' => 'sk-ant-xxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
        ]);

        // Seed Sites
        \App\Modules\SiteManager\Models\Site::create([
            'domain_url' => 'https://tech-insider.com',
            'slot' => '12:00',
            'api_key' => $key1->id, // References the key id
            'promt_id' => $prompt2->id,
            'selected_topics' => [
                ['topic' => 'Generative AI', 'promt_id' => $prompt2->id]
            ],
            'last_sync_status' => 'success',
            'last_synced_at' => now()->subMinutes(2),
        ]);

        \App\Modules\SiteManager\Models\Site::create([
            'domain_url' => 'https://finance-daily.net',
            'slot' => '14:30',
            'api_key' => $key1->id,
            'promt_id' => $prompt1->id,
            'selected_topics' => [
                ['topic' => 'Venture Capital', 'promt_id' => $prompt1->id]
            ],
            'last_sync_status' => 'syncing',
            'last_synced_at' => now()->subMinutes(45),
        ]);

        \App\Modules\SiteManager\Models\Site::create([
            'domain_url' => 'https://health-trends.org',
            'slot' => '09:00',
            'api_key' => $key2->id,
            'promt_id' => $prompt1->id,
            'selected_topics' => [
                ['topic' => 'Healthy Living', 'promt_id' => $prompt1->id]
            ],
            'last_sync_status' => 'failed',
            'last_synced_at' => now()->subHours(3),
            'error_log' => 'WordPress Sync failed: HTTP 403 Forbidden. Invalid API Key credentials configured.',
        ]);
    }
}
