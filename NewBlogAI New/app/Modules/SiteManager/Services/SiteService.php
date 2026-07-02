<?php

namespace App\Modules\SiteManager\Services;

use App\Modules\SiteManager\Models\Site;
use App\Modules\Operations\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SiteService
{
    public function __construct(
        protected WPClientService $wpClient
    ) {}

    /**
     * Create a website configuration.
     */
    public function createSite(array $data): Site
    {
        try {
            return DB::transaction(function () use ($data) {
                if (!empty($data['is_default'])) {
                    Site::where('is_default', true)->update(['is_default' => false]);
                }

                $site = Site::create($data);

                // Audit Log
                AuditLog::create([
                    'user_id'    => Auth::id(),
                    'event'      => 'website_connected',
                    'new_values' => ['domain_url' => $site->domain_url],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return $site->refresh();
            });
        } catch (\Exception $e) {
            Log::error("Failed to create site: " . $e->getMessage());
            throw new \RuntimeException("Could not create site configuration.", 0, $e);
        }
    }

    /**
     * Update an existing site configuration.
     */
    public function updateSite(Site $site, array $data): Site
    {
        try {
            return DB::transaction(function () use ($site, $data) {
                if (!empty($data['is_default'])) {
                    Site::where('id', '!=', $site->id)
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }

                $oldValues = ['domain_url' => $site->domain_url];
                $site->update($data);

                // Audit Log
                AuditLog::create([
                    'user_id'    => Auth::id(),
                    'event'      => 'website_updated',
                    'old_values' => $oldValues,
                    'new_values' => ['domain_url' => $site->domain_url],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return $site;
            });
        } catch (\Exception $e) {
            Log::error("Failed to update site configuration: " . $e->getMessage());
            throw new \RuntimeException("Could not update site configuration.", 0, $e);
        }
    }

    /**
     * Set default site configuration.
     */
    public function setDefault(Site $site): Site
    {
        if (!$site->is_active) {
            throw new \InvalidArgumentException("Cannot set an inactive website as the default.");
        }

        try {
            return DB::transaction(function () use ($site) {
                Site::where('is_default', true)->update(['is_default' => false]);
                $site->update(['is_default' => true]);

                // Audit Log
                AuditLog::create([
                    'user_id'    => Auth::id(),
                    'event'      => 'website_set_default',
                    'new_values' => ['domain_url' => $site->domain_url],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return $site;
            });
        } catch (\Exception $e) {
            Log::error("Failed to set default site: " . $e->getMessage());
            throw new \RuntimeException("Could not change default site.", 0, $e);
        }
    }

    /**
     * Toggle site status.
     */
    public function toggleActive(Site $site, bool $isActive): Site
    {
        if (!$isActive && $site->is_default) {
            throw new \InvalidArgumentException("Cannot deactivate the default website. Set another website as default first.");
        }

        $site->update(['is_active' => $isActive]);
        return $site;
    }

    /**
     * Validate connection to remote WordPress site.
     */
    public function validateConnection(Site $site): bool
    {
        return $this->wpClient->validateConnection($site);
    }

    /**
     * Delete a site and record an audit log atomically.
     */
    public function deleteSite(Site $site): void
    {
        try {
            DB::transaction(function () use ($site) {
                $site->delete();

                AuditLog::create([
                    'user_id'    => Auth::id(),
                    'event'      => 'website_disconnected',
                    'old_values' => ['domain_url' => $site->getOriginal('domain_url')],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            });
        } catch (\Exception $e) {
            Log::error("Failed to delete site configuration: " . $e->getMessage());
            throw new \RuntimeException("Could not delete site configuration.", 0, $e);
        }
    }
}
