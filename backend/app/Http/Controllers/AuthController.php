<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Admin login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            Log::warning('Failed admin login attempt', [
                'email' => $request->email,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => ['Les informations d\'identification fournies sont incorrectes.'],
            ]);
        }

        // Revoke existing tokens
        $admin->tokens()->delete();

        // Create new token
        $token = $admin->createToken('admin-token')->plainTextToken;

        Log::info('Admin logged in successfully', [
            'admin_id' => $admin->id,
            'email' => $admin->email,
        ]);

        return response()->json([
            'ok' => true,
            'admin' => $admin->only(['id', 'name', 'email']),
            'token' => $token,
            'message' => 'Connexion réussie.',
        ]);
    }

    /**
     * Admin logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Déconnexion réussie.',
        ]);
    }

    /**
     * Get current admin user
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'admin' => $request->user()->only(['id', 'name', 'email']),
        ]);
    }
}
