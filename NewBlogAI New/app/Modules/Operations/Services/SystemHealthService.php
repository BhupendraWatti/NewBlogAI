<?php

namespace App\Modules\Operations\Services;

use App\Modules\AIProviderManager\Models\AIProvider;
use App\Modules\SiteManager\Models\Site;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SystemHealthService
{
    /**
     * Run unified system diagnostics.
     */
    public function getSystemHealth(): array
    {
        return [
            'status' => $this->overallStatus(),
            'timestamp' => now()->toIso8601String(),
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'websites' => $this->checkWebsites(),
            'ai_engines' => $this->checkAIEngines(),
        ];
    }

    /**
     * Verify database connection.
     */
    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'healthy', 'message' => 'Connection established.'];
        } catch (\Exception $e) {
            Log::error('Health check - Database failed: '.$e->getMessage());

            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    /**
     * Verify cache availability.
     */
    protected function checkCache(): array
    {
        try {
            $testKey = 'health_ping_'.uniqid();
            Cache::put($testKey, 'pong', 10);
            $val = Cache::get($testKey);
            Cache::forget($testKey);

            if ($val === 'pong') {
                return ['status' => 'healthy', 'message' => 'Read/Write succeeded.'];
            }

            return ['status' => 'unhealthy', 'message' => 'Cache mismatch.'];
        } catch (\Exception $e) {
            Log::error('Health check - Cache failed: '.$e->getMessage());

            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    /**
     * Verify storage directories writable.
     */
    protected function checkStorage(): array
    {
        try {
            $filename = 'health_test_'.uniqid().'.txt';
            Storage::disk('local')->put($filename, 'test');
            Storage::disk('local')->delete($filename);

            return ['status' => 'healthy', 'message' => 'Writable permissions verified.'];
        } catch (\Exception $e) {
            Log::error('Health check - Storage failed: '.$e->getMessage());

            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    /**
     * List WordPress websites state.
     */
    protected function checkWebsites(): array
    {
        $total = Site::count();
        $connected = Site::where('status', 'connected')->count();
        $errors = Site::where('status', 'error')->count();

        return [
            'status' => ($errors > 0) ? 'warning' : 'healthy',
            'total' => $total,
            'connected' => $connected,
            'errors' => $errors,
        ];
    }

    /**
     * List AI providers configs availability.
     */
    protected function checkAIEngines(): array
    {
        $enabled = AIProvider::where('is_enabled', true)->count();
        $configured = AIProvider::where('is_enabled', true)->whereNotNull('api_key')->count();

        return [
            'status' => ($configured > 0) ? 'healthy' : 'unhealthy',
            'enabled' => $enabled,
            'configured' => $configured,
        ];
    }

    /**
     * Evaluate overall state.
     */
    protected function overallStatus(): string
    {
        $db = $this->checkDatabase()['status'];
        $cache = $this->checkCache()['status'];
        $storage = $this->checkStorage()['status'];

        if ($db === 'healthy' && $cache === 'healthy' && $storage === 'healthy') {
            return 'healthy';
        }

        return 'unhealthy';
    }
}
