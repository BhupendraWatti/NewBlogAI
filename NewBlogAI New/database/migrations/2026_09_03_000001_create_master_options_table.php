<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('master_options', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index()->comment('Option type: topic, country, state');
            $table->string('name', 191);
            $table->string('code', 50)->nullable()->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')
                ->references('id')
                ->on('master_options')
                ->onDelete('cascade');
        });

        // Seed initial standard options so UI is fully populated out-of-the-box
        $now = now();

        // 1. Countries (India only for focused regional newsroom)
        $countries = [
            ['name' => 'India', 'code' => 'IN', 'sort_order' => 1],
        ];

        $countryIds = [];
        foreach ($countries as $country) {
            $id = DB::table('master_options')->insertGetId([
                'type' => 'country',
                'name' => $country['name'],
                'code' => $country['code'],
                'parent_id' => null,
                'is_active' => true,
                'sort_order' => $country['sort_order'],
                'metadata' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $countryIds[$country['code']] = $id;
        }

        // 2. States for India
        if (isset($countryIds['IN'])) {
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
                DB::table('master_options')->insert([
                    'type' => 'state',
                    'name' => $st['name'],
                    'code' => $st['code'],
                    'parent_id' => $countryIds['IN'],
                    'is_active' => true,
                    'sort_order' => $idx + 1,
                    'metadata' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // 4. Topics / Categories
        $topics = [
            ['name' => 'Indian Startups', 'code' => 'indian-startups'],
            ['name' => 'Technology & AI', 'code' => 'tech-ai'],
            ['name' => 'Business & Economy', 'code' => 'business-economy'],
            ['name' => 'Politics & Policy', 'code' => 'politics-policy'],
            ['name' => 'National News', 'code' => 'national-news'],
            ['name' => 'Crime & Legal', 'code' => 'crime-legal'],
            ['name' => 'Sports & Cricket', 'code' => 'sports-cricket'],
            ['name' => 'Science & Healthcare', 'code' => 'science-healthcare'],
            ['name' => 'Entertainment & Cinema', 'code' => 'entertainment'],
        ];

        foreach ($topics as $idx => $tp) {
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_options');
    }
};
