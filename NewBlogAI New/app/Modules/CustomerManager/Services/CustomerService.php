<?php

namespace App\Modules\CustomerManager\Services;

use App\Modules\CustomerManager\DTOs\CustomerDTO;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\CustomerManager\Models\CustomerActivity;
use App\Modules\CustomerManager\Models\CustomerNote;
use App\Modules\CustomerManager\Repositories\CustomerRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerService
{
    public function __construct(
        protected CustomerRepository $repository
    ) {}

    /**
     * Create a new customer inside a database transaction.
     */
    public function createCustomer(CustomerDTO $dto): Customer
    {
        // Fail Fast invariant checks
        if (empty($dto->companyName) || empty($dto->email)) {
            throw new \InvalidArgumentException('Company name and email address are required fields.');
        }

        try {
            return DB::transaction(function () use ($dto) {
                $customer = $this->repository->create($dto->toArray());

                // Register initial note if provided
                if ($dto->notes) {
                    CustomerNote::create([
                        'customer_id' => $customer->id,
                        'user_id' => Auth::id() ?? 1, // Fallback to System ID if CLI/Seeder
                        'content' => $dto->notes,
                    ]);

                    CustomerActivity::create([
                        'customer_id' => $customer->id,
                        'user_id' => Auth::id(),
                        'event_type' => 'note_added',
                        'description' => 'Initial configuration note registered.',
                        'properties' => ['content' => $dto->notes],
                    ]);
                }

                return $customer;
            });
        } catch (\Exception $e) {
            Log::error('Failed to register customer: '.$e->getMessage(), [
                'dto' => $dto->toArray(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Could not register customer. System encountered a database error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Update customer details.
     */
    public function updateCustomer(string $id, array $data): Customer
    {
        $customer = $this->repository->find($id);
        if (! $customer) {
            throw new ModelNotFoundException("Customer record with ID '{$id}' does not exist.");
        }

        try {
            return $this->repository->update($customer, $data);
        } catch (\Exception $e) {
            Log::error("Failed to update customer ID {$id}: ".$e->getMessage());
            throw new \RuntimeException('Could not update customer. Database transaction failed.', 0, $e);
        }
    }

    /**
     * Soft delete a customer.
     */
    public function deleteCustomer(string $id): void
    {
        $customer = $this->repository->find($id);
        if (! $customer) {
            throw new ModelNotFoundException("Customer with ID '{$id}' not found.");
        }

        $this->repository->delete($customer);
    }

    /**
     * Restore a soft-deleted customer.
     */
    public function restoreCustomer(string $id): void
    {
        $customer = $this->repository->findTrashed($id);
        if (! $customer) {
            throw new ModelNotFoundException("Deleted Customer with ID '{$id}' not found in trash.");
        }

        $this->repository->restore($customer);
    }

    /**
     * Archive a customer by updating their status.
     */
    public function archiveCustomer(string $id): Customer
    {
        $customer = $this->repository->find($id);
        if (! $customer) {
            throw new ModelNotFoundException("Customer with ID '{$id}' not found.");
        }

        return $this->repository->update($customer, ['status' => 'archived']);
    }

    /**
     * Add a note to a customer.
     */
    public function addNote(string $customerId, string $content): CustomerNote
    {
        if (empty(trim($content))) {
            throw new \InvalidArgumentException('Note content cannot be empty.');
        }

        $customer = $this->repository->find($customerId);
        if (! $customer) {
            throw new ModelNotFoundException("Customer with ID '{$customerId}' not found.");
        }

        try {
            return DB::transaction(function () use ($customer, $content) {
                $note = CustomerNote::create([
                    'customer_id' => $customer->id,
                    'user_id' => Auth::id() ?? 1,
                    'content' => $content,
                ]);

                CustomerActivity::create([
                    'customer_id' => $customer->id,
                    'user_id' => Auth::id(),
                    'event_type' => 'note_added',
                    'description' => 'Note added by staff member.',
                    'properties' => ['content' => $content],
                ]);

                return $note;
            });
        } catch (\Exception $e) {
            Log::error("Failed to add note to customer ID {$customerId}: ".$e->getMessage());
            throw new \RuntimeException('Could not save note. Database error occurred.', 0, $e);
        }
    }

    /**
     * Get paginated logs/activities timeline of a customer.
     */
    public function getTimeline(string $customerId, int $limit = 10): LengthAwarePaginator
    {
        $customer = $this->repository->find($customerId);
        if (! $customer) {
            throw new ModelNotFoundException("Customer with ID '{$customerId}' not found.");
        }

        return CustomerActivity::where('customer_id', $customerId)
            ->latest('id')
            ->paginate($limit);
    }
}
