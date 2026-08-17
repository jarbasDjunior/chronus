<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PersonMovement;
use App\Models\VehicleMovement;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    public function presence(Request $r, string $kind)
    {
        $model = $kind === 'person' ? PersonMovement::class : ($kind === 'vehicle' ? VehicleMovement::class : abort(404));
        $owner = $kind === 'person' ? 'person_id' : 'vehicle_id';
        $ids = $model::where('status', 'valid')->selectRaw("$owner, MAX(id) last_id")->groupBy($owner)->pluck('last_id');
        $with = $kind === 'person' ? ['person.category', 'person.department', 'location', 'operator', 'vehicle'] : ['vehicle.people', 'person', 'location', 'operator'];
        $q = $model::with($with)->whereIn('id', $ids)->where('type', 'entry');

        return response()->json($q->latest('occurred_at')->paginate(min((int) $r->query('per_page', 50), 100)));
    }

    public function audit(Request $r)
    {
        abort_unless($r->user()->canDo('audit.view'), 403);

        return response()->json(AuditLog::latest('created_at')->paginate(min((int) $r->query('per_page', 50), 100)));
    }
}
