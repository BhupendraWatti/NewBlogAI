<?php

namespace App\Modules\SiteManager\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SiteManager\Events\SiteCreated;
use App\Modules\SiteManager\Jobs\SyncSiteDataJob;
use App\Modules\SiteManager\Models\Site;
use App\Modules\SiteManager\Requests\StoreSiteRequest;
use App\Modules\SiteManager\Requests\UpdateSiteRequest;
use App\Modules\SiteManager\Resources\SiteResource;
use App\Modules\SiteManager\Services\SiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class SiteController extends Controller
{
    public function __construct(
        protected SiteService $siteService
    ) {}

    /**
     * Display a listing of client sites.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $limit = $request->get('limit', 15);
        $search = $request->get('search');
        $status = $request->get('status');

        $query = Site::with('promt')->latest();

        // Tenant Isolation: Only allow SuperAdmin to view all websites
        if (Auth::user()->role !== 1) {
            $query->where('customer_id', Auth::user()->customer_id);
        }

        if (! empty($search)) {
            $query->where('domain_url', 'like', '%'.$search.'%');
        }

        if (! empty($status)) {
            $query->where('status', $status);
        }

        $sites = $query->paginate($limit);

        return SiteResource::collection($sites);
    }

    /**
     * Store a newly created site configuration.
     */
    public function store(StoreSiteRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['domain_url'] = rtrim($validated['domain_url'], '/');

        // Tenant Isolation: Force customer_id to match user's customer unless they are SuperAdmin/Support
        if (Auth::user()->role !== 1 && Auth::user()->role !== 3) {
            $validated['customer_id'] = Auth::user()->customer_id;
        }

        $site = $this->siteService->createSite($validated);

        event(new SiteCreated($site));

        return (new SiteResource($site->load('promt')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified site details.
     */
    public function show(string $id): SiteResource
    {
        $site = $this->findSiteOrFail($id, ['promt']);

        return new SiteResource($site);
    }

    /**
     * Update the specified site configuration.
     */
    public function update(UpdateSiteRequest $request, string $id): SiteResource
    {
        $site = $this->findSiteOrFail($id);
        $validated = $request->validated();

        if (isset($validated['domain_url'])) {
            $validated['domain_url'] = rtrim($validated['domain_url'], '/');
        }

        // Tenant Isolation: Don't allow changing customer_id for non-SuperAdmins
        if (Auth::user()->role !== 1 && Auth::user()->role !== 3) {
            unset($validated['customer_id']);
        }

        $updated = $this->siteService->updateSite($site, $validated);

        return new SiteResource($updated->load('promt'));
    }

    /**
     * Remove the specified site configuration.
     */
    public function destroy(string $id): JsonResponse
    {
        $site = $this->findSiteOrFail($id);

        if ($site->is_default) {
            return response()->json([
                'message' => 'Cannot delete default site. Set another website as default first.',
            ], 422);
        }

        $this->siteService->deleteSite($site);

        return response()->json([
            'message' => 'Site configuration deleted successfully.',
        ]);
    }

    /**
     * Trigger manual sync job for the site.
     */
    public function sync(string $id): JsonResponse
    {
        $site = $this->findSiteOrFail($id);

        // Dispatch background job to Horizon queue
        SyncSiteDataJob::dispatch($site);

        return response()->json([
            'message' => 'Synchronization job dispatched successfully.',
        ]);
    }

    /**
     * Test the connection to the WordPress site.
     */
    public function validateConnection(string $id): JsonResponse
    {
        $site = $this->findSiteOrFail($id);
        $connected = $this->siteService->validateConnection($site);

        if ($connected) {
            return response()->json([
                'message' => 'Connection test successful! Website verified.',
                'status' => 'connected',
                'plugin_version' => $site->plugin_version,
            ]);
        }

        return response()->json([
            'message' => 'Connection test failed. Verify website domain, API key and plugin status.',
            'status' => 'error',
            'error_log' => $site->error_log,
        ], 502);
    }

    /**
     * Set a website configuration as default.
     */
    public function setDefault(string $id): JsonResponse
    {
        $site = $this->findSiteOrFail($id);
        $this->siteService->setDefault($site);

        return response()->json([
            'message' => 'Website is now set as the default destination.',
        ]);
    }

    /**
     * Helper to find a site by ID while enforcing tenant isolation.
     */
    private function findSiteOrFail(string $id, array $relations = []): Site
    {
        $query = Site::query();
        if (! empty($relations)) {
            $query->with($relations);
        }

        if (Auth::user()->role !== 1) {
            $query->where('customer_id', Auth::user()->customer_id);
        }

        return $query->findOrFail($id);
    }
}
