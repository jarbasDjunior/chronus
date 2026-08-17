<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $d = $r->validate(['login' => 'required|string', 'password' => 'required|string', 'device_name' => 'nullable|string|max:100']);
        $u = User::where('email', $d['login'])->orWhere('username', $d['login'])->first();
        if (! $u || ! $u->active || ! Hash::check($d['password'], $u->password)) {
            return response()->json(['message' => 'Credenciais inválidas.'], 422);
        } $u->tokens()->where('created_at', '<', now()->subDays(30))->delete();

        return response()->json(['data' => ['token' => $u->createToken($d['device_name'] ?? 'app')->plainTextToken, 'user' => $u->load('role.permissions', 'gatekeeper.company')]]);
    }

    public function me(Request $r)
    {
        return response()->json(['data' => $r->user()->load('role.permissions', 'gatekeeper.company')]);
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada.']);
    }
}
