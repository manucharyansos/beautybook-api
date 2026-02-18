<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use Illuminate\Http\Request;

class BillingSeatsController extends Controller
{
    public function show(Request $request)
    {
        $salon = Salon::query()
            ->with(['subscription.plan'])
            ->findOrFail($request->user()->salon_id);

        $limit = $salon->subscription?->plan?->seats;

        $activeUsers = $salon->seatUsers()
            ->orderByRaw("FIELD(role,'owner','manager','staff')")
            ->orderBy('id')
            ->get(['id','name','email','role','is_active']);

        return response()->json([
            'data' => [
                'seat_limit' => $limit,
                'active_seat_count' => $activeUsers->count(),
                'users' => $activeUsers,
            ],
        ]);
    }
}
