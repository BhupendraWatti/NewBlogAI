<?php

namespace App\Modules\SiteManager\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SiteManager\Models\Site;
use App\Modules\SiteManager\Requests\StoreSiteRequest;
use App\Modules\SiteManager\Requests\UpdateSiteRequest;
use App\Modules\SiteManager\Jobs\SyncSiteDataJob;
use App\Modules\SiteManager\Events\SiteCreated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    /**
     * Display a listing of client sites.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 15);
        $search = $request->get('search');

        $query = Site::with('promt')->latest();

        if (!empty($search)) {
            $query->where('domain_url', 'like', '%' . $search . '%');
        }

        $sites = $query->paginate($limit);

        return response()->json($sites);
    }

    /**
     * Store a newly created site configuration.
     *
     * @param StoreSiteRequest $request
     * @return JsonResponse
     */
    public function store(StoreSiteRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Ensure trailing slash is trimmed
        $validated['domain_url'] = rtrim($validated['domain_url'], '/');

        $site = Site::create($validated);

        event(new SiteCreated($site));

        return response()->json([
            'message' => 'WordPress site configuration saved successfully.',
            'data' => $site
        ], 201);
    }

    /**
     * Display the specified site details.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $site = Site::with('promt')->find($id);

        if (!$site) {
            return response()->json(['message' => 'Site not found.'], 404);
        }

        return response()->json(['data' => $site]);
    }

    /**
     * Update the specified site configuration.
     *
     * @param UpdateSiteRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateSiteRequest $request, int $id): JsonResponse
    {
        $site = Site::find($id);

        if (!$site) {
            return response()->json(['message' => 'Site not found.'], 404);
        }

        $validated = $request->validated();
        if (isset($validated['domain_url'])) {
            $validated['domain_url'] = rtrim($validated['domain_url'], '/');
        }

        $site->update($validated);

        return response()->json([
            'message' => 'WordPress site configuration updated successfully.',
            'data' => $site
        ]);
    }

    /**
     * Remove the specified site configuration.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $site = Site::find($id);

        if (!$site) {
            return response()->json(['message' => 'Site not found.'], 404);
        }

        $site->delete();

        return response()->json(['message' => 'Site configuration deleted successfully.']);
    }

    /**
     * Trigger manual sync job for the site.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function sync(int $id): JsonResponse
    {
        $site = Site::find($id);

        if (!$site) {
            return response()->json(['message' => 'Site not found.'], 404);
        }

        // Dispatch background job to Horizon queue
        SyncSiteDataJob::dispatch($site);

        return response()->json([
            'message' => 'Synchronization queued successfully.',
            'status' => 'syncing'
        ], 202);
    }
}
