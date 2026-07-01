<?php

namespace App\Modules\SiteManager\Services;

use App\Modules\SiteManager\Models\Site;
use App\Modules\SiteManager\Events\SiteSyncCompleted;
use App\Modules\SiteManager\Events\SiteSyncFailed;
use App\Models\keys;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WPClientService
{
    /**
     * Synchronize a site's configuration with the remote WordPress site.
     *
     * @param Site $site
     * @return array
     * @throws \Exception
     */
    public function sync(Site $site): array
    {
        $domain = rtrim($site->domain_url, '/');
        if (empty($domain) || !filter_var($domain, FILTER_VALIDATE_URL)) {
            $error = "Invalid target URL: {$domain}";
            event(new SiteSyncFailed($site, $error));
            throw new \InvalidArgumentException($error);
        }

        // 1. Resolve API Key
        $apiKey = $this->resolveApiKey($site);
        if (empty($apiKey)) {
            $error = "API key could not be resolved for site ID: {$site->id}";
            event(new SiteSyncFailed($site, $error));
            throw new \RuntimeException($error);
        }

        // 2. Prepare payload
        $payload = [
            'selected_topics' => $site->selected_topics ?? [],
            'slot'            => $site->slot ?? '12:00',
            'api_key'         => $apiKey,
        ];

        $wpUrl = $domain . '/wp-json/ai-news/v1/sync-data';

        Log::info("Dispatching WP sync request to: {$wpUrl}");

        try {
            // 3. Dispatch POST request with timeout
            $response = Http::timeout(15)
                ->withoutVerifying()
                ->post($wpUrl, $payload);

            if ($response->successful()) {
                $responseData = $response->json() ?? [];
                
                // Update database state
                $site->update([
                    'last_synced_at' => now(),
                    'last_sync_status' => 'success',
                    'error_log' => null,
                ]);

                event(new SiteSyncCompleted($site, $responseData));

                return $responseData;
            }

            // HTTP error response (4xx, 5xx)
            $statusCode = $response->status();
            $body = $response->body();
            $error = "WordPress API returned status {$statusCode}: {$body}";
            
            $site->update([
                'last_sync_status' => 'failed',
                'error_log' => $error,
            ]);

            event(new SiteSyncFailed($site, $error));
            throw new \RuntimeException($error);

        } catch (\Exception $e) {
            $error = "Sync exception: " . $e->getMessage();
            
            $site->update([
                'last_sync_status' => 'failed',
                'error_log' => $error,
            ]);

            event(new SiteSyncFailed($site, $error));
            throw $e;
        }
    }

    /**
     * Resolve the active API key for the site.
     *
     * @param Site $site
     * @return string|null
     */
    protected function resolveApiKey(Site $site): ?string
    {
        // Direct key defined on site
        if (!empty($site->api_key)) {
            return $site->api_key;
        }

        // Relation to keys table
        if (!empty($site->key_id)) {
            $keyRecord = keys::find($site->key_id);
            if ($keyRecord) {
                return $keyRecord->key;
            }
        }

        // Backward compatibility fallback if api_key column holds key_id
        if (is_numeric($site->api_key)) {
            $keyRecord = keys::find((int)$site->api_key);
            if ($keyRecord) {
                return $keyRecord->key;
            }
        }

        return null;
    }
}
