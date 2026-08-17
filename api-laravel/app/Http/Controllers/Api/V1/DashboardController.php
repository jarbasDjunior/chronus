<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PersonMovement;
use App\Models\VehicleMovement;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $personIds = PersonMovement::where('status', 'valid')->selectRaw('person_id, MAX(id) last_id')->groupBy('person_id')->pluck('last_id');
        $vehicleIds = VehicleMovement::where('status', 'valid')->selectRaw('vehicle_id, MAX(id) last_id')->groupBy('vehicle_id')->pluck('last_id');
        $insidePeople = PersonMovement::whereIn('id', $personIds)->where('type', 'entry')->count();
        $insideVehicles = VehicleMovement::whereIn('id', $vehicleIds)->where('type', 'entry')->count();

        return response()->json(['data' => ['people_inside' => $insidePeople, 'vehicles_inside' => $insideVehicles, 'movements_today' => PersonMovement::whereDate('occurred_at', today())->count() + VehicleMovement::whereDate('occurred_at', today())->count(), 'latest' => PersonMovement::with('person', 'location', 'operator')->latest('occurred_at')->limit(10)->get()]]);
    }
}
