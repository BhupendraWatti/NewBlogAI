<?php

namespace App\Modules\SiteManager\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Publishing\Models\PublishingLog;
use App\Modules\SiteManager\Models\Site;
use App\Modules\SiteManager\Services\PluginTokenService;
use App\Modules\SiteManager\Services\SiteConfigurationService;
use App\Modules\SiteManager\Services\SiteService;
use App\Modules\SubscriptionManager\Exceptions\EntitlementDeniedException;
use App\Modules\SubscriptionManager\Services\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class WPPluginAPIController extends Controller
{
    public function __construct(
        protected PluginTokenService $tokens,
        protected SiteConfigurationService $configuration,
        protected SiteService $sites,
        protected EntitlementService $entitlements,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password credentials.'], 401);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Authentication successful.',
            'access_token' => $this->tokens->issue($user),
            'token_type' => 'Bearer',
            'expires_in' => 31536000,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function registerWebsite(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (!$user) {
            return $this->unauthorized();
        }

        $validated = $request->validate([
            'domain_url' => ['required', 'url'],
            'name' => ['required', 'string', 'max:255'],
            'api_key' => ['required', 'string'],
            'slot' => ['sometimes', 'string', 'max:50'],
            'publishing_mode' => ['sometimes', 'string', 'in:draft,review,publish'],
            'category_mapping' => ['nullable', 'array'],
            'timezone' => ['sometimes', 'timezone'],
        ]);

        $customer = $this->tokens->customerForUser($user);
        if (!$customer) {
            throw new EntitlementDeniedException(
                'The authenticated account is not linked to a customer.',
                'customer_account',
            );
        }

        if (!$user->customer_id) {
            $user->update(['customer_id' => $customer->id]);
        }

        $domain = rtrim($validated['domain_url'], '/');
        $site = Site::where('domain_url', $domain)->first();

        if ($site && $site->customer_id && $site->customer_id !== $customer->id) {
            throw new InvalidArgumentException('This website is already registered to another customer.');
        }

        $attributes = [
            'customer_id' => $customer->id,
            'domain_url' => $domain,
            'name' => $validated['name'],
            'api_key' => $validated['api_key'],
            'slot' => strtolower($validated['slot'] ?? $site?->slot ?? 'daily'),
            'publishing_mode' => $validated['publishing_mode'] ?? $site?->publishing_mode ?? 'draft',
            'category_mapping' => $validated['category_mapping'] ?? $site?->category_mapping ?? [],
            'timezone' => $validated['timezone'] ?? $site?->timezone ?? $customer->timezone ?? 'UTC',
            'is_active' => true,
            'status' => 'connected',
        ];

        $site = $site
            ? $this->sites->updateSite($site, $attributes)
            : $this->sites->createSite($attributes);

        return response()->json([
            'status' => 'success',
            'message' => 'Website registered successfully on backend.',
            'site_id' => $site->id,
            'connection_status' => 'connected',
            'configuration' => $this->configuration->build($site),
        ], $site->wasRecentlyCreated ? 201 : 200);
    }

    public function configuration(Request $request): JsonResponse
    {
        $site = $this->authenticatedSite($request);
        if ($site instanceof JsonResponse) {
            return $site;
        }

        return response()->json([
            'status' => 'success',
            ...$this->configuration->build($site),
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $site = $this->authenticatedSite($request);
        if ($site instanceof JsonResponse) {
            return $site;
        }

        $configuration = $this->configuration->build($site);

        return response()->json([
            'status' => 'success',
            'connection_status' => $site->status,
            'website_status' => $site->is_active ? 'active' : 'inactive',
            'backend_status' => 'online',
            'last_sync' => $site->last_synced_at?->toIso8601String(),
            'articles_published' => PublishingLog::where('site_id', $site->id)->where('status', 'completed')->count(),
            'pending_queue' => PublishingLog::where('site_id', $site->id)
                ->whereIn('status', ['pending', 'queued', 'processing'])
                ->count(),
            'ai_providers' => collect($configuration['content']['topics'])->pluck('provider')->filter()->unique()->values(),
            'schedules' => $configuration['scheduling']['schedules'],
            'subscription' => $configuration['subscription'],
            'plugin_version' => $site->plugin_version,
            'configuration_hash' => $configuration['configuration_hash'],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $site = $this->authenticatedSite($request);
        if ($site instanceof JsonResponse) {
            return $site;
        }

        $configuration = $this->configuration->build($site);

        return response()->json([
            'status' => 'success',
            'site_id' => $site->id,
            'is_active' => (bool) $site->is_active,
            'connection_status' => $site->status,
            'subscription' => $configuration['subscription'],
            'configuration_hash' => $configuration['configuration_hash'],
        ]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $site = $this->authenticatedSite($request);
        if ($site instanceof JsonResponse) {
            return $site;
        }

        $validated = $request->validate([
            'plugin_version' => ['nullable', 'string', 'max:50'],
            'wp_version' => ['nullable', 'string', 'max:50'],
            'php_version' => ['nullable', 'string', 'max:50'],
        ]);

        $site->update([
            'last_synced_at' => now(),
            'plugin_version' => $validated['plugin_version'] ?? $site->plugin_version,
            'last_sync_status' => 'success',
            'sync_settings' => array_merge($site->sync_settings ?? [], [
                'wp_version' => $validated['wp_version'] ?? null,
                'php_version' => $validated['php_version'] ?? null,
            ]),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Heartbeat logged successfully.',
            'configuration_hash' => $this->configuration->build($site)['configuration_hash'],
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $site = $this->authenticatedSite($request);
        if ($site instanceof JsonResponse) {
            return $site;
        }

        $site->update([
            'last_synced_at' => now(),
            'last_sync_status' => 'success',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Configuration synchronized.',
            'configuration' => $this->configuration->build($site),
        ]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (!$user) {
            return $this->unauthorized();
        }

        $site = $this->siteForUser($request, $user);
        if (!$site) {
            return response()->json(['message' => 'Website not found.'], 404);
        }

        $site->update([
            'status' => 'disconnected',
            'is_active' => false,
            'configuration_version' => $site->configuration_version + 1,
        ]);
        $this->tokens->revoke($user);

        return response()->json([
            'status' => 'success',
            'message' => 'Website disconnected successfully.',
        ]);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (!$user) {
            return $this->unauthorized();
        }

        return response()->json([
            'status' => 'success',
            'access_token' => $this->tokens->issue($user),
            'token_type' => 'Bearer',
            'expires_in' => 31536000,
        ]);
    }

    public function logs(Request $request): JsonResponse
    {
        $site = $this->authenticatedSite($request);
        if ($site instanceof JsonResponse) {
            return $site;
        }

        $logs = PublishingLog::where('site_id', $site->id)
            ->latest()
            ->limit(100)
            ->get([
                'id',
                'generated_content_id',
                'status',
                'wp_post_id',
                'published_url',
                'scheduled_at',
                'completed_at',
                'error_message',
            ]);

        return response()->json(['status' => 'success', 'logs' => $logs]);
    }

    public function publishResult(Request $request): JsonResponse
    {
        $site = $this->authenticatedSite($request);
        if ($site instanceof JsonResponse) {
            return $site;
        }

        $validated = $request->validate([
            'publishing_log_id' => ['required', 'integer'],
            'status' => ['required', 'string', 'in:completed,failed'],
            'wp_post_id' => ['nullable', 'integer'],
            'published_url' => ['nullable', 'url'],
            'error_message' => ['nullable', 'string'],
        ]);

        $log = PublishingLog::where('site_id', $site->id)
            ->findOrFail($validated['publishing_log_id']);
        $log->update([
            'status' => $validated['status'],
            'wp_post_id' => $validated['wp_post_id'] ?? $log->wp_post_id,
            'published_url' => $validated['published_url'] ?? $log->published_url,
            'error_message' => $validated['error_message'] ?? null,
            'completed_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Publishing result recorded.']);
    }

    private function authenticatedSite(Request $request): Site|JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (!$user) {
            return $this->unauthorized();
        }

        return $this->siteForUser($request, $user)
            ?? response()->json(['message' => 'Website not found.'], 404);
    }

    private function authenticatedUser(Request $request): ?User
    {
        return $this->tokens->authenticate(
            $request->bearerToken()
                ?: $request->input('api_key')
                ?: $request->header('X-API-Key'),
        );
    }

    private function siteForUser(Request $request, User $user): ?Site
    {
        $customer = $this->tokens->customerForUser($user);
        if (!$customer) {
            return null;
        }

        $siteUrl = rtrim((string) $request->input('site_url', $request->input('domain_url', '')), '/');

        return Site::where('customer_id', $customer->id)
            ->when($siteUrl !== '', fn ($query) => $query->where('domain_url', $siteUrl))
            ->when($siteUrl === '' && $request->filled('site_id'), fn ($query) => $query->whereKey($request->integer('site_id')))
            ->when($siteUrl === '' && !$request->filled('site_id'), fn ($query) => $query->where('is_default', true))
            ->first();
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json(['message' => 'Unauthorized token.'], 401);
    }
}
