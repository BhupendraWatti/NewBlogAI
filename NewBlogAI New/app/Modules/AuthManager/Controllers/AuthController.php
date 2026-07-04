<?php

namespace App\Modules\AuthManager\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AuthManager\Requests\ChangePasswordRequest;
use App\Modules\AuthManager\Requests\LoginRequest;
use App\Modules\AuthManager\Requests\UpdateProfileRequest;
use App\Modules\AuthManager\Resources\UserResource;
use App\Modules\AuthManager\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Authenticate a user and create a session.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->login($request->validated());

        return response()->json([
            'message' => 'Login successful.',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Terminate the authenticated session.
     */
    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }

    /**
     * Retrieve the authenticated user profile.
     */
    public function me(): UserResource
    {
        return new UserResource(Auth::user());
    }

    /**
     * Update the authenticated user profile.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = Auth::user();
        $updatedUser = $this->authService->updateProfile($user, $request->validated());

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => new UserResource($updatedUser),
        ]);
    }

    /**
     * Update the authenticated user password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = Auth::user();
        $this->authService->changePassword(
            $user,
            $request->input('current_password'),
            $request->input('new_password')
        );

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }
}
