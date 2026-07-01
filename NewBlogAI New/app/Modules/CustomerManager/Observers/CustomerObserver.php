<?php

namespace App\Modules\CustomerManager\Observers;

use App\Modules\CustomerManager\Models\Customer;
use App\Modules\CustomerManager\Models\CustomerActivity;
use Illuminate\Support\Facades\Auth;

class CustomerObserver
{
    /**
     * Handle the Customer "created" event.
     */
    public function created(Customer $customer): void
    {
        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id'     => Auth::id(),
            'event_type'  => 'created',
            'description' => "Customer '{$customer->company_name}' was registered.",
            'properties'  => $customer->toArray()
        ]);
    }

    /**
     * Handle the Customer "updating" event.
     */
    public function updating(Customer $customer): void
    {
        // Automatically calculate health score based on site connection rates
        // (Just a safe mock formula for deep implementation):
        // Starts at 100, drops if status is expired or suspended
        $score = 100;
        if ($customer->status === 'suspended') {
            $score = 40;
        } elseif ($customer->status === 'expired') {
            $score = 50;
        } elseif ($customer->status === 'cancelled') {
            $score = 20;
        }
        $customer->health_score = $score;
    }

    /**
     * Handle the Customer "updated" event.
     */
    public function updated(Customer $customer): void
    {
        $dirty = $customer->getDirty();
        
        // Remove timestamps from changes list
        unset($dirty['updated_at']);

        if (!empty($dirty)) {
            $original = array_intersect_key($customer->getOriginal(), $dirty);
            
            CustomerActivity::create([
                'customer_id' => $customer->id,
                'user_id'     => Auth::id(),
                'event_type'  => 'updated',
                'description' => "Customer '{$customer->company_name}' details were updated.",
                'properties'  => [
                    'before' => $original,
                    'after'  => $dirty
                ]
            ]);
        }
    }

    /**
     * Handle the Customer "deleted" event.
     */
    public function deleted(Customer $customer): void
    {
        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id'     => Auth::id(),
            'event_type'  => 'deleted',
            'description' => "Customer '{$customer->company_name}' was soft-deleted.",
            'properties'  => ['deleted_at' => $customer->deleted_at]
        ]);
    }

    /**
     * Handle the Customer "restored" event.
     */
    public function restored(Customer $customer): void
    {
        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id'     => Auth::id(),
            'event_type'  => 'restored',
            'description' => "Customer '{$customer->company_name}' was restored.",
            'properties'  => []
        ]);
    }
}
