<?php

namespace App\Mcp\Tools;

use App\Models\Key;
use App\Modules\SiteManager\Models\Site;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Synchronize configured topics and slots with the remote WordPress site.')]
class SyncSiteTopicsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $siteId = $request->get('site_id');
        $site = Site::find($siteId);

        if (! $site) {
            return Response::error("Site with ID {$siteId} not found.");
        }

        $domain = rtrim($site->domain_url, '/');
        if (empty($domain) || ! filter_var($domain, FILTER_VALIDATE_URL)) {
            return Response::error("Invalid domain URL configured for site ID {$siteId}: {$domain}");
        }

        // Resolve API key
        $apiKey = $site->api_key;
        if (is_numeric($apiKey)) {
            $keyRecord = Key::find((int) $apiKey);
            if ($keyRecord) {
                try {
                    $apiKey = Crypt::decryptString($keyRecord->key);
                } catch (\Throwable) {
                    $apiKey = $keyRecord->key;
                }
            }
        }

        if (empty($apiKey)) {
            return Response::error("Authorization token missing for site ID {$siteId}.");
        }

        // Prepare topics format
        $topics = $site->selected_topics ?? [];

        $wpUrl = $domain.'/wp-json/newsblogify/v1/sync-data';

        try {
            $pendingRequest = Http::timeout(15);
            if (! config('services.wordpress.verify_tls', true)) {
                $pendingRequest = $pendingRequest->withoutVerifying();
            }

            $response = $pendingRequest
                ->withToken($apiKey)
                ->post($wpUrl, [
                    'selected_topics' => $topics,
                    'slot' => $site->slot ?? '12:00',
                    'api_key' => $apiKey,
                ]);

            if ($response->successful()) {
                return Response::text("Sync successful! WordPress Response:\n".json_encode($response->json(), JSON_PRETTY_PRINT));
            } else {
                return Response::error('WordPress sync failed with status '.$response->status().":\n".$response->body());
            }
        } catch (\Exception $e) {
            Log::error("WP Sync Failed for site {$siteId}: ".$e->getMessage());

            return Response::error('WordPress sync failed due to exception: '.$e->getMessage());
        }
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'site_id' => $schema->integer()
                ->description('The ID of the site to synchronize')
                ->required(),
        ];
    }
}
