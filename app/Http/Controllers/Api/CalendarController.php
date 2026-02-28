<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    /**
     * GET /api/calendar?from=2026-02-01&to=2026-02-28
     * (optional for super-admin) &business_id=123
     */
    public function index(Request $request)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date'],
            'business_id' => ['nullable', 'integer'],
        ]);

        $q = Booking::query()
            ->with(['service', 'staff'])
            ->whereBetween('starts_at', [$data['from'], $data['to']]);

        if ($actor->isSuperAdmin()) {
            if (!empty($data['business_id'])) {
                $q->where('business_id', (int) $data['business_id']);
            }
        } else {
            // tenant enforce
            $q->where('business_id', $actor->business_id);

            // staff sees only own bookings
            if ($actor->role === User::ROLE_STAFF) {
                $q->where('staff_id', $actor->id);
            }
        }

        return response()->json([
            'data' => $q->orderBy('starts_at')->get(),
        ]);
    }
}
