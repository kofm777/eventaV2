<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Admin login
     */
    public function login(Request $request): JsonResponse
    {
        if (!session()->has('lottery')) {
            session(['lottery' => []]);
        }

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

        // Create new Sanctum token
        $token = $admin->createToken('admin-token')->plainTextToken;

        Log::info('Admin logged in successfully', [
            'admin_id' => $admin->id,
            'email' => $admin->email,
        ]);

        return response()->json([
            'ok' => true,
            // Additive role/organizer_id keys; id/name/email unchanged so the frontend keeps working.
            'admin' => $admin->only(['id', 'name', 'email', 'role', 'organizer_id']),
            // Additive: organizer summary (incl status) so the SPA can route/banner
            // pending/suspended owners. null for super-admin.
            'organizer' => $this->organizerSummary($admin),
            'token' => $token,
            'message' => 'Connexion réussie.',
        ]);
    }

    /**
     * Build the organizer summary attached to login/me, or null for super-admins.
     */
    private function organizerSummary(Admin $admin): ?array
    {
        if ($admin->isSuperAdmin()) {
            return null;
        }

        $organizer = $admin->organizer;

        return $organizer
            ? $organizer->only(['id', 'name', 'slug', 'status'])
            : null;
    }

    /**
     * Admin logout
     */
    public function logout(Request $request): JsonResponse
    {
        // Explicitly use Sanctum guard
        $request->user('sanctum')->currentAccessToken()->delete();

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
        $admin = $request->user('sanctum'); // explicitly use Sanctum

        return response()->json([
            'ok' => true,
            // Additive role/organizer_id keys; id/name/email unchanged.
            'admin' => $admin->only(['id', 'name', 'email', 'role', 'organizer_id']),
            // Additive: organizer summary (incl status) so the SPA can refresh
            // approval state without re-login. null for super-admin.
            'organizer' => $this->organizerSummary($admin),
        ]);
    }
}
