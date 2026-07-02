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

        // Resolve API Key
        $apiKey = $this->resolveApiKey($site);
        if (empty($apiKey)) {
            $error = "API key could not be resolved for site ID: {$site->id}";
            event(new SiteSyncFailed($site, $error));
            throw new \RuntimeException($error);
        }

        // Prepare payload
        $payload = [
            'selected_topics' => $site->selected_topics ?? [],
            'slot'            => $site->slot ?? '12:00',
            'api_key'         => $apiKey,
        ];

        $wpUrl = $domain . '/wp-json/ai-news/v1/sync-data';

        Log::info("Dispatching WP sync request to: {$wpUrl}");

        try {
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
     * Validate the connection to the remote WordPress site.
     *
     * @param Site $site
     * @return bool
     */
    public function validateConnection(Site $site): bool
    {
        $domain = rtrim($site->domain_url, '/');
        if (empty($domain) || !filter_var($domain, FILTER_VALIDATE_URL)) {
            $site->update([
                'status' => 'error',
                'error_log' => "Invalid target URL: {$domain}"
            ]);
            return false;
        }

        $apiKey = $this->resolveApiKey($site);
        $wpUrl = $domain . '/wp-json/ai-news/v1/ping';

        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey
                ])
                ->post($wpUrl, [
                    'api_key' => $apiKey
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $version = $data['version'] ?? '1.0.0';

                $site->update([
                    'status' => 'connected',
                    'plugin_version' => $version,
                    'error_log' => null,
                ]);

                return true;
            }

            $error = "WP connection check status {$response->status()}: " . $response->body();
            $site->update([
                'status' => 'error',
                'error_log' => $error,
            ]);
            return false;

        } catch (\Exception $e) {
            $error = "WP connection exception: " . $e->getMessage();
            $site->update([
                'status' => 'error',
                'error_log' => $error,
            ]);
            return false;
        }
    }

    /**
     * Publish or update a post on remote WordPress site.
     *
     * @param Site $site
     * @param string $title
     * @param string $content
     * @param string $status (draft, publish, pending, future)
     * @param string|null $scheduledAt
     * @param int|null $wpPostId
     * @return array [
     *    'id'   => int (WP Post ID),
     *    'link' => string (Published URL)
     * ]
     * @throws \Exception
     */
    public function publishPost(Site $site, string $title, string $content, string $status = 'draft', ?string $scheduledAt = null, ?int $wpPostId = null): array
    {
        $domain = rtrim($site->domain_url, '/');
        $apiKey = $this->resolveApiKey($site);

        if (empty($apiKey)) {
            throw new \RuntimeException("Authorization token missing for WordPress site ID {$site->id}.");
        }

        // Determine correct endpoint
        $endpoint = $wpPostId 
            ? "{$domain}/wp-json/wp/v2/posts/{$wpPostId}"
            : "{$domain}/wp-json/wp/v2/posts";

        $payload = [
            'title'   => $title,
            'content' => $content,
            'status'  => $status,
        ];

        if ($status === 'future' && $scheduledAt) {
            $payload['date'] = date('Y-m-d\TH:i:s', strtotime($scheduledAt));
        }

        try {
            $response = Http::timeout(20)
                ->withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey
                ])
                ->post($endpoint, $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'id'   => $data['id'] ?? null,
                    'link' => $data['link'] ?? null,
                ];
            }

            throw new \RuntimeException("WordPress REST API error ({$response->status()}): " . $response->body());

        } catch (\Exception $e) {
            Log::error("WordPress publishing failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retrieve post details from remote WordPress site to sync status.
     *
     * @param Site $site
     * @param int $wpPostId
     * @return array|null
     */
    public function getPost(Site $site, int $wpPostId): ?array
    {
        $domain = rtrim($site->domain_url, '/');
        $apiKey = $this->resolveApiKey($site);
        $endpoint = "{$domain}/wp-json/wp/v2/posts/{$wpPostId}";

        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey
                ])
                ->get($endpoint);

            if ($response->successful()) {
                return $response->json();
            }

            if ($response->status() === 404) {
                // Post deleted from WordPress
                return null;
            }

            Log::warning("WordPress post retrieval failed with status {$response->status()}: " . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error("WordPress post retrieval exception: " . $e->getMessage());
            return null;
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
        if (!empty($site->api_key)) {
            return $site->api_key;
        }

        if (!empty($site->key_id)) {
            $keyRecord = keys::find($site->key_id);
            if ($keyRecord) {
                return $keyRecord->key;
            }
        }

        if (is_numeric($site->api_key)) {
            $keyRecord = keys::find((int)$site->api_key);
            if ($keyRecord) {
                return $keyRecord->key;
            }
        }

        return null;
    }
}
