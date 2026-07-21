<?php

namespace App\Modules\AIProviderManager\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AIProviderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Aggregate real token usage and cost metrics directly from database logs.
        // Dual-match: new logs use provider_id FK; legacy logs only have the provider string key.
        $stats = \App\Modules\ContentGeneration\Models\AIRequestLog::query()
            ->where(function ($q) {
                $q->where('provider_id', $this->id)
                  ->orWhere(function ($q2) {
                      $q2->whereNull('provider_id')
                         ->where('provider', $this->provider_key);
                  });
            })
            ->selectRaw('
                COALESCE(SUM(total_tokens), 0) as total_tokens,
                COALESCE(SUM(prompt_tokens), 0) as prompt_tokens,
                COALESCE(SUM(completion_tokens), 0) as completion_tokens,
                COALESCE(SUM(estimated_cost), 0) as total_cost,
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = \'success\' THEN 1 ELSE 0 END) as success_requests,
                SUM(CASE WHEN status != \'success\' THEN 1 ELSE 0 END) as error_requests
            ')
            ->first();

        $totalTokensUsed       = (int)   ($stats->total_tokens  ?? 0);
        $promptTokensUsed      = (int)   ($stats->prompt_tokens ?? 0);
        $completionTokensUsed  = (int)   ($stats->completion_tokens ?? 0);
        $totalCost             = (float) ($stats->total_cost ?? 0);
        $totalRequests         = (int)   ($stats->total_requests ?? 0);
        $successRequests       = (int)   ($stats->success_requests ?? 0);
        $errorRequests         = (int)   ($stats->error_requests ?? 0);

        // Effective remaining credits calculation
        $creditsTotal = $this->credits_total;
        $creditsRemaining = $this->credits_remaining;

        if ($creditsTotal !== null && $creditsRemaining === null) {
            $creditsRemaining = max(0, $creditsTotal - $totalTokensUsed);
        }

        return [
            'id' => $this->id,
            'provider_key' => $this->provider_key,
            'name' => $this->name,
            'has_api_key' => ! empty($this->api_key),
            'api_key' => $this->getMaskedApiKey(),
            'default_model' => $this->default_model,
            'is_default' => (bool) $this->is_default,
            'is_enabled' => (bool) $this->is_enabled,
            'credits_total' => $creditsTotal,
            'credits_remaining' => $creditsRemaining,
            'tokens_used_total' => $totalTokensUsed,
            'prompt_tokens_total' => $promptTokensUsed,
            'completion_tokens_total' => $completionTokensUsed,
            'total_requests' => $totalRequests,
            'success_requests' => $successRequests,
            'error_requests' => $errorRequests,
            'estimated_cost_total' => round($totalCost, 4),
            'reset_at' => $this->reset_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'tier' => $this->tier ?? 'free',
            'priority' => (int) ($this->priority ?? 0),
            'status' => $this->status ?? 'healthy',
            'last_failure' => $this->last_failure?->toIso8601String(),
            'cooldown_until' => $this->cooldown_until?->toIso8601String(),
            'last_used' => $this->last_used?->toIso8601String(),
            'error_count' => (int) ($this->error_count ?? 0),
            'success_count' => (int) ($this->success_count ?? 0),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
