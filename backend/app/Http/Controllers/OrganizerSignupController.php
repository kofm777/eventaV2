<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizerSignupRequest;
use App\Models\Admin;
use App\Models\Organizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PUBLIC organizer self-signup (hybrid: self-serve + super-admin approval).
 *
 * Mirrors the existing public RegistrationController: unauthenticated, throttled at
 * the route, NO organizer context (so the BelongsToOrganizer auto-stamp hook is inert
 * and we set organizer_id explicitly, exactly like RegistrationController).
 *
 * Creates, in ONE transaction:
 *   (a) Organizer with status=pending (DB default stays 'active'; only signup writes pending);
 *   (b) the owner Admin (role=owner, organizer_id set, password auto-hashed by the cast).
 *
 * NO token is issued — the owner must wait for approval, then log in.
 */
class OrganizerSignupController extends Controller
{
    public function signup(OrganizerSignupRequest $request): JsonResponse
    {
        try {
            $organizer = DB::transaction(function () use ($request) {
                $organizer = Organizer::create([
                    'name' => $request->organizer_name,
                    'slug' => $this->uniqueSlug($request->organizer_name),
                    'status' => Organizer::STATUS_PENDING,
                    'contact_email' => $request->contact_email,
                ]);

                Admin::create([
                    'organizer_id' => $organizer->id,
                    'role' => Admin::ROLE_OWNER,
                    'name' => $request->admin_name,
                    'email' => $request->email,
                    // 'password' => 'hashed' cast on Admin hashes this automatically.
                    'password' => $request->password,
                ]);

                return $organizer;
            });

            return response()->json([
                'ok' => true,
                'organizer' => $organizer->only(['id', 'name', 'slug', 'status']),
                'message' => 'Account created. Pending approval.',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Organizer signup failed', [
                'email' => $request->email ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'An error occurred while creating your account.',
            ], 500);
        }
    }

    /**
     * Build a unique organizer slug from a name (same pattern as EventController::uniqueSlug).
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'organizer';
        }

        $slug = $base;
        $suffix = 1;

        while (Organizer::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
