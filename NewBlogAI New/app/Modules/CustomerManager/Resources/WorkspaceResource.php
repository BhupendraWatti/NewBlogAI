<?php

namespace App\Modules\CustomerManager\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'customer_id' => $this->customer_id,
            'sites' => $this->whenLoaded('sites', function () {
                return $this->sites->map(fn ($site) => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'domain_url' => $site->domain_url,
                ])->all();
            }),
            'employees' => EmployeeResource::collection($this->whenLoaded('employees')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
