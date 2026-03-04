<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoyaltyController extends Controller
{
    public function program(Request $request, LoyaltyService $svc)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        $program = $svc->getOrCreateProgram((int)$actor->business_id);
        return response()->json(['data' => $program]);
    }

    public function updateProgram(Request $request, LoyaltyService $svc)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        // Owner/Manager only
        if (!in_array($actor->role, ['owner', 'manager'])) {
            abort(403);
        }

        $data = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'currency_unit' => ['required', 'integer', 'min:1', 'max:1000000'],
            'points_per_currency_unit' => ['required', 'integer', 'min:0', 'max:1000'],
            'min_booking_amount' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $program = $svc->getOrCreateProgram((int)$actor->business_id);
        $program->update([
            'is_enabled' => (bool)$data['is_enabled'],
            'currency_unit' => (int)$data['currency_unit'],
            'points_per_currency_unit' => (int)$data['points_per_currency_unit'],
            'min_booking_amount' => (int)($data['min_booking_amount'] ?? 0),
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['data' => $program->fresh()]);
    }

    public function clients(Request $request)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        $q = Client::query()->where('business_id', $actor->business_id);

        if ($request->filled('q')) {
            $term = trim((string)$request->string('q'));
            $q->where(function ($qq) use ($term) {
                $qq->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        $clients = $q->orderBy('name')->limit(500)->get();

        // balance via subquery (faster than N+1)
        $balances = DB::table('loyalty_point_ledgers')
            ->select('client_id', DB::raw('COALESCE(SUM(delta_points),0) as points'))
            ->where('business_id', $actor->business_id)
            ->groupBy('client_id')
            ->pluck('points', 'client_id');

        $data = $clients->map(function (Client $c) use ($balances) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'points' => (int)($balances[$c->id] ?? 0),
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function adjust(Request $request, Client $client, LoyaltyService $svc)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        if ((int)$client->business_id !== (int)$actor->business_id) {
            abort(404);
        }

        if (!in_array($actor->role, ['owner', 'manager'])) {
            abort(403);
        }

        $data = $request->validate([
            'delta_points' => ['required', 'integer', 'min:-1000000', 'max:1000000'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $ledger = $svc->adjust($actor, $client, (int)$data['delta_points'], $data['reason'] ?? null);

        return response()->json(['data' => $ledger], 201);
    }
}
