<?php

namespace App\Modules\Licensing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Licensing\Requests\ActivateLicenseRequest;
use App\Modules\Licensing\Requests\DeactivateLicenseRequest;
use App\Modules\Licensing\Requests\VerifyLicenseRequest;
use App\Modules\Licensing\Services\LicenseService;
use Illuminate\Http\JsonResponse;

class LicenseController extends Controller
{
    public function __construct(
        protected LicenseService $licenseService
    ) {}

    /**
     * Verify license key and bounds.
     */
    public function verify(VerifyLicenseRequest $request): JsonResponse
    {
        $result = $this->licenseService->verifyLicense(
            $request->input('license_key'),
            $request->input('domain')
        );

        if (! $result['valid']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['reason'],
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'expires_at' => $result['expires_at'],
        ]);
    }

    /**
     * Activate a license key for a domain.
     */
    public function activate(ActivateLicenseRequest $request): JsonResponse
    {
        try {
            $license = $this->licenseService->activateLicense(
                $request->input('license_key'),
                $request->input('domain'),
                $request->input('site_id')
            );

            return response()->json([
                'status' => 'success',
                'message' => 'License key activated successfully.',
                'license_key' => $license->license_key,
                'expires_at' => $license->expires_at ? $license->expires_at->toIso8601String() : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Deactivate a license key.
     */
    public function deactivate(DeactivateLicenseRequest $request): JsonResponse
    {
        try {
            $this->licenseService->deactivateLicense(
                $request->input('license_key'),
                $request->input('domain')
            );

            return response()->json([
                'status' => 'success',
                'message' => 'License key deactivated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
