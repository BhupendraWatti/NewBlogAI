<?php

namespace App\Modules\AuthManager\Services;

use App\Models\User;
use App\Modules\AuthManager\Models\AuthActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Authenticate user session.
     *
     * @throws ValidationException
     */
    public function login(array $credentials): User
    {
        $ip = Request::ip();
        $userAgent = Request::header('User-Agent');

        if (!Auth::attempt($credentials)) {
            // Log failed login event
            $this->logActivity(null, 'login_failed', "Failed login attempt for email: " . ($credentials['email'] ?? 'unknown'), [
                'email' => $credentials['email'] ?? null,
            ]);

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        // Log successful login event
        $this->logActivity($user->id, 'login', "Successful login for user: {$user->email}");

        return $user;
    }

    /**
     * Logout current session.
     */
    public function logout(): void
    {
        $user = Auth::user();

        if ($user) {
            $this->logActivity($user->id, 'logout', "Successful logout for user: {$user->email}");
            Auth::guard('web')->logout();
            Request::session()->invalidate();
            Request::session()->regenerateToken();
        }
    }

    /**
     * Update user profile information.
     */
    public function updateProfile(User $user, array $data): User
    {
        $oldName = $user->name;
        $oldEmail = $user->email;

        $user->update($data);

        $changes = [];
        if ($oldName !== $user->name) $changes['name'] = ['from' => $oldName, 'to' => $user->name];
        if ($oldEmail !== $user->email) $changes['email'] = ['from' => $oldEmail, 'to' => $user->email];

        if (count($changes) > 0) {
            $this->logActivity($user->id, 'profile_updated', "Profile updated.", ['changes' => $changes]);
        }

        return $user;
    }

    /**
     * Change user password.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (!Hash::check($currentPassword, $user->password)) {
            $this->logActivity($user->id, 'password_change_failed', "Failed password change attempt: current password mismatch.");
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match our records.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($newPassword)
        ]);

        $this->logActivity($user->id, 'password_changed', "Password changed successfully.");
    }

    /**
     * Log authentication activity to database and Laravel logs.
     */
    public function logActivity(?int $userId, string $eventType, string $description, ?array $properties = null): void
    {
        try {
            AuthActivity::create([
                'user_id'     => $userId,
                'event_type'  => $eventType,
                'ip_address'  => Request::ip(),
                'user_agent'  => Request::header('User-Agent'),
                'description' => $description,
                'properties'  => $properties,
            ]);

            Log::info("AuthEvent: [{$eventType}] - {$description}", [
                'user_id' => $userId,
                'ip' => Request::ip()
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to log auth activity: " . $e->getMessage());
        }
    }
}
