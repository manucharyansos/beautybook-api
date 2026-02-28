<?php
// app/Http/Controllers/Api/StaffController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();

        if (!in_array($actor->role, [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_SUPER_ADMIN], true)) {
            abort(403);
        }

        $q = User::query()
            ->whereIn('role', [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_STAFF]);

        if ($actor->role === User::ROLE_SUPER_ADMIN) {
            $data = $request->validate([
                'business_id' => ['required','integer','exists:businesses,id'], // Փոխել salon_id-ից business_id
            ]);
            $q->where('business_id', $data['business_id']); // Փոխել salon_id-ից business_id
        } else {
            $q->where('business_id', $actor->business_id); // Փոխել salon_id-ից business_id
        }

        if ($request->has('only_active')) {
            $onlyActive = $request->boolean('only_active', true);
            if ($onlyActive) $q->where('is_active', true);
        }

        return response()->json([
            'data' => $q->orderBy('id')->get([
                'id','name','email','role','business_id','is_active','deactivated_at' // Փոխել salon_id-ից business_id
            ])
        ]);
    }

    public function store(Request $request)
    {
        $actor = $request->user();
        $business = $actor->business()->with('subscription.plan')->first(); // Փոխել salon-ից business

        if ($business && !$business->hasAvailableSeat()) {
            return response()->json([
                'message' => 'Seat limit reached. Please upgrade your plan.',
                'limit' => $business->seatLimit(),
                'current' => $business->activeSeatCount(),
            ], 409);
        }

        if (!in_array($actor->role, [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_SUPER_ADMIN], true)) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required','string','min:2','max:120'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:8','max:255'],
            'role' => ['nullable','in:staff,manager'],
        ]);

        $role = $data['role'] ?? User::ROLE_STAFF;

        $staff = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $role,
            'business_id' => $actor->business_id, // Փոխել salon_id-ից business_id
        ]);

        return response()->json([
            'data' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'role' => $staff->role,
                'business_id' => $staff->business_id, // Փոխել salon_id-ից business_id
            ]
        ], 201);
    }

    public function deactivate(Request $request, User $user)
    {
        Gate::authorize('deactivate', $user);

        if ($user->is_active === false) return response()->json(['ok' => true]);

        $user->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);

        $user->tokens()?->delete();

        return response()->json(['ok' => true]);
    }

    public function activate(Request $request, User $user)
    {
        Gate::authorize('deactivate', $user);

        $user->update([
            'is_active' => true,
            'deactivated_at' => null,
        ]);

        return response()->json(['ok' => true]);
    }
}
