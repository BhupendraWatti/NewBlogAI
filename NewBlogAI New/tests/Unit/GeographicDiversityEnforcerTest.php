<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\ContentPipeline\Services\GeographicDiversityEnforcer;
use Tests\TestCase;

class GeographicDiversityEnforcerTest extends TestCase
{
    public function test_location_phrase_relaxes_only_its_matching_location(): void
    {
        $candidates = $this->candidatesFrom('Ujjain', 'Madhya Pradesh', 5);
        $result = (new GeographicDiversityEnforcer)->filter($candidates, 'Latest Ujjain news');

        $this->assertCount(5, $result['passed']);
        $this->assertCount(0, $result['blocked']);
    }

    public function test_non_location_topic_keeps_global_city_diversity(): void
    {
        $candidates = $this->candidatesFrom('Ujjain', 'Madhya Pradesh', 5);
        $result = (new GeographicDiversityEnforcer)->filter($candidates, 'Technology');

        $this->assertCount(2, $result['passed']);
        $this->assertCount(3, $result['blocked']);
    }

    private function candidatesFrom(string $city, string $state, int $count): array
    {
        return array_map(fn (int $index) => [
            'title' => "Distinct event {$index}",
            'geo_city' => $city,
            'geo_state' => $state,
        ], range(1, $count));
    }
}
