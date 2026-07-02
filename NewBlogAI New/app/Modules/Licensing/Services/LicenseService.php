<?php

namespace App\Modules\Licensing\Services;

use App\Modules\Licensing\Models\PluginLicense;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LicenseService
{
    /**
     * Generate a new unique plugin license.
     */
    public function generateLicense(string $customerId, int $maxInstallations = 1, ?string $expiresAt = null): PluginLicense
    {
        return PluginLicense::create([
            'license_key'         => 'NB-' . strtoupper(Str::random(16)),
            'customer_id'         => $customerId,
            'status'              => 'inactive',
            'max_installations'   => $maxInstallations,
            'expires_at'          => $expiresAt ? now()->parse($expiresAt) : now()->addYear(),
            'installations_count' => 0,
        ]);
    }

    /**
     * Activate a license key for a domain.
     */
    public function activateLicense(string $key, string $domain, ?int $siteId = null): PluginLicense
    {
        $license = PluginLicense::where('license_key', $key)->first();
        if (!$license) {
            throw new InvalidArgumentException("License key not found.");
        }

        if ($license->status === 'revoked') {
            throw new \RuntimeException("This license key has been revoked.");
        }

        if ($license->isExpired()) {
            $license->update(['status' => 'expired']);
            throw new \RuntimeException("This license key has expired.");
        }

        if ($license->installations_count >= $license->max_installations && $license->domain !== $domain) {
            throw new \RuntimeException("Installation limit reached for this license key.");
        }

        $license->update([
            'status'              => 'active',
            'domain'              => $domain,
            'site_id'             => $siteId ?? $license->site_id,
            'installations_count' => 1,
        ]);

        return $license;
    }

    /**
     * Deactivate a license key.
     */
    public function deactivateLicense(string $key, string $domain): PluginLicense
    {
        $license = PluginLicense::where('license_key', $key)->first();
        if (!$license) {
            throw new InvalidArgumentException("License key not found.");
        }

        if ($license->domain !== $domain) {
            throw new InvalidArgumentException("License key is not bound to this domain.");
        }

        $license->update([
            'status'              => 'inactive',
            'domain'              => null,
            'installations_count' => 0,
        ]);

        return $license;
    }

    /**
     * Verify license key constraints.
     */
    public function verifyLicense(string $key, string $domain): array
    {
        $license = PluginLicense::where('license_key', $key)->first();
        if (!$license) {
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

        if ($license->domain !== $domain) {
            return ['valid' => false, 'reason' => 'License domain mismatch.'];
        }

        return [
            'valid'             => true,
            'expires_at'        => $license->expires_at ? $license->expires_at->toIso8601String() : null,
            'max_installations' => $license->max_installations,
        ];
    }

    /**
     * Renew an existing license.
     */
    public function renewLicense(string $key, string $expiresAt): PluginLicense
    {
        $license = PluginLicense::where('license_key', $key)->first();
        if (!$license) {
            throw new InvalidArgumentException("License key not found.");
        }

        $license->update([
            'status'     => 'active',
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
        if (!$license) {
            throw new InvalidArgumentException("License key not found.");
        }

        $license->update([
            'status' => 'revoked',
        ]);

        return $license;
    }
}
