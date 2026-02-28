<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OwnerRegistered;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TrialAttempt;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required','string'],
        ]);

        $user = User::with('business:id,slug,is_onboarding_completed,business_type')
            ->where('email', $data['email'])
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials']);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Your account is disabled. Please contact admin.'
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'business_id' => $user->business_id,
                'business_slug' => $user->business?->slug,
                'business_type' => $user->business?->business_type,
                'needs_onboarding' => $user->business ? !$user->business->is_onboarding_completed : true,
            ],
        ]);
    }

    public function register(Request $request)
    {
        // Fingerprint should be generated on frontend and passed as header or field.
        $fingerprint = (string)($request->header('X-Device-Fingerprint') ?? $request->input('device_fingerprint') ?? '');
        $fingerprint = trim($fingerprint);
        if ($fingerprint === '') $fingerprint = null;

        $data = $request->validate([
            'business_name' => ['required','string','min:2','max:120'],
            'business_phone' => ['required','string','max:40'],
            'business_address' => ['nullable','string','max:255'],
            'business_type' => ['required','string','in:salon,clinic'],

            'name' => ['required','string','min:2','max:120'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:8','max:255','confirmed'],
        ]);

        $phoneNorm = Phone::normalizeAM($data['business_phone'] ?? null);
        if (!$phoneNorm) {
            throw ValidationException::withMessages(['business_phone' => 'Invalid phone number']);
        }

        // ✅ Anti-abuse: trial once per phone OR fingerprint
        $trialExists = TrialAttempt::query()
            ->where(function ($q) use ($phoneNorm, $fingerprint) {
                $q->where('phone_norm', $phoneNorm);
                if ($fingerprint) $q->orWhere('fingerprint', $fingerprint);
            })
            ->exists();

        if ($trialExists) {
            throw ValidationException::withMessages([
                'business_phone' => 'Trial already used. Please login or contact support.'
            ]);
        }

        $trialDays = (int) config('billing.trial_days', 14);
        if ($trialDays < 1 || $trialDays > 30) $trialDays = 14;

        $out = DB::transaction(function () use ($data, $phoneNorm, $fingerprint, $request, $trialDays) {
            // unique slug
            $baseSlug = Str::slug($data['business_name']);
            $slug = $baseSlug ?: 'business';
            $i = 1;
            while (Business::where('slug', $slug)->exists()) {
                $i++;
                $slug = ($baseSlug ?: 'business') . '-' . $i;
            }

            $business = Business::create([
                'name' => $data['business_name'],
                'slug' => $slug,
                'business_type' => $data['business_type'],
                'phone' => $phoneNorm,
                'address' => $data['business_address'] ?? null,
                'status' => 'active',
            ]);

            // working hours defaults
            $defaults = [
                1 => ['09:00','18:00', false],
                2 => ['09:00','18:00', false],
                3 => ['09:00','18:00', false],
                4 => ['09:00','18:00', false],
                5 => ['09:00','18:00', false],
                6 => ['09:00','18:00', false],
                7 => [null, null, true],
            ];

            foreach ($defaults as $weekday => [$start,$end,$closed]) {
                DB::table('business_working_hours')->insert([
                    'business_id' => $business->id,
                    'weekday' => $weekday,
                    'is_closed' => $closed,
                    'start' => $start,
                    'end' => $end,
                    'break_start' => null,
                    'break_end' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => User::ROLE_OWNER,
                'business_id' => $business->id,
            ]);

            // Pick starter plan that matches business type
            $plan = Plan::query()
                ->where('code', 'starter')
                ->first();

            $sub = Subscription::create([
                'business_id' => $business->id,
                'plan_id' => $plan?->id ?? 1,
                'status' => 'trialing',
                'trial_ends_at' => now()->addDays($trialDays),
                // snapshots will be filled by Subscription model boot / service layer in Phase 1
            ]);

            TrialAttempt::query()->create([
                'phone_norm' => $phoneNorm,
                'fingerprint' => $fingerprint,
                'email' => $data['email'],
                'ip' => (string)$request->ip(),
            ]);

            return [$user, $business, $sub];
        });

        /** @var User $user */
        /** @var Business $business */
        [$user, $business, $sub] = $out;

        // Send email to owner
        try {
            Mail::to($user->email)->send(new OwnerRegistered($user, $business, $trialDays));
        } catch (\Throwable $e) {
            // Do not fail registration on email issues
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'business_id' => $user->business_id,
                'business_slug' => $user->business?->slug,
                'business_type' => $user->business?->business_type,
                'needs_onboarding' => !$user->business?->is_onboarding_completed,
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('business:id,slug,is_onboarding_completed,business_type');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'business_id' => $user->business_id,
                'business_slug' => $user->business?->slug,
                'business_type' => $user->business?->business_type,
                'needs_onboarding' => !$user->business || !$user->business->is_onboarding_completed,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }
}
