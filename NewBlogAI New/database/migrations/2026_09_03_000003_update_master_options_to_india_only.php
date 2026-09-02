<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        // 1. Ensure India exists as the primary country
        $india = DB::table('master_options')
            ->where('type', 'country')
            ->where('code', 'IN')
            ->first();

        if (! $india) {
            $indiaId = DB::table('master_options')->insertGetId([
                'type' => 'country',
                'name' => 'India',
                'code' => 'IN',
                'parent_id' => null,
                'is_active' => true,
                'sort_order' => 1,
                'metadata' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $indiaId = $india->id;
            DB::table('master_options')
                ->where('id', $indiaId)
                ->update([
                    'name' => 'India',
                    'is_active' => true,
                    'sort_order' => 1,
                ]);
        }

        // 2. Remove all countries other than India, and remove their child states
        $otherCountryIds = DB::table('master_options')
            ->where('type', 'country')
            ->where('id', '!=', $indiaId)
            ->pluck('id')
            ->all();

        if (! empty($otherCountryIds)) {
            DB::table('master_options')
                ->where('type', 'state')
                ->whereIn('parent_id', $otherCountryIds)
                ->delete();

            DB::table('master_options')
                ->whereIn('id', $otherCountryIds)
                ->delete();
        }

        // 3. Comprehensive Indian States & Union Territories
        $indiaStates = [
            ['name' => 'Andhra Pradesh', 'code' => 'AP'],
            ['name' => 'Arunachal Pradesh', 'code' => 'AR'],
            ['name' => 'Assam', 'code' => 'AS'],
            ['name' => 'Bihar', 'code' => 'BR'],
            ['name' => 'Chhattisgarh', 'code' => 'CG'],
            ['name' => 'Goa', 'code' => 'GA'],
            ['name' => 'Gujarat', 'code' => 'GJ'],
            ['name' => 'Haryana', 'code' => 'HR'],
            ['name' => 'Himachal Pradesh', 'code' => 'HP'],
            ['name' => 'Jharkhand', 'code' => 'JH'],
            ['name' => 'Karnataka', 'code' => 'KA'],
            ['name' => 'Kerala', 'code' => 'KL'],
            ['name' => 'Madhya Pradesh', 'code' => 'MP'],
            ['name' => 'Maharashtra', 'code' => 'MH'],
            ['name' => 'Manipur', 'code' => 'MN'],
            ['name' => 'Meghalaya', 'code' => 'ML'],
            ['name' => 'Mizoram', 'code' => 'MZ'],
            ['name' => 'Nagaland', 'code' => 'NL'],
            ['name' => 'Odisha', 'code' => 'OD'],
            ['name' => 'Punjab', 'code' => 'PB'],
            ['name' => 'Rajasthan', 'code' => 'RJ'],
            ['name' => 'Sikkim', 'code' => 'SK'],
            ['name' => 'Tamil Nadu', 'code' => 'TN'],
            ['name' => 'Telangana', 'code' => 'TG'],
            ['name' => 'Tripura', 'code' => 'TR'],
            ['name' => 'Uttar Pradesh', 'code' => 'UP'],
            ['name' => 'Uttarakhand', 'code' => 'UK'],
            ['name' => 'West Bengal', 'code' => 'WB'],
            // Union Territories
            ['name' => 'Delhi', 'code' => 'DL'],
            ['name' => 'Jammu and Kashmir', 'code' => 'JK'],
            ['name' => 'Ladakh', 'code' => 'LA'],
            ['name' => 'Chandigarh', 'code' => 'CH'],
            ['name' => 'Puducherry', 'code' => 'PY'],
            ['name' => 'Andaman and Nicobar Islands', 'code' => 'AN'],
            ['name' => 'Dadra and Nagar Haveli and Daman and Diu', 'code' => 'DN'],
            ['name' => 'Lakshadweep', 'code' => 'LD'],
        ];

        foreach ($indiaStates as $idx => $st) {
            $existing = DB::table('master_options')
                ->where('type', 'state')
                ->where('code', $st['code'])
                ->first();

            if ($existing) {
                DB::table('master_options')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $st['name'],
                        'parent_id' => $indiaId,
                        'is_active' => true,
                        'sort_order' => $idx + 1,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('master_options')->insert([
                    'type' => 'state',
                    'name' => $st['name'],
                    'code' => $st['code'],
                    'parent_id' => $indiaId,
                    'is_active' => true,
                    'sort_order' => $idx + 1,
                    'metadata' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 4. Newsroom topics
        $topics = [
            ['name' => 'National News', 'code' => 'national-news'],
            ['name' => 'Politics & Governance', 'code' => 'politics-governance'],
            ['name' => 'Indian Startups', 'code' => 'indian-startups'],
            ['name' => 'Business & Economy', 'code' => 'business-economy'],
            ['name' => 'Technology & AI', 'code' => 'technology-ai'],
            ['name' => 'Markets & Finance', 'code' => 'markets-finance'],
            ['name' => 'Sports & Cricket', 'code' => 'sports-cricket'],
            ['name' => 'Entertainment & Cinema', 'code' => 'entertainment-cinema'],
            ['name' => 'Crime & Legal', 'code' => 'crime-legal'],
            ['name' => 'Science & Healthcare', 'code' => 'science-healthcare'],
            ['name' => 'Automobile & EV', 'code' => 'automobile-ev'],
            ['name' => 'Education & Jobs', 'code' => 'education-jobs'],
            ['name' => 'Real Estate & Infrastructure', 'code' => 'real-estate'],
        ];

        foreach ($topics as $idx => $tp) {
            $existing = DB::table('master_options')
                ->where('type', 'topic')
                ->where('code', $tp['code'])
                ->first();

            if ($existing) {
                DB::table('master_options')
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $tp['name'],
                        'is_active' => true,
                        'sort_order' => $idx + 1,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('master_options')->insert([
                    'type' => 'topic',
                    'name' => $tp['name'],
                    'code' => $tp['code'],
                    'parent_id' => null,
                    'is_active' => true,
                    'sort_order' => $idx + 1,
                    'metadata' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive rollback
    }
};
