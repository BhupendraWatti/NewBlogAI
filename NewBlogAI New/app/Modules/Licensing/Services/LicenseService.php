<?php

namespace App\Modules\Licensing\Services;

use App\Modules\CustomerManager\Models\Customer;
use App\Modules\Licensing\Models\PluginLicense;
use App\Modules\SiteManager\Models\Site;
use App\Modules\SubscriptionManager\Exceptions\EntitlementDeniedException;
use App\Modules\SubscriptionManager\Services\EntitlementService;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LicenseService
{
    public function __construct(protected EntitlementService $entitlements) {}

    /**
     * Generate a new unique plugin license.
     */
    public function generateLicense(string $customerId, int $maxInstallations = 1, ?string $expiresAt = null): PluginLicense
    {
        $customer = Customer::findOrFail($customerId);

        try {
            $subscription = $this->entitlements->activeSubscription($customer);
            $maxInstallations = min(
                $maxInstallations,
                $this->entitlements->limits($subscription)['max_wordpress_sites'],
            );
        } catch (EntitlementDeniedException) {
            // Administrative license creation remains available for pre-subscription onboarding.
        }

        return PluginLicense::create([
            'license_key' => 'NB-'.strtoupper(Str::random(16)),
            'customer_id' => $customerId,
            'status' => 'inactive',
            'max_installations' => $maxInstallations,
            'expires_at' => $expiresAt ? now()->parse($expiresAt) : now()->addYear(),
            'installations_count' => 0,
        ]);
    }

    /**
     * Activate a license key for a domain.
     */
    public function activateLicense(string $key, string $domain, ?int $siteId = null): PluginLicense
    {
        $license = PluginLicense::where('license_key', $key)->first();
        if (! $license) {
            throw new InvalidArgumentException('License key not found.');
        }

        if ($license->status === 'revoked') {
            throw new \RuntimeException('This license key has been revoked.');
        }

        if ($license->isExpired()) {
            $license->update(['status' => 'expired']);
            throw new \RuntimeException('This license key has expired.');
        }

        $domain = $this->normalizeDomain($domain);
        $installations = $this->installationsFor($license);
        $existingIndex = collect($installations)->search(
            fn (array $installation): bool => $installation['domain'] === $domain
        );

        if ($existingIndex === false && count($installations) >= $license->max_installations) {
            throw new \RuntimeException('Installation limit reached for this license key.');
        }

        if ($siteId) {
            $site = Site::findOrFail($siteId);
            if ($license->customer_id && $site->customer_id !== $license->customer_id) {
                throw new InvalidArgumentException('The license and website belong to different customers.');
            }

            if ($site->customer) {
                $this->entitlements->activeSubscription($site->customer);
            }
        }

        $entry = [
            'domain' => $domain,
            'site_id' => $siteId,
            'activated_at' => now()->toIso8601String(),
        ];
        if ($existingIndex === false) {
            $installations[] = $entry;
        } else {
            $installations[$existingIndex] = array_merge($installations[$existingIndex], array_filter($entry));
        }

        $license->update([
            'status' => 'active',
            // Keep the legacy columns populated for older plugin clients.
            'domain' => $installations[0]['domain'],
            'site_id' => $installations[0]['site_id'] ?? $license->site_id,
            'installations' => array_values($installations),
            'installations_count' => count($installations),
        ]);

        return $license;
    }

    /**
     * Deactivate a license key.
     */
    public function deactivateLicense(string $key, string $domain): PluginLicense
    {
        $license = PluginLicense::where('license_key', $key)->first();
        if (! $license) {
            throw new InvalidArgumentException('License key not found.');
        }

        $domain = $this->normalizeDomain($domain);
        $installations = $this->installationsFor($license);
        $remaining = array_values(array_filter(
            $installations,
            fn (array $installation): bool => $installation['domain'] !== $domain
        ));

        if (count($remaining) === count($installations)) {
            throw new InvalidArgumentException('License key is not bound to this domain.');
        }

        $license->update([
            'status' => $remaining === [] ? 'inactive' : 'active',
            'domain' => $remaining[0]['domain'] ?? null,
            'site_id' => $remaining[0]['site_id'] ?? null,
            'installations' => $remaining,
            'installations_count' => count($remaining),
        ]);

        return $license;
    }

    /**
     * Verify license key constraints.
     */
    public function verifyLicense(string $key, string $domain): array
    {
        $license = PluginLicense::where('license_key', $key)->first();
        if (! $license) {
            return ['valid' => false, 'reason' => 'License key not found.'];
        }

        if ($license->status === 'revoked') {
            return ['valid' => false, 'reason' => 'License has been revoked.'];
        }

        if ($license->isExpired()) {
            $license->update(['status' => 'expired']);

            return ['valid' => false, 'reason' => 'License has expired.'];
        }

        if ($license->status !== 'active') {
            return ['valid' => false, 'reason' => 'License is inactive.'];
        }

        $domain = $this->normalizeDomain($domain);
        $isInstalled = collect($this->installationsFor($license))->contains(
            fn (array $installation): bool => $installation['domain'] === $domain
        );
        if (! $isInstalled) {
            return ['valid' => false, 'reason' => 'License domain mismatch.'];
        }

        return [
            'valid' => true,
            'expires_at' => $license->expires_at ? $license->expires_at->toIso8601String() : null,
            'max_installations' => $license->max_installations,
        ];
    }

    /**
     * Renew an existing license.
     */
    public function renewLicense(string $key, string $expiresAt): PluginLicense
    {
        $license = PluginLicense::where('license_key', $key)->first();
        if (! $license) {
            throw new InvalidArgumentException('License key not found.');
        }

        $license->update([
            'status' => 'active',
            'expires_at' => now()->parse($expiresAt),
        ]);

        return $license;
    }

    /**
     * Revoke a license.
     */
    public function revokeLicense(string $key): PluginLicense
    {
        $license = PluginLicense::where('license_key', $key)->first();
        if (! $license) {
            throw new InvalidArgumentException('License key not found.');
        }

        $license->update([
            'status' => 'revoked',
        ]);

        return $license;
    }

    /** @return array<int, array{domain:string, site_id:?int, activated_at:?string}> */
    private function installationsFor(PluginLicense $license): array
    {
        $installations = is_array($license->installations) ? $license->installations : [];
        if ($installations === [] && $license->domain) {
            $installations[] = [
                'domain' => $this->normalizeDomain($license->domain),
                'site_id' => $license->site_id,
                'activated_at' => null,
            ];
        }

        return $installations;
    }

    private function normalizeDomain(string $domain): string
    {
        $normalized = rtrim(strtolower(trim($domain)), '/');
        if (! filter_var($normalized, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('A valid license domain URL is required.');
        }

        return $normalized;
    }
}
