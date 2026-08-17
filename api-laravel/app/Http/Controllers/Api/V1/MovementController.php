<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PersonMovement;
use App\Models\VehicleMovement;
use App\Services\MovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MovementController extends Controller
{
    public function __construct(private MovementService $service) {}

    private function rules(string $kind)
    {
        return ['uuid' => 'required|uuid', ($kind === 'person' ? 'person_id' : 'vehicle_id') => 'required|integer', 'type' => 'required|in:entry,exit', 'occurred_at' => 'nullable|date', 'access_location_id' => 'required|exists:access_locations,id', 'person_id' => $kind === 'vehicle' ? 'nullable|exists:people,id' : 'required|exists:people,id', 'vehicle_id' => $kind === 'person' ? 'nullable|exists:vehicles,id' : 'required|exists:vehicles,id', 'notes' => 'nullable|string', 'origin' => 'nullable|in:online,offline', 'force' => 'nullable|boolean', 'correction_reason' => 'nullable|string|min:5'];
    }

    public function store(Request $r, string $kind)
    {
        abort_unless(in_array($kind, ['person', 'vehicle']), 404);
        $d = $r->validate($this->rules($kind));
        $m = $this->service->$kind($d, $r->user(), $r->ip(), $r->userAgent());

        return response()->json(['data' => $m], 201);
    }

    public function index(Request $r, string $kind)
    {
        $model = $kind === 'person' ? PersonMovement::class : VehicleMovement::class;
        $q = $model::with($kind === 'person' ? ['person.category', 'location', 'operator', 'vehicle'] : ['vehicle', 'person', 'location', 'operator']);
        foreach (['type', 'access_location_id', 'operator_id', 'status'] as $f) {
            if ($r->filled($f)) {
                $q->where($f, $r->$f);
            }
        }if ($r->filled('from')) {
            $q->where('occurred_at', '>=', $r->from);
        }if ($r->filled('to')) {
            $q->where('occurred_at', '<=', $r->to);
        }

        return response()->json($q->latest('occurred_at')->paginate(min((int) $r->query('per_page', 20), 100)));
    }

    public function sync(Request $r)
    {
        $data = $r->validate(['batch_uuid' => 'nullable|uuid', 'movements' => 'required|array|max:100', 'movements.*.kind' => 'required|in:person,vehicle', 'movements.*.payload' => 'required|array']);
        $out = [];
        foreach ($data['movements'] as $i => $x) {
            try {
                $payload = validator($x['payload'], $this->rules($x['kind']))->validate();
                $out[] = ['uuid' => $payload['uuid'], 'status' => 'synced', 'data' => $this->service->{$x['kind']}($payload, $r->user(), $r->ip(), $r->userAgent())];
            } catch (\Throwable $e) {
                $out[] = ['index' => $i, 'uuid' => $x['payload']['uuid'] ?? null, 'status' => 'error', 'message' => $e->getMessage()];
            }
        }

        DB::table('sync_logs')->insert(['user_id' => $r->user()->id, 'batch_uuid' => $data['batch_uuid'] ?? Str::uuid(), 'received' => count($data['movements']), 'processed' => collect($out)->where('status', 'synced')->count(), 'failed' => collect($out)->where('status', 'error')->count(), 'errors' => json_encode(collect($out)->where('status', 'error')->values()), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => $out]);
    }

    public function correct(Request $r, string $kind, int $id)
    {
        abort_unless($r->user()->canDo('movements.correct'), 403);
        $model = $kind === 'person' ? PersonMovement::class : ($kind === 'vehicle' ? VehicleMovement::class : abort(404));
        $data = $r->validate(['uuid' => 'required|uuid', 'type' => 'required|in:entry,exit', 'occurred_at' => 'required|date', 'access_location_id' => 'required|exists:access_locations,id', 'correction_reason' => 'required|string|min:5', 'notes' => 'nullable|string']);
        $movement = DB::transaction(function () use ($model, $id, $kind, $data, $r) {
            $original = $model::lockForUpdate()->findOrFail($id);
            abort_if($original->status !== 'valid', 422, 'Somente registros válidos podem ser corrigidos.');
            $original->update(['status' => 'corrected', 'correction_reason' => $data['correction_reason']]);
            $payload = [...$data, 'force' => true, 'corrects_id' => $original->id, 'origin' => 'online', 'person_id' => $original->person_id, 'vehicle_id' => $original->vehicle_id];

            return $this->service->{$kind}($payload, $r->user(), $r->ip(), $r->userAgent());
        });

        return response()->json(['data' => $movement], 201);
    }
}
