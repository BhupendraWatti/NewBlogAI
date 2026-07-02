<?php

namespace App\Modules\SystemSettings\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SystemSettings\Requests\UpdateSettingsRequest;
use App\Modules\SystemSettings\Services\SystemSettingsService;
use Illuminate\Http\JsonResponse;

class SystemSettingsController extends Controller
{
    public function __construct(
        protected SystemSettingsService $settingsService
    ) {}

    /**
     * Retrieve all system configurations.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'settings' => $this->settingsService->all()
        ]);
    }

    /**
     * Update multiple system configurations in transaction.
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->settingsService->updateMany($request->validated());

        return response()->json([
            'message' => 'System settings updated successfully.',
            'settings' => $this->settingsService->all()
        ]);
    }
}
