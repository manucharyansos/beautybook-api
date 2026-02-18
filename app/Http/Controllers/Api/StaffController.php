<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;


class StaffController extends Controller
{
    // GET /api/staff  (owner/manager տեսնում է salon-ի staff list-ը)
    public function index(Request $request)
    {
        $actor = $request->user();

        if (!in_array($actor->role, [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_SUPER_ADMIN], true)) {
            abort(403);
        }

        $q = User::query()
            ->whereIn('role', [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_STAFF]);

        // SuperAdmin: allow choose salon_id via query
        if ($actor->role === User::ROLE_SUPER_ADMIN) {
            $data = $request->validate([
                'salon_id' => ['required','integer','exists:salons,id'],
            ]);
            $q->where('salon_id', $data['salon_id']);
        } else {
            $q->where('salon_id', $actor->salon_id);
        }

        // optional filter
        if ($request->has('only_active')) {
            $onlyActive = $request->boolean('only_active', true);
            if ($onlyActive) $q->where('is_active', true);
        }

        return response()->json([
            'data' => $q->orderBy('id')->get([
                'id','name','email','role','salon_id','is_active','deactivated_at'
            ])
        ]);
    }


    // POST /api/staff  (owner/manager ստեղծում է staff)
    public function store(Request $request)
    {
        $salon = $request->user()->salon()->with('subscription.plan')->first();

        if ($salon && !$salon->hasAvailableSeat()) {
            return response()->json([
                'message' => 'Seat limit reached. Please upgrade your plan.',
                'limit' => $salon->seatLimit(),
                'current' => $salon->activeSeatCount(),
            ], 409);
        }

        $user = $request->user();

        if (!in_array($user->role, [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_SUPER_ADMIN], true)) {
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
            'salon_id' => $user->salon_id,
        ]);

        return response()->json([
            'data' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'role' => $staff->role,
                'salon_id' => $staff->salon_id,
            ]
        ], 201);
    }

    public function deactivate(Request $request, User $user)
    {
        Gate::authorize('deactivate', $user);

        // լրացուցիչ պաշտպանություն՝ եթե պետք գա
        if ($user->is_active === false) return response()->json(['ok' => true]);

        $user->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);

        // optionally: revoke tokens
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
