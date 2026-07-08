<?php

namespace App\Modules\CustomerManager\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\CustomerManager\Models\Employee;
use App\Modules\CustomerManager\Models\Workspace;
use App\Modules\CustomerManager\Requests\StoreEmployeeRequest;
use App\Modules\CustomerManager\Requests\StoreWorkspaceRequest;
use App\Modules\CustomerManager\Requests\UpdateEmployeeRequest;
use App\Modules\CustomerManager\Requests\UpdateWorkspaceRequest;
use App\Modules\CustomerManager\Resources\EmployeeResource;
use App\Modules\CustomerManager\Resources\WorkspaceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WorkspaceController extends Controller
{
    /**
     * List workspaces visible to the authenticated user.
     *
     * SuperAdmins/Admins (roles 1, 2) see all workspaces and may filter by
     * customer_id. Tenant users see workspaces of their own customer or
     * workspaces where they are an employee.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = Workspace::query()->with(['employees.user', 'sites']);

        if (in_array($user->role, [1, 2], true)) {
            if ($request->filled('customer_id')) {
                $query->where('customer_id', $request->input('customer_id'));
            }
        } else {
            $query->where(function ($q) use ($user) {
                if ($user->customer_id) {
                    $q->where('customer_id', $user->customer_id);
                }
                $q->orWhereHas('employees', fn ($e) => $e->where('user_id', $user->id));
            });
        }

        $workspaces = $query->latest('id')->paginate((int) $request->input('limit', 15));

        return WorkspaceResource::collection($workspaces)->response();
    }

    /**
     * Create a workspace. Tenant users are always scoped to their own customer.
     */
    public function store(StoreWorkspaceRequest $request): JsonResponse
    {
        $user = Auth::user();
        Gate::forUser($user)->authorize('create', Workspace::class);

        $customerId = in_array($user->role, [1, 2], true)
            ? ($request->input('customer_id') ?? $user->customer_id)
            : $user->customer_id;

        if (! $customerId) {
            abort(422, 'A customer_id is required to create a workspace.');
        }

        $workspace = DB::transaction(function () use ($request, $user, $customerId) {
            $workspace = Workspace::create([
                'name' => $request->input('name'),
                'customer_id' => $customerId,
            ]);

            // The creating tenant user becomes the workspace Owner.
            if (! in_array($user->role, [1, 2], true)) {
                Employee::create([
                    'workspace_id' => $workspace->id,
                    'user_id' => $user->id,
                    'role' => 'Owner',
                ]);
            }

            return $workspace;
        });

        return (new WorkspaceResource($workspace->load(['employees.user', 'sites'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single workspace.
     */
    public function show(int $id): JsonResponse
    {
        $workspace = Workspace::with(['employees.user', 'sites'])->findOrFail($id);
        Gate::authorize('view', $workspace);

        return (new WorkspaceResource($workspace))->response();
    }

    /**
     * Rename a workspace (Owner/Admin or system roles 1-2).
     */
    public function update(UpdateWorkspaceRequest $request, int $id): JsonResponse
    {
        $workspace = Workspace::findOrFail($id);
        Gate::authorize('update', $workspace);

        $workspace->update(['name' => $request->input('name')]);

        return (new WorkspaceResource($workspace->load(['employees.user', 'sites'])))->response();
    }

    /**
     * Delete a workspace (Owner or SuperAdmin only, per WorkspacePolicy).
     */
    public function destroy(int $id): JsonResponse
    {
        $workspace = Workspace::findOrFail($id);
        Gate::authorize('delete', $workspace);

        $workspace->delete();

        return response()->json(['message' => 'Workspace deleted successfully.']);
    }

    /**
     * List employees of a workspace.
     */
    public function employees(int $id): JsonResponse
    {
        $workspace = Workspace::findOrFail($id);
        Gate::authorize('view', $workspace);

        $employees = $workspace->employees()->with('user')->get();

        return EmployeeResource::collection($employees)->response();
    }

    /**
     * Add an employee to a workspace (requires workspace 'update' ability).
     *
     * The user being added must belong to the same customer as the workspace
     * unless the acting user is a system SuperAdmin/Admin.
     */
    public function addEmployee(StoreEmployeeRequest $request, int $id): JsonResponse
    {
        $workspace = Workspace::findOrFail($id);
        Gate::authorize('update', $workspace);

        $actingUser = Auth::user();
        $targetUser = User::findOrFail($request->input('user_id'));

        if (! in_array($actingUser->role, [1, 2], true)
            && $targetUser->customer_id !== $workspace->customer_id) {
            abort(422, 'The user must belong to the same customer as the workspace.');
        }

        if ($workspace->employees()->where('user_id', $targetUser->id)->exists()) {
            abort(422, 'The user is already an employee of this workspace.');
        }

        $employee = Employee::create([
            'workspace_id' => $workspace->id,
            'user_id' => $targetUser->id,
            'role' => $request->input('role'),
        ]);

        return (new EmployeeResource($employee->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Change an employee's workspace role.
     */
    public function updateEmployee(UpdateEmployeeRequest $request, int $id, int $employeeId): JsonResponse
    {
        $workspace = Workspace::findOrFail($id);
        Gate::authorize('update', $workspace);

        $employee = $workspace->employees()->whereKey($employeeId)->firstOrFail();
        $employee->update(['role' => $request->input('role')]);

        return (new EmployeeResource($employee->load('user')))->response();
    }

    /**
     * Remove an employee from a workspace. The last Owner cannot be removed.
     */
    public function removeEmployee(int $id, int $employeeId): JsonResponse
    {
        $workspace = Workspace::findOrFail($id);
        Gate::authorize('update', $workspace);

        $employee = $workspace->employees()->whereKey($employeeId)->firstOrFail();

        $isLastOwner = $employee->role === 'Owner'
            && $workspace->employees()->where('role', 'Owner')->count() === 1;

        if ($isLastOwner) {
            abort(422, 'Cannot remove the last Owner of a workspace.');
        }

        $employee->delete();

        return response()->json(['message' => 'Employee removed from workspace.']);
    }
}
