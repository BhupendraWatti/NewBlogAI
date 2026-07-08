<?php

namespace Database\Seeders;

use App\Models\Key;
use App\Models\User;
use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\SiteManager\Models\Site;
use App\Modules\SubscriptionManager\Models\Plan;
use App\Modules\SubscriptionManager\Models\Subscription;
use App\Modules\TopicManager\Models\Topic;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed Customer
        $customer = Customer::create([
            'company_name' => 'NewsBlogify Devs',
            'owner_name' => 'Super Admin',
            'email' => 'admin@newsblogify.com',
            'status' => 'active',
        ]);

        // Super Admin — deterministic credentials for development login
        // role: 1 = Super Admin, 2 = Admin, 3 = Support
        $user = User::firstOrCreate(
            ['email' => 'admin@newsblogify.com'],
            [
                'name' => 'Super Admin',
                'email' => 'admin@newsblogify.com',
                'password' => Hash::make('admin123'),
                'role' => 1,
                'customer_id' => $customer->id,
            ]
        );
        if (! $user->customer_id) {
            $user->update(['customer_id' => $customer->id]);
        }

        // Seed Plan
        $plan = Plan::create([
            'name' => 'Professional Plan',
            'monthly_price' => 79.00,
            'yearly_price' => 790.00,
            'max_wordpress_sites' => 10,
            'max_topics' => 50,
            'publishing_schedule_limit' => 20,
            'max_articles_per_day' => 50,
            'prompt_templates_allowed' => 20,
            'ai_providers_available' => ['openai', 'gemini', 'claude', 'groq', 'openrouter', 'ollama'],
            'api_keys_allowed' => 5,
            'storage_limit' => 5120,
            'status' => 'active',
            'monthly_generation_limit' => 500,
            'minimum_publishing_frequency' => 'hourly',
            'feature_flags' => ['seo_optimizer' => true],
        ]);

        // Seed Subscription
        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_period' => 'monthly',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'limits' => $plan->toArray(),
        ]);

        // Seed AI Providers
        AIProvider::create([
            'provider_key' => 'openai',
            'name' => 'OpenAI',
            'api_key' => 'sk-proj-mockopenaiapikeyvaluehere',
            'default_model' => 'gpt-4o',
            'is_default' => true,
            'is_enabled' => true,
        ]);
        AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Google Gemini',
            'api_key' => 'AIzaSy-mockgeminiapikeyvaluehere',
            'default_model' => 'gemini-2.5-flash',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        AIProvider::create([
            'provider_key' => 'claude',
            'name' => 'Claude (Anthropic)',
            'api_key' => 'sk-ant-mockclaudeapikeyvaluehere',
            'default_model' => 'claude-3-5-sonnet-20241022',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        AIProvider::create([
            'provider_key' => 'groq',
            'name' => 'Groq',
            'api_key' => 'gsk_mockgroqapikeyvaluehere',
            'default_model' => 'llama-3.1-70b-versatile',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        AIProvider::create([
            'provider_key' => 'openrouter',
            'name' => 'OpenRouter',
            'api_key' => 'sk-or-mockopenrouterapikeyvaluehere',
            'default_model' => 'openai/gpt-4o',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        AIProvider::create([
            'provider_key' => 'ollama',
            'name' => 'Ollama',
            'api_key' => 'http://localhost:11434',
            'default_model' => 'llama3',
            'is_default' => false,
            'is_enabled' => true,
        ]);

        // Seed Prompts — must be 'active' for pipeline validation
        $prompt1 = Prompt::create([
            'name' => 'Newsletter Compiler',
            'prompt' => 'Synthesize the latest news on the topic {{topic}} into an engaging weekly newsletter format. Use markdown. Website: {{website}}.',
            'status' => 'active',
        ]);

        $prompt2 = Prompt::create([
            'name' => 'Tech Blog Writer',
            'prompt' => 'Write a deeply analytical blog post about {{topic}}. Language: {{language}}. Keep the tone professional. Website: {{website}}.',
            'status' => 'active',
        ]);

        // Seed Topics — must be 'active' for pipeline validation
        Topic::create(['name' => 'Generative AI', 'status' => 'active']);
        Topic::create(['name' => 'Venture Capital', 'status' => 'active']);
        Topic::create(['name' => 'Healthy Living', 'status' => 'active']);

        // Seed Keys
        $key1 = Key::create([
            'name' => 'Primary OpenAI Key',
            'key' => 'sk-proj-xxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        ]);

        $key2 = Key::create([
            'name' => 'Claude Anthropic Key',
            'key' => 'sk-ant-xxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        ]);

        // Seed Sites
        Site::create([
            'domain_url' => 'https://tech-insider.com',
            'customer_id' => $customer->id,
            'is_active' => true,
            'api_key' => $key1->id, // References the key id
            'last_sync_status' => 'success',
            'last_synced_at' => now()->subMinutes(2),
        ]);

        Site::create([
            'domain_url' => 'https://finance-daily.net',
            'customer_id' => $customer->id,
            'is_active' => true,
            'api_key' => $key1->id,
            'last_sync_status' => 'syncing',
            'last_synced_at' => now()->subMinutes(45),
        ]);

        Site::create([
            'domain_url' => 'https://health-trends.org',
            'customer_id' => $customer->id,
            'is_active' => true,
            'api_key' => $key2->id,
            'last_sync_status' => 'failed',
            'last_synced_at' => now()->subHours(3),
            'error_log' => 'WordPress Sync failed: HTTP 403 Forbidden. Invalid API Key credentials configured.',
        ]);
    }
}
