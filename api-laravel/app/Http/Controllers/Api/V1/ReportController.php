<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PersonMovement;
use App\Models\VehicleMovement;
use App\Services\XlsxService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private function data(Request $r)
    {
        $common = fn ($q) => $q
            ->when($r->from, fn ($x, $v) => $x->where('occurred_at', '>=', $v))
            ->when($r->to, fn ($x, $v) => $x->where('occurred_at', '<=', $v))
            ->when($r->type, fn ($x, $v) => $x->where('type', $v))
            ->when($r->operator_id, fn ($x, $v) => $x->where('operator_id', $v))
            ->when($r->access_location_id, fn ($x, $v) => $x->where('access_location_id', $v))
            ->when($r->status, fn ($x, $v) => $x->where('status', $v))
            ->when($r->corrected, fn ($x, $v) => $x->whereNotNull('corrects_id'));
        $people = $common(PersonMovement::with('person.category', 'person.department', 'location', 'operator', 'vehicle'))
            ->when($r->person_id, fn ($q, $v) => $q->where('person_id', $v))
            ->when($r->vehicle_id, fn ($q, $v) => $q->where('vehicle_id', $v))
            ->when($r->category_id, fn ($q, $v) => $q->whereHas('person', fn ($p) => $p->where('category_id', $v)))
            ->when($r->department_id, fn ($q, $v) => $q->whereHas('person', fn ($p) => $p->where('department_id', $v)))
            ->when($r->plate, fn ($q, $v) => $q->whereHas('vehicle', fn ($vehicle) => $vehicle->where('plate', 'like', '%'.strtoupper($v).'%')))
            ->get();
        $vehicles = $common(VehicleMovement::with('vehicle', 'person.category', 'person.department', 'location', 'operator'))
            ->when($r->person_id, fn ($q, $v) => $q->where('person_id', $v))
            ->when($r->vehicle_id, fn ($q, $v) => $q->where('vehicle_id', $v))
            ->when($r->category_id, fn ($q, $v) => $q->whereHas('person', fn ($p) => $p->where('category_id', $v)))
            ->when($r->department_id, fn ($q, $v) => $q->whereHas('person', fn ($p) => $p->where('department_id', $v)))
            ->when($r->plate, fn ($q, $v) => $q->whereHas('vehicle', fn ($vehicle) => $vehicle->where('plate', 'like', '%'.strtoupper($v).'%')))
            ->get();

        return [$people, $vehicles];
    }

    public function pdf(Request $r)
    {
        [$people,$vehicles] = $this->data($r);

        return Pdf::loadView('reports.movements', ['people' => $people, 'vehicles' => $vehicles, 'filters' => $r->query(), 'user' => $r->user()])->setPaper('a4', 'landscape')->download('relatorio-chronus.pdf');
    }

    public function xlsx(Request $r, XlsxService $xlsx)
    {
        [$p,$v] = $this->data($r);
        $pr = [['Data/Hora', 'Pessoa', 'Matrícula', 'Tipo', 'Portaria', 'Operador', 'Placa']];
        foreach ($p as $m) {
            $pr[] = [$m->occurred_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s'), $m->person->name, $m->person->registration, $m->type === 'entry' ? 'Entrada' : 'Saída', $m->location->name, $m->operator->name, $m->vehicle?->plate];
        }$vr = [['Data/Hora', 'Placa', 'Veículo', 'Pessoa', 'Tipo', 'Portaria', 'Operador']];
        foreach ($v as $m) {
            $vr[] = [$m->occurred_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s'), $m->vehicle->plate, $m->vehicle->model, $m->person?->name, $m->type === 'entry' ? 'Entrada' : 'Saída', $m->location->name, $m->operator->name];
        }$fr = [['Filtro', 'Valor']];
        foreach ($r->query() as $k => $val) {
            $fr[] = [$k, $val];
        }$path = $xlsx->build(['Resumo' => [['Indicador', 'Total'], ['Movimentações de Pessoas', $p->count()], ['Movimentações de Veículos', $v->count()]], 'Movimentações de Pessoas' => $pr, 'Movimentações de Veículos' => $vr, 'Filtros Aplicados' => $fr]);

        return response()->download($path, 'relatorio-chronus.xlsx')->deleteFileAfterSend();
    }
}
