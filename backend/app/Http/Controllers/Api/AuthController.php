<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['email' => ['Credenciales incorrectas.']]);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Cuenta desactivada.'], 403);
        }

        $deviceName = $request->device_name
            ?: 'device-' . strtolower(substr(md5(random_bytes(16)), 0, 16));

        // Sesión única: se descartan tokens vencidos o abandonados (sin uso
        // reciente), de modo que una sesión muerta no bloquee el login.
        $user->tokens()
            ->where(fn ($q) =>
                $q->where(fn ($q2) => $q2->whereNotNull('expires_at')->where('expires_at', '<=', now()))
                  ->orWhere(fn ($q3) => $q3->whereNotNull('last_used_at')->where('last_used_at', '<', now()->subMinutes(60)))
            )
            ->delete();

        // Sesión única por usuario: un nuevo login SIEMPRE reemplaza la sesión
        // anterior en lugar de bloquear (evita quedarse fuera por tokens huérfanos).
        $user->tokens()->delete();

        $token = $user->createToken($deviceName, ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'role' => 'agent',
            'is_active' => true,
        ]);

        $deviceName = $request->device_name
            ?: 'device-' . strtolower(substr(md5(random_bytes(16)), 0, 16));
        $token = $user->createToken($deviceName, ['*'], now()->addDays(30))->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token, 'token_type' => 'Bearer'], 201);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function updateProfile(Request $request)
    {
        $request->validate(['name' => 'sometimes|string|max:255', 'phone' => 'nullable|string|max:20']);
        $request->user()->update($request->only(['name', 'phone']));
        return response()->json($request->user());
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'La contraseña actual es incorrecta'], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);
        return response()->json(['message' => 'Contraseña actualizada']);
    }
}