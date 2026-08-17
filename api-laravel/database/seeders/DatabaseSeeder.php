<?php

namespace Database\Seeders;

use App\Models\AccessLocation;
use App\Models\Department;
use App\Models\Gatekeeper;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PersonCategory;
use App\Models\Role;
use App\Models\SecurityCompany;
use App\Models\User;
use App\Models\Vehicle;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissions = collect(['movements.create' => 'Registrar movimentações', 'movements.correct' => 'Corrigir movimentações', 'registrations.manage' => 'Gerenciar cadastros', 'reports.view' => 'Visualizar relatórios', 'audit.view' => 'Visualizar auditoria', 'shifts.view' => 'Visualizar turnos dos porteiros'])->map(fn ($name, $slug) => Permission::firstOrCreate(['slug' => $slug], ['name' => $name]));
        $admin = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrador']);
        $operator = Role::firstOrCreate(['slug' => 'operator'], ['name' => 'Porteiro']);
        $auditor = Role::firstOrCreate(['slug' => 'auditor'], ['name' => 'Gestor/Auditor']);
        $admin->permissions()->sync($permissions->pluck('id'));
        $operator->permissions()->sync($permissions->whereIn('slug', ['movements.create'])->pluck('id'));
        $auditor->permissions()->sync($permissions->whereIn('slug', ['reports.view', 'audit.view', 'shifts.view'])->pluck('id'));
        User::updateOrCreate(['email' => 'admin@chronus.local'], ['name' => 'Administrador Chronus', 'username' => 'admin', 'password' => 'Chronus@123', 'role_id' => $admin->id, 'active' => true]);
        $portaria = User::updateOrCreate(['email' => 'portaria@chronus.local'], ['name' => 'Carlos Lima', 'username' => 'portaria', 'password' => 'Chronus@123', 'role_id' => $operator->id, 'active' => true]);
        $func = PersonCategory::firstOrCreate(['name' => 'Funcionário']);
        $dep = Department::firstOrCreate(['name' => 'Administrativo']);
        Department::firstOrCreate(['name' => 'Operações']);
        AccessLocation::firstOrCreate(['name' => 'Portaria Principal']);
        $p1 = Person::firstOrCreate(['registration' => 'F001'], ['name' => 'Ana Souza', 'category_id' => $func->id, 'department_id' => $dep->id, 'job_title' => 'Analista']);
        $securityCompany = SecurityCompany::firstOrCreate(['name' => 'Segurança Terceirizada Exemplo']);
        Gatekeeper::updateOrCreate(['registration' => 'P001'], ['security_company_id' => $securityCompany->id, 'user_id' => $portaria->id, 'name' => 'Carlos Lima', 'active' => true]);
        $vehicle = Vehicle::firstOrCreate(['plate' => 'ABC1D23'], ['brand' => 'Toyota', 'model' => 'Corolla', 'color' => 'Prata', 'type' => 'Automóvel']);
        $vehicle->people()->syncWithoutDetaching([$p1->id]);
    }
}
