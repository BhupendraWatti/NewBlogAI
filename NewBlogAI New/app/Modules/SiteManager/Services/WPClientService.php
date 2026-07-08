<?php

namespace App\Modules\SiteManager\Services;

use App\Models\Key;
use App\Modules\SiteManager\Events\SiteSyncCompleted;
use App\Modules\SiteManager\Events\SiteSyncFailed;
use App\Modules\SiteManager\Models\Site;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WPClientService
{
    public function __construct(protected SiteConfigurationService $configuration) {}

    /**
     * Synchronize a site's configuration with the remote WordPress site.
     *
     * @throws \Exception
     */
    public function sync(Site $site): array
    {
        $domain = rtrim($site->domain_url, '/');
        if (empty($domain) || ! filter_var($domain, FILTER_VALIDATE_URL)) {
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
        $payload = $this->configuration->build($site);
        $payload['api_key'] = $apiKey;

        // Try new plugin endpoint first, fall back to legacy
        $wpUrl = $domain.'/wp-json/newsblogify/v1/sync-data';

        Log::info("Dispatching WP sync request to: {$wpUrl}");

        try {
            $response = Http::timeout(15)
                ->withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                ])
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
            $error = 'Sync exception: '.$e->getMessage();

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
     */
    public function validateConnection(Site $site): bool
    {
        $domain = rtrim($site->domain_url, '/');
        if (empty($domain) || ! filter_var($domain, FILTER_VALIDATE_URL)) {
            $site->update([
                'status' => 'error',
                'error_log' => "Invalid target URL: {$domain}",
            ]);

            return false;
        }

        $apiKey = $this->resolveApiKey($site);
        // Support both new namespace (v2.0+) and legacy namespace
        $wpUrl = $domain.'/wp-json/newsblogify/v1/ping';

        try {
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                ])
                ->post($wpUrl, [
                    'api_key' => $apiKey,
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

            $error = "WP connection check status {$response->status()}: ".$response->body();
            $site->update([
                'status' => 'error',
                'error_log' => $error,
            ]);

            return false;

        } catch (\Exception $e) {
            $error = 'WP connection exception: '.$e->getMessage();
            $site->update([
                'status' => 'error',
                'error_log' => $error,
            ]);

            return false;
        }
    }

    /**
     * Publish or update a post via the plugin's dedicated REST endpoint.
     * Falls back to wp/v2/posts for sites without the v2.0+ plugin.
     *
     * @param  string  $status  (draft, publish, pending, future)
     * @return array ['id' => int, 'link' => string]
     *
     * @throws \Exception
     */
    public function publishPost(
        Site $site,
        string $title,
        string $content,
        string $status = 'draft',
        ?string $scheduledAt = null,
        ?int $wpPostId = null,
        ?int $publishingLogId = null,
        array $categories = [],
        array $tags = [],
        ?string $featuredImageUrl = null,
        array $meta = [],
        ?string $slug = null,
    ): array {
        $domain = rtrim($site->domain_url, '/');
        $apiKey = $this->resolveApiKey($site);

        if (empty($apiKey)) {
            throw new \RuntimeException("Authorization token missing for WordPress site ID {$site->id}.");
        }

        // Prefer newsblogify/v1/publish (plugin v2.0+) for full feature support
        $pluginPublishUrl = "{$domain}/wp-json/newsblogify/v1/publish";
        $pluginPayload = [
            'publishing_log_id' => $publishingLogId,
            'title' => $title,
            'content' => $content,
            'status' => $status,
            'categories' => $categories,
            'tags' => $tags,
            'featured_image_url' => $featuredImageUrl,
            'meta' => $meta,
            'slug' => $slug,
        ];

        if ($status === 'future' && $scheduledAt) {
            $pluginPayload['scheduled_at'] = $scheduledAt;
        }

        try {
            $pluginResponse = Http::timeout(20)
                ->withoutVerifying()
                ->withHeaders(['Authorization' => 'Bearer '.$apiKey])
                ->post($pluginPublishUrl, $pluginPayload);

            if ($pluginResponse->successful()) {
                $data = $pluginResponse->json();

                return ['id' => $data['wp_post_id'] ?? null, 'link' => $data['post_url'] ?? null];
            }

            // If 404 (plugin v1.x — endpoint not yet registered), fall back to wp/v2/posts
            if ($pluginResponse->status() === 404) {
                Log::info("Site {$site->id}: newsblogify/v1/publish not found, falling back to wp/v2/posts.");

                return $this->publishViaWpRestApi($domain, $apiKey, $title, $content, $status, $scheduledAt, $wpPostId, $slug);
            }

            throw new \RuntimeException("WordPress plugin publish error ({$pluginResponse->status()}): ".$pluginResponse->body());
        } catch (\Exception $e) {
            Log::error('WordPress publishing failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Publish directly via the standard WP REST API (fallback).
     */
    protected function publishViaWpRestApi(
        string $domain,
        string $apiKey,
        string $title,
        string $content,
        string $status,
        ?string $scheduledAt = null,
        ?int $wpPostId = null,
        ?string $slug = null,
    ): array {
        $endpoint = $wpPostId
            ? "{$domain}/wp-json/wp/v2/posts/{$wpPostId}"
            : "{$domain}/wp-json/wp/v2/posts";

        $payload = ['title' => $title, 'content' => $content, 'status' => $status];
        if ($status === 'future' && $scheduledAt) {
            $payload['date'] = date('Y-m-d\TH:i:s', strtotime($scheduledAt));
        }
        if (! empty($slug)) {
            $payload['slug'] = $slug;
        }

        $response = Http::timeout(20)
            ->withoutVerifying()
            ->withHeaders(['Authorization' => 'Bearer '.$apiKey])
            ->post($endpoint, $payload);

        if ($response->successful()) {
            $data = $response->json();

            return ['id' => $data['id'] ?? null, 'link' => $data['link'] ?? null];
        }

        throw new \RuntimeException("WordPress REST API error ({$response->status()}): ".$response->body());
    }

    /**
     * Retrieve post details from remote WordPress site to sync status.
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
                    'Authorization' => 'Bearer '.$apiKey,
                ])
                ->get($endpoint);

            if ($response->successful()) {
                return $response->json();
            }

            if ($response->status() === 404) {
                // Post deleted from WordPress
                return null;
            }

            Log::warning("WordPress post retrieval failed with status {$response->status()}: ".$response->body());

            return null;

        } catch (\Exception $e) {
            Log::error('WordPress post retrieval exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Resolve the active API key for the site.
     */
    protected function resolveApiKey(Site $site): ?string
    {
        // api_key column is cast as 'encrypted' on the Site model, so
        // Eloquent automatically decrypts it when accessed via $site->api_key.
        if (! empty($site->api_key)) {
            // Safely try to return the decrypted value
            try {
                // If already a plaintext string (e.g. set directly), just return it
                return $site->api_key;
            } catch (\Throwable $e) {
                Log::warning("Failed to resolve api_key for site {$site->id}: ".$e->getMessage());
            }
        }

        if (! empty($site->key_id)) {
            $keyRecord = Key::find($site->key_id);
            if ($keyRecord) {
                // The keys table `key` column is TEXT and stores encrypted ciphertext;
                // decrypt it to get the original token.
                try {
                    return Crypt::decryptString($keyRecord->key);
                } catch (\Throwable $e) {
                    // Already plaintext token (legacy records)
                    return $keyRecord->key;
                }
            }
        }

        return null;
    }
}
