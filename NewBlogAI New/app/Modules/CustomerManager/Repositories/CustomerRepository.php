<?php

namespace App\Modules\CustomerManager\Repositories;

use App\Modules\CustomerManager\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CustomerRepository
{
    /**
     * Get paginated list of customers with search and filter criteria.
     */
    public function getPaginated(int $limit = 15, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        $query = Customer::query()->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('owner_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($limit);
    }

    /**
     * Find a customer record by ID.
     */
    public function find(string $id): ?Customer
    {
        return Customer::find($id);
    }

    /**
     * Find a soft-deleted customer record by ID.
     */
    public function findTrashed(string $id): ?Customer
    {
        return Customer::onlyTrashed()->find($id);
    }

    /**
     * Create a new customer record.
     */
    public function create(array $data): Customer
    {
        return Customer::create($data);
    }

    /**
     * Update an existing customer record.
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        return $customer;
    }

    /**
     * Soft delete a customer.
     */
    public function delete(Customer $customer): bool
    {
        return $customer->delete();
    }

    /**
     * Restore a soft-deleted customer.
     */
    public function restore(Customer $customer): bool
    {
        return $customer->restore();
    }
}
