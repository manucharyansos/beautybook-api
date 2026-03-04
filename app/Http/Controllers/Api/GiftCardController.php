<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class GiftCardController extends Controller
{
    /**
     * GET /api/gift-cards
     */
    public function index(Request $request)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        $q = GiftCard::query();

        if ($actor->isSuperAdmin()) {
            if ($request->filled('business_id')) {
                $q->where('business_id', $request->integer('business_id'));
            }
        } else {
            $q->where('business_id', $actor->business_id);
        }

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        if ($request->filled('q')) {
            $term = trim((string)$request->string('q'));
            $q->where(function ($qq) use ($term) {
                $qq->where('code', 'like', "%{$term}%")
                   ->orWhere('issued_to_name', 'like', "%{$term}%")
                   ->orWhere('issued_to_phone', 'like', "%{$term}%")
                   ->orWhere('purchased_by_name', 'like', "%{$term}%")
                   ->orWhere('purchased_by_phone', 'like', "%{$term}%");
            });
        }

        return response()->json([
            'data' => $q->orderByDesc('id')->limit(500)->get(),
        ]);
    }

    /**
     * POST /api/gift-cards
     */
    public function store(Request $request)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        // staff cannot create gift cards
        if ($actor->role === User::ROLE_STAFF) abort(403);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40'],
            'amount' => ['required', 'integer', 'min:100', 'max:100000000'],
            'currency' => ['nullable', 'string', 'max:8'],
            'issued_to_name' => ['nullable', 'string', 'max:120'],
            'issued_to_phone' => ['nullable', 'string', 'max:40'],
            'purchased_by_name' => ['nullable', 'string', 'max:120'],
            'purchased_by_phone' => ['nullable', 'string', 'max:40'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $businessId = $actor->isSuperAdmin() ? (int)($request->integer('business_id') ?: 0) : (int)$actor->business_id;
        if (!$businessId) {
            throw ValidationException::withMessages(['business_id' => 'business_id is required for super admin']);
        }

        $code = $data['code'] ?? null;
        if (!$code) {
            $code = $this->generateCode();
        }

        $currency = $data['currency'] ?? 'AMD';

        $expiresAt = !empty($data['expires_at']) ? Carbon::parse($data['expires_at'])->endOfDay() : null;

        $gc = GiftCard::create([
            'business_id' => $businessId,
            'code' => strtoupper(trim($code)),
            'initial_amount' => (int)$data['amount'],
            'balance' => (int)$data['amount'],
            'currency' => $currency,
            'issued_to_name' => $data['issued_to_name'] ?? null,
            'issued_to_phone' => $data['issued_to_phone'] ?? null,
            'purchased_by_name' => $data['purchased_by_name'] ?? null,
            'purchased_by_phone' => $data['purchased_by_phone'] ?? null,
            'expires_at' => $expiresAt,
            'notes' => $data['notes'] ?? null,
            'status' => 'active',
        ]);

        return response()->json(['data' => $gc], 201);
    }

    /**
     * GET /api/gift-cards/{giftCard}
     */
    public function show(Request $request, GiftCard $giftCard)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        if (!$actor->isSuperAdmin() && (int)$giftCard->business_id !== (int)$actor->business_id) abort(404);

        return response()->json(['data' => $giftCard]);
    }

    /**
     * PUT /api/gift-cards/{giftCard}
     */
    public function update(Request $request, GiftCard $giftCard)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        if ($actor->role === User::ROLE_STAFF) abort(403);
        if (!$actor->isSuperAdmin() && (int)$giftCard->business_id !== (int)$actor->business_id) abort(404);

        $data = $request->validate([
            'issued_to_name' => ['nullable', 'string', 'max:120'],
            'issued_to_phone' => ['nullable', 'string', 'max:40'],
            'purchased_by_name' => ['nullable', 'string', 'max:120'],
            'purchased_by_phone' => ['nullable', 'string', 'max:40'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'in:active,cancelled'],
        ]);

        if (array_key_exists('expires_at', $data)) {
            $giftCard->expires_at = $data['expires_at'] ? Carbon::parse($data['expires_at'])->endOfDay() : null;
        }

        foreach (['issued_to_name','issued_to_phone','purchased_by_name','purchased_by_phone','notes'] as $k) {
            if (array_key_exists($k, $data)) $giftCard->{$k} = $data[$k];
        }

        if (!empty($data['status'])) {
            if ($data['status'] === 'cancelled' && $giftCard->status !== 'redeemed') {
                $giftCard->status = 'cancelled';
            }
            if ($data['status'] === 'active' && $giftCard->status !== 'redeemed') {
                $giftCard->status = 'active';
            }
        }

        $giftCard->save();

        return response()->json(['data' => $giftCard]);
    }

    /**
     * PATCH /api/gift-cards/{giftCard}/redeem
     * body: amount
     */
    public function redeem(Request $request, GiftCard $giftCard)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        if ($actor->role === User::ROLE_STAFF) abort(403);
        if (!$actor->isSuperAdmin() && (int)$giftCard->business_id !== (int)$actor->business_id) abort(404);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:100000000'],
        ]);

        if (!$giftCard->isActive()) {
            throw ValidationException::withMessages(['gift_card' => 'Gift card is not active']);
        }

        $amount = (int)$data['amount'];
        if ($amount > (int)$giftCard->balance) {
            throw ValidationException::withMessages(['amount' => 'Amount exceeds balance']);
        }

        $giftCard->balance = (int)$giftCard->balance - $amount;
        $giftCard->redeemed_total = (int)$giftCard->redeemed_total + $amount;
        $giftCard->last_redeemed_at = now();
        if ($giftCard->balance <= 0) {
            $giftCard->balance = 0;
            $giftCard->status = 'redeemed';
        }
        $giftCard->save();

        return response()->json(['data' => $giftCard]);
    }

    private function generateCode(): string
    {
        do {
            $code = 'GC-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
        } while (GiftCard::where('code', $code)->exists());

        return $code;
    }
}
