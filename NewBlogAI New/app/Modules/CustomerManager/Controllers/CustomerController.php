<?php

namespace App\Modules\CustomerManager\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\CustomerManager\DTOs\CustomerDTO;
use App\Modules\CustomerManager\Requests\StoreCustomerRequest;
use App\Modules\CustomerManager\Requests\UpdateCustomerRequest;
use App\Modules\CustomerManager\Services\CustomerService;
use App\Modules\CustomerManager\Repositories\CustomerRepository;
use App\Modules\CustomerManager\Resources\CustomerResource;
use App\Modules\CustomerManager\Resources\CustomerNoteResource;
use App\Modules\CustomerManager\Resources\CustomerActivityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $service,
        protected CustomerRepository $repository
    ) {}

    /**
     * Display a listing of customers.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Customer::class);

        $limit = $request->get('limit', 15);
        $search = $request->get('search');
        $status = $request->get('status');

        $customers = $this->repository->getPaginated($limit, $search, $status);

        return CustomerResource::collection($customers);
    }

    /**
     * Store a newly created customer.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        Gate::authorize('create', Customer::class);

        $validated = $request->validated();
        $dto = CustomerDTO::fromRequest($validated);

        $customer = $this->service->createCustomer($dto);

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified customer.
     */
    public function show(string $id): CustomerResource
    {
        $customer = $this->repository->find($id);
        if (!$customer) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Customer with ID '{$id}' not found.");
        }

        Gate::authorize('view', $customer);

        // Load relations for deep details
        $customer->load(['notes', 'activities']);

        return new CustomerResource($customer);
    }

    /**
     * Update the specified customer.
     */
    public function update(UpdateCustomerRequest $request, string $id): CustomerResource
    {
        $customer = $this->repository->find($id);
        if (!$customer) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Customer with ID '{$id}' not found.");
        }

        Gate::authorize('update', $customer);

        $validated = $request->validated();
        $updated = $this->service->updateCustomer($id, $validated);

        return new CustomerResource($updated);
    }

    /**
     * Remove the specified customer (Soft Delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $customer = $this->repository->find($id);
        if (!$customer) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Customer with ID '{$id}' not found.");
        }

        Gate::authorize('delete', $customer);

        $this->service->deleteCustomer($id);

        return response()->json([
            'message' => "Customer '{$customer->company_name}' soft-deleted successfully."
        ]);
    }

    /**
     * Restore the specified customer from trash.
     */
    public function restore(string $id): JsonResponse
    {
        $customer = $this->repository->findTrashed($id);
        if (!$customer) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Deleted Customer with ID '{$id}' not found in trash.");
        }

        Gate::authorize('restore', $customer);

        $this->service->restoreCustomer($id);

        return response()->json([
            'message' => "Customer '{$customer->company_name}' restored successfully."
        ]);
    }

    /**
     * Archive the specified customer.
     */
    public function archive(string $id): CustomerResource
    {
        $customer = $this->repository->find($id);
        if (!$customer) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Customer with ID '{$id}' not found.");
        }

        Gate::authorize('update', $customer);

        $archived = $this->service->archiveCustomer($id);

        return new CustomerResource($archived);
    }

    /**
     * Add a note to the specified customer.
     */
    public function storeNote(Request $request, string $id): JsonResponse
    {
        $customer = $this->repository->find($id);
        if (!$customer) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Customer with ID '{$id}' not found.");
        }

        Gate::authorize('addNote', $customer);

        $request->validate([
            'content' => ['required', 'string']
        ]);

        $note = $this->service->addNote($id, $request->input('content'));

        return (new CustomerNoteResource($note))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display activities timeline for the specified customer.
     */
    public function timeline(string $id): AnonymousResourceCollection
    {
        $customer = $this->repository->find($id);
        if (!$customer) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Customer with ID '{$id}' not found.");
        }

        Gate::authorize('view', $customer);

        $activities = $this->service->getTimeline($id);

        return CustomerActivityResource::collection($activities);
    }
}
