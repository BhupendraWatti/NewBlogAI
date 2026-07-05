<?php

namespace App\Modules\AuthManager\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AuthManager\Requests\CreateUserRequest;
use App\Modules\AuthManager\Requests\UpdateUserRequest;
use App\Modules\AuthManager\Resources\UserResource;
use App\Modules\Operations\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of system users.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query();

        // Tenant Isolation: Only allow SuperAdmin to view all users
        if (Auth::user()->role !== 1) {
            $query->where('customer_id', Auth::user()->customer_id);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        return UserResource::collection($query->paginate($request->input('limit', 15)));
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        // Tenant Isolation: Force user creation within the same customer tenant unless SuperAdmin/Support
        if (Auth::user()->role !== 1 && Auth::user()->role !== 3) {
            $data['customer_id'] = Auth::user()->customer_id;
        }

        $user = User::create($data);

        // Audit Log
        AuditLog::create([
            'user_id' => Auth::id(),
            'event' => 'user_created',
            'new_values' => ['user_id' => $user->id, 'email' => $user->email],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified user.
     */
    public function show(string $id): UserResource
    {
        $user = $this->findUserOrFail($id);

        return new UserResource($user);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UpdateUserRequest $request, string $id): UserResource
    {
        $user = $this->findUserOrFail($id);
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Tenant Isolation: Ensure customer_id cannot be changed for non-SuperAdmins
        if (Auth::user()->role !== 1 && Auth::user()->role !== 3) {
            unset($data['customer_id']);
        }

        $oldValues = $user->only(['name', 'email', 'role']);
        $user->update($data);

        // Audit Log
        AuditLog::create([
            'user_id' => Auth::id(),
            'event' => 'user_updated',
            'old_values' => $oldValues,
            'new_values' => $user->only(['name', 'email', 'role']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return new UserResource($user);
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $this->findUserOrFail($id);

        if ((int) $user->id === (int) Auth::id()) {
            return response()->json(['message' => 'Cannot delete your own user account.'], 422);
        }

        $oldValues = $user->only(['name', 'email', 'role']);
        $user->delete();

        // Audit Log
        AuditLog::create([
            'user_id' => Auth::id(),
            'event' => 'user_deleted',
            'old_values' => $oldValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['message' => 'User deleted successfully.']);
    }

    /**
     * Helper to find a user by ID while enforcing tenant isolation.
     */
    private function findUserOrFail(string $id): User
    {
        $query = User::query();
        if (Auth::user()->role !== 1) {
            $query->where('customer_id', Auth::user()->customer_id);
        }

        return $query->findOrFail($id);
    }
}
