<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AccessLocation;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Gatekeeper;
use App\Models\Person;
use App\Models\PersonCategory;
use App\Models\SecurityCompany;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class CrudController extends Controller
{
    private function model(string $resource)
    {
        return match ($resource) {
            'people' => Person::class,
            'vehicles' => Vehicle::class,
            'categories' => PersonCategory::class,
            'departments' => Department::class,
            'locations' => AccessLocation::class,
            'security-companies' => SecurityCompany::class,
            'gatekeepers' => Gatekeeper::class,
            default => abort(404)
        };
    }

    public function index(Request $r, string $resource)
    {
        $this->authorizeSensitiveResource($r, $resource);
        $m = $this->model($resource);
        $q = $m::query();
        if ($resource === 'people') {
            $q->with('category', 'department', 'vehicles');
        } if ($resource === 'vehicles') {
            $q->with('people');
        } if ($resource === 'security-companies') {
            $q->with('gatekeepers');
        } if ($resource === 'gatekeepers') {
            $q->with('company', 'user');
        } if ($s = $r->query('search')) {
            $q->where(function ($x) use ($s, $resource) {
                if ($resource === 'people') {
                    $x->where('name', 'like', "%$s%")
                        ->orWhere('registration', 'like', "%$s%")
                        ->orWhereHas('vehicles', fn ($vehicle) => $vehicle->where('plate', 'like', "%$s%"));
                } elseif ($resource === 'vehicles') {
                    $x->where('plate', 'like', "%$s%")->orWhere('model', 'like', "%$s%")->orWhereHas('people', fn ($p) => $p->where('name', 'like', "%$s%"));
                } elseif ($resource === 'gatekeepers') {
                    $x->where('name', 'like', "%$s%")
                        ->orWhere('registration', 'like', "%$s%")
                        ->orWhereHas('company', fn ($company) => $company->where('name', 'like', "%$s%"));
                } else {
                    $x->where('name', 'like', "%$s%");
                }
            });
        }

        return response()->json($q->paginate(min((int) $r->query('per_page', 20), 100)));
    }

    public function store(Request $r, string $resource)
    {
        $data = $this->validated($r, $resource);
        $m = $this->model($resource);
        $item = $m::create(Arr::except($data, ['person_ids']));
        if ($resource === 'vehicles' && isset($data['person_ids'])) {
            $item->people()->sync($data['person_ids']);
        }
        $item->load($this->relations($resource));
        $this->audit($r, 'create', $item, null);

        return response()->json(['data' => $item], 201);
    }

    public function show(Request $r, string $resource, int $id)
    {
        $this->authorizeSensitiveResource($r, $resource);
        $m = $this->model($resource);

        return response()->json(['data' => $m::with($this->relations($resource))->findOrFail($id)]);
    }

    public function update(Request $r, string $resource, int $id)
    {
        $m = $this->model($resource);
        $item = $m::findOrFail($id);
        $before = $item->toArray();
        $data = $this->validated($r, $resource, $id);
        $item->update(Arr::except($data, ['person_ids']));
        if ($resource === 'vehicles' && array_key_exists('person_ids', $data)) {
            $item->people()->sync($data['person_ids']);
        }
        $item->load($this->relations($resource));
        $this->audit($r, 'update', $item, $before);

        return response()->json(['data' => $item]);
    }

    private function validated(Request $r, string $resource, ?int $id = null)
    {
        if ($resource === 'vehicles' && $r->has('plate')) {
            $plate = strtoupper(trim((string) $r->input('plate')));
            $r->merge(['plate' => str_replace(['-', ' '], '', $plate)]);
        }

        return match ($resource) {
            'people' => $r->validate(['name' => 'required|string|max:255', 'registration' => ['required', 'string', Rule::unique('people')->ignore($id)], 'category_id' => 'required|exists:person_categories,id', 'department_id' => 'nullable|exists:departments,id', 'job_title' => 'nullable|string', 'cpf' => 'nullable|string|max:14', 'phone' => 'nullable|string', 'email' => 'nullable|email', 'notes' => 'nullable|string', 'active' => 'boolean']),
            'vehicles' => $r->validate([
                'plate' => ['required', 'string', 'regex:/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', Rule::unique('vehicles')->ignore($id)],
                'model' => 'required|string|max:255',
                'color' => 'required|string|max:100',
                'active' => 'required|boolean',
                'brand' => 'nullable|string|max:255',
                'type' => 'nullable|string|max:100',
                'year' => 'nullable|integer|min:1900|max:2100',
                'notes' => 'nullable|string',
                'person_ids' => 'required|array|min:1',
                'person_ids.*' => 'distinct|exists:people,id',
            ], [
                'plate.regex' => 'A placa deve estar no formato brasileiro ABC1234 ou Mercosul ABC1D23.',
                'person_ids.required' => 'Vincule o veículo a pelo menos um funcionário.',
            ]),
            'security-companies' => $r->validate([
                'name' => ['required', 'string', 'max:255', Rule::unique('security_companies')->ignore($id)],
                'cnpj' => ['nullable', 'string', 'max:18', Rule::unique('security_companies')->ignore($id)],
                'active' => 'boolean',
            ]),
            'gatekeepers' => $r->validate([
                'security_company_id' => 'required|exists:security_companies,id',
                'user_id' => ['nullable', 'exists:users,id', Rule::unique('gatekeepers')->ignore($id)],
                'name' => 'required|string|max:255',
                'registration' => ['required', 'string', 'max:100', Rule::unique('gatekeepers')->ignore($id)],
                'cpf' => 'nullable|string|max:14',
                'phone' => 'nullable|string|max:30',
                'email' => 'nullable|email|max:255',
                'active' => 'boolean',
            ]),
            default => $r->validate(['name' => ['required', 'string', 'max:255', Rule::unique($resource === 'locations' ? 'access_locations' : $resource)->ignore($id)], 'active' => 'boolean'])
        };
    }

    private function relations(string $resource): array
    {
        return match ($resource) {
            'people' => ['category', 'department', 'vehicles'],
            'vehicles' => ['people'],
            'security-companies' => ['gatekeepers'],
            'gatekeepers' => ['company', 'user'],
            default => [],
        };
    }

    private function audit($r, $action, $item, $before)
    {
        AuditLog::create(['user_id' => $r->user()->id, 'action' => $action, 'auditable_type' => $item::class, 'auditable_id' => $item->id, 'before' => $before, 'after' => $item->toArray(), 'ip_address' => $r->ip(), 'device' => $r->userAgent()]);
    }

    private function authorizeSensitiveResource(Request $request, string $resource): void
    {
        if (in_array($resource, ['security-companies', 'gatekeepers'], true)
            && ! $request->user()->canDo('registrations.manage')) {
            abort(403, 'Apenas administradores podem acessar este cadastro.');
        }
    }
}
