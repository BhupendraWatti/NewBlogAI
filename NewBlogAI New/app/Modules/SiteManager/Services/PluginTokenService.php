<?php

namespace App\Modules\SiteManager\Services;

use App\Models\keys;
use App\Models\User;
use App\Modules\CustomerManager\Models\Customer;
use App\Modules\SubscriptionManager\Models\Plan;
use App\Modules\SubscriptionManager\Models\Subscription;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class PluginTokenService
{
    public function issue(User $user): string
    {
        $plainTextToken = Str::random(60);

        keys::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        keys::updateOrCreate(
            ['name' => 'plugin-token-'.$user->id],
            [
                'user_id' => $user->id,
                'key' => Crypt::encryptString($plainTextToken),
                'key_hash' => hash('sha256', $plainTextToken),
                'abilities' => ['plugin:connect', 'plugin:read', 'plugin:write'],
                'last_used_at' => null,
                'expires_at' => now()->addYear(),
                'revoked_at' => null,
            ],
        );

        return $plainTextToken;
    }

    public function authenticate(?string $plainTextToken): ?User
    {
        if (! $plainTextToken) {
            return null;
        }

        $credential = keys::query()
            ->where('key_hash', hash('sha256', $plainTextToken))
            ->whereNull('revoked_at')
            ->first();

        if (! $credential) {
            return null;
        }

        if ($credential->expires_at?->isPast()) {
            $credential->update(['revoked_at' => now()]);

            return null;
        }

        $user = $credential->user;
        if (! $user) {
            return null;
        }

        $credential->forceFill(['last_used_at' => now()])->save();

        return $user;
    }

    public function revoke(User $user): void
    {
        keys::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere('name', 'plugin-token-'.$user->id);
            })
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeCustomerTokens(Customer $customer): void
    {
        $userIds = User::query()
            ->where('customer_id', $customer->id)
            ->orWhere('email', $customer->email)
            ->pluck('id');

        keys::whereIn('user_id', $userIds)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function customerForUser(User $user): ?Customer
    {
        $customer = $user->customer
            ?? Customer::where('email', $user->email)->first();

        if (! $customer) {
            $customer = Customer::create([
                'company_name' => ($user->name ?: 'Customer').' Company',
                'owner_name' => $user->name ?: 'Customer Owner',
                'email' => $user->email,
                'status' => 'active',
            ]);

            $user->update(['customer_id' => $customer->id]);

            // Seed a default subscription so the user has active entitlements immediately
            $plan = Plan::where('status', 'active')->first();
            if ($plan) {
                Subscription::create([
                    'customer_id' => $customer->id,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'billing_period' => 'monthly',
                    'starts_at' => now()->subDay(),
                    'ends_at' => now()->addMonth(),
                    'limits' => $plan->toArray(),
                ]);
            }
        }

        return $customer;
    }
}
