<?php

namespace App\Modules\ScheduleManager\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ScheduleManager\Models\PublishingSchedule;
use App\Modules\ScheduleManager\Requests\StoreScheduleRequest;
use App\Modules\ScheduleManager\Resources\ScheduleResource;
use App\Modules\ScheduleManager\Services\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScheduleController extends Controller
{
    public function __construct(protected ScheduleService $schedules) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PublishingSchedule::query()->latest();

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->integer('site_id'));
        }

        return ScheduleResource::collection($query->paginate($request->integer('limit', 15)));
    }

    public function store(StoreScheduleRequest $request): JsonResponse
    {
        return (new ScheduleResource($this->schedules->create($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PublishingSchedule $schedule): ScheduleResource
    {
        return new ScheduleResource($schedule);
    }

    public function update(StoreScheduleRequest $request, PublishingSchedule $schedule): ScheduleResource
    {
        return new ScheduleResource($this->schedules->update($schedule, $request->validated()));
    }

    public function destroy(PublishingSchedule $schedule): JsonResponse
    {
        $this->schedules->delete($schedule);

        return response()->json(['message' => 'Schedule deleted successfully.']);
    }
}
