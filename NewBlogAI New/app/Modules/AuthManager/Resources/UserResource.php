<?php

namespace App\Modules\AuthManager\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email,
            'role'  => $this->role, // 1 = Super Admin, 2 = Admin, 3 = Support / Partner
            'customer_id' => $this->customer_id,
            'role_label' => match ((int) $this->role) {
                1 => 'Super Admin',
                2 => 'Admin',
                3 => 'Support / Partner',
                default => 'Unknown'
            },
            'created_at' => $this->created_at,
        ];
    }
}
