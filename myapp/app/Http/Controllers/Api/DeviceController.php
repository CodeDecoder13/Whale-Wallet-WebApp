<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    /** Registering the same token again just refreshes it (201 the first time, 200 after). */
    public function store(DeviceRequest $request): JsonResponse
    {
        $device = Device::updateOrCreate(
            ['expo_push_token' => $request->expo_push_token],
            ['platform' => $request->platform, 'last_seen_at' => now()],
        );

        return (new DeviceResource($device))->response()->setStatusCode($device->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Device $device): JsonResponse
    {
        $device->delete();

        return response()->json(null, 204);
    }

    /** Turning push off after an app restart: the app still has its token but not the device id. */
    public function forget(DeviceRequest $request): JsonResponse
    {
        Device::where('expo_push_token', $request->expo_push_token)->delete();

        return response()->json(null, 204);
    }
}
