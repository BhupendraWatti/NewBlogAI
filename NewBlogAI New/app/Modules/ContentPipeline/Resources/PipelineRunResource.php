<?php

namespace App\Modules\ContentPipeline\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PipelineRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $properties = $this->properties ?? [];
        $telemetry = $properties['telemetry'] ?? [];

        // Refresh elapsed/remaining time at read time so the client receives a
        // live measurement even while a non-streaming provider call is in flight.
        if ($telemetry !== [] && $this->started_at) {
            $terminal = in_array($this->status, ['ready', 'completed', 'failed'], true);
            $end = $terminal && $this->completed_at ? $this->completed_at : now();
            $elapsedMs = max(
                (int) ($telemetry['elapsed_ms'] ?? 0),
                (int) $this->started_at->diffInMilliseconds($end),
            );
            $timeoutMs = (int) ($telemetry['timeout_ms'] ?? 0);
            $telemetry['elapsed_ms'] = $elapsedMs;
            $telemetry['remaining_ms'] = max(0, $timeoutMs - $elapsedMs);
        }

        return [
            'id' => $this->id,
            'pipeline_id' => $this->pipeline_id,
            'status' => $this->status,
            'retry_count' => $this->retry_count,
            'error_message' => $this->error_message,
            'properties' => $properties,
            'telemetry' => $telemetry,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
        ];
    }
}
