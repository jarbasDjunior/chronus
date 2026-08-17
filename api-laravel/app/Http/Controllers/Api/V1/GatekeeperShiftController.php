<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Gatekeeper;
use App\Models\GatekeeperShift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GatekeeperShiftController extends Controller
{
    public function index(Request $request)
    {
        $query = GatekeeperShift::with('gatekeeper.company', 'location')->latest('started_at');
        if (! $request->user()->canDo('shifts.view')) {
            $query->where('gatekeeper_id', $this->gatekeeper($request)->id);
        }

        return response()->json($query->paginate(min((int) $request->query('per_page', 20), 100)));
    }

    public function current(Request $request)
    {
        $shift = $this->openShift($this->gatekeeper($request));

        return response()->json(['data' => $shift?->load('gatekeeper.company', 'location')]);
    }

    public function start(Request $request)
    {
        $data = $request->validate([
            'access_location_id' => 'required|exists:access_locations,id',
            'notes' => 'nullable|string|max:2000',
        ]);
        $gatekeeper = $this->gatekeeper($request);

        return DB::transaction(function () use ($request, $data, $gatekeeper) {
            if ($this->openShift($gatekeeper, true)) {
                throw ValidationException::withMessages(['shift' => 'Já existe um turno aberto para este porteiro.']);
            }
            $shift = GatekeeperShift::create([
                ...$data,
                'gatekeeper_id' => $gatekeeper->id,
                'started_at' => now()->utc(),
            ]);
            $this->audit($request, 'shift.start', $shift);

            return response()->json(['data' => $shift->load('gatekeeper.company', 'location')], 201);
        });
    }

    public function startBreak(Request $request)
    {
        $shift = $this->requiredOpenShift($request);
        if ($shift->break_started_at) {
            throw ValidationException::withMessages(['break' => 'O intervalo deste turno já foi iniciado.']);
        }
        $shift->update(['break_started_at' => now()->utc()]);
        $this->audit($request, 'shift.break.start', $shift);

        return response()->json(['data' => $shift->fresh()->load('gatekeeper.company', 'location')]);
    }

    public function endBreak(Request $request)
    {
        $shift = $this->requiredOpenShift($request);
        if (! $shift->break_started_at || $shift->break_ended_at) {
            throw ValidationException::withMessages(['break' => 'Não existe intervalo aberto neste turno.']);
        }
        if ($shift->break_started_at->diffInMinutes(now()->utc()) < 60) {
            throw ValidationException::withMessages(['break' => 'O intervalo de almoço deve durar pelo menos 1 hora.']);
        }
        $shift->update(['break_ended_at' => now()->utc()]);
        $this->audit($request, 'shift.break.end', $shift);

        return response()->json(['data' => $shift->fresh()->load('gatekeeper.company', 'location')]);
    }

    public function finish(Request $request)
    {
        $shift = $this->requiredOpenShift($request);
        if ($shift->break_started_at && ! $shift->break_ended_at) {
            throw ValidationException::withMessages(['shift' => 'Finalize o intervalo antes de encerrar o turno.']);
        }
        $shift->update(['ended_at' => now()->utc()]);
        $this->audit($request, 'shift.finish', $shift);

        return response()->json(['data' => $shift->fresh()->load('gatekeeper.company', 'location')]);
    }

    private function gatekeeper(Request $request): Gatekeeper
    {
        $gatekeeper = Gatekeeper::where('user_id', $request->user()->id)->where('active', true)->first();
        if (! $gatekeeper) {
            throw ValidationException::withMessages(['gatekeeper' => 'Este usuário não está vinculado a um porteiro ativo.']);
        }

        return $gatekeeper;
    }

    private function openShift(Gatekeeper $gatekeeper, bool $lock = false): ?GatekeeperShift
    {
        $query = $gatekeeper->shifts()->whereNull('ended_at')->latest('started_at');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function requiredOpenShift(Request $request): GatekeeperShift
    {
        $shift = $this->openShift($this->gatekeeper($request));
        if (! $shift) {
            throw ValidationException::withMessages(['shift' => 'Nenhum turno aberto foi encontrado.']);
        }

        return $shift;
    }

    private function audit(Request $request, string $action, GatekeeperShift $shift): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => GatekeeperShift::class,
            'auditable_id' => $shift->id,
            'after' => $shift->fresh()->toArray(),
            'ip_address' => $request->ip(),
            'device' => $request->userAgent(),
        ]);
    }
}
