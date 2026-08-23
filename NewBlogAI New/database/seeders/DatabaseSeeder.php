<?php

namespace Database\Seeders;

use App\Models\Key;
use App\Models\User;
use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\PromptManager\Models\Prompt;
use App\Modules\PromptManager\Support\UniversalNewsPrompt;
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
        if (!$user->customer_id) {
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
            // ── Feature Entitlements ─────────────────────────────
            'analytics_access' => true,   // Enables per-site analytics dashboard
            'priority_support' => true,   // Enables priority support badge
        ]);


        // Seed AI Providers
        AIProvider::create([
            'provider_key' => 'openai',
            'name' => 'OpenAI',
            'api_key' => '',
            'default_model' => 'gpt-4o',
            'is_default' => true,
            'is_enabled' => true,
        ]);
        AIProvider::create([
            'provider_key' => 'gemini',
            'name' => 'Google Gemini',
            'api_key' => '',
            'default_model' => 'gemini-2.5-flash',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        AIProvider::create([
            'provider_key' => 'claude',
            'name' => 'Claude (Anthropic)',
            'api_key' => '',
            'default_model' => 'claude-3-5-sonnet-20241022',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        AIProvider::create([
            'provider_key' => 'groq',
            'name' => 'Groq',
            'api_key' => '',
            'default_model' => 'llama-3.3-70b-versatile',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        AIProvider::create([
            'provider_key' => 'openrouter',
            'name' => 'OpenRouter',
            'api_key' => '',
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
            'name' => UniversalNewsPrompt::NAME,
            'prompt' => UniversalNewsPrompt::template(),
            'variables' => UniversalNewsPrompt::variables(),
            'version' => UniversalNewsPrompt::VERSION,
            'status' => 'active',
        ]);
    }
}
