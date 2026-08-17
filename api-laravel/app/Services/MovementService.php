<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Person;
use App\Models\PersonMovement;
use App\Models\Vehicle;
use App\Models\VehicleMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MovementService
{
    public function person(array $data, $user, ?string $ip = null, ?string $device = null): PersonMovement
    {
        return DB::transaction(function () use ($data, $user, $ip, $device) {
            if ($existing = PersonMovement::where('uuid', $data['uuid'])->first()) {
                return $existing;
            }
            $person = Person::whereKey($data['person_id'])->lockForUpdate()->firstOrFail();
            $last = $person->movements()->where('status', 'valid')->latest('occurred_at')->latest('id')->first();
            $isException = (bool) ($data['force'] ?? false);
            if ($last?->type === $data['type'] && ! $isException) {
                throw ValidationException::withMessages(['type' => 'Movimentação consecutiva incompatível.']);
            }
            if ($isException && (! $user->canDo('movements.correct') || empty($data['correction_reason']))) {
                throw ValidationException::withMessages(['correction_reason' => 'Exceção administrativa exige permissão e justificativa.']);
            }
            unset($data['force']);
            $movement = PersonMovement::create([...$data, 'operator_id' => $user->id, 'occurred_at' => $data['occurred_at'] ?? now()->utc(), 'synced_at' => now()->utc(), 'origin' => $data['origin'] ?? 'online', 'status' => 'valid']);
            AuditLog::create(['user_id' => $user->id, 'action' => $isException ? 'movement.exception' : 'movement.create', 'auditable_type' => PersonMovement::class, 'auditable_id' => $movement->id, 'after' => $movement->toArray(), 'reason' => $data['correction_reason'] ?? null, 'ip_address' => $ip, 'device' => $device]);

            return $movement->load('person.category', 'location', 'operator', 'vehicle');
        });
    }

    public function vehicle(array $data, $user, ?string $ip = null, ?string $device = null): VehicleMovement
    {
        return DB::transaction(function () use ($data, $user, $ip, $device) {
            if ($existing = VehicleMovement::where('uuid', $data['uuid'])->first()) {
                return $existing;
            }
            $vehicle = Vehicle::whereKey($data['vehicle_id'])->lockForUpdate()->firstOrFail();
            $last = $vehicle->movements()->where('status', 'valid')->latest('occurred_at')->latest('id')->first();
            $forced = (bool) ($data['force'] ?? false);
            if ($last?->type === $data['type'] && ! $forced) {
                throw ValidationException::withMessages(['type' => 'Movimentação consecutiva incompatível.']);
            }
            if ($forced && (! $user->canDo('movements.correct') || empty($data['correction_reason']))) {
                throw ValidationException::withMessages(['correction_reason' => 'Exceção administrativa exige permissão e justificativa.']);
            }
            unset($data['force']);
            $movement = VehicleMovement::create([...$data, 'operator_id' => $user->id, 'occurred_at' => $data['occurred_at'] ?? now()->utc(), 'synced_at' => now()->utc(), 'origin' => $data['origin'] ?? 'online', 'status' => 'valid']);
            AuditLog::create(['user_id' => $user->id, 'action' => $forced ? 'movement.exception' : 'movement.create', 'auditable_type' => VehicleMovement::class, 'auditable_id' => $movement->id, 'after' => $movement->toArray(), 'reason' => $data['correction_reason'] ?? null, 'ip_address' => $ip, 'device' => $device]);

            return $movement->load('vehicle.people', 'person', 'location', 'operator');
        });
    }
}
