<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Salon;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
//use OpenApi\Annotations as OA;
use Illuminate\Support\Str;




class AuthController extends Controller
{
    /**
     * @OA\Post(
     *   path="/api/auth/login",
     *   tags={"Auth"},
     *   summary="Login and get token",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"email","password"},
     *       @OA\Property(property="email", type="string", example="owner@mail.com"),
     *       @OA\Property(property="password", type="string", example="password")
     *     )
     *   ),
     *   @OA\Response(response=200, description="OK")
     * )
     */

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required','string'],
        ]);

        $user = User::with('salon:id,slug')->where('email', $data['email'])->first();

        if (!$user || !\Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials']);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Your account is disabled. Please contact salon admin.'
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'salon_id' => $user->salon_id,
                'salon_slug' => $user->salon?->slug,
            ],
        ]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'salon_name' => ['required','string','min:2','max:120'],
            'salon_phone' => ['nullable','string','max:40'],
            'salon_address' => ['nullable','string','max:255'],

            'name' => ['required','string','min:2','max:120'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:8','max:255','confirmed'],
            // password_confirmation պարտադիր է request-ում
        ]);

        $user = DB::transaction(function () use ($data) {

            // Slug generate (unique)
            $baseSlug = Str::slug($data['salon_name']);
            $slug = $baseSlug ?: 'salon';

            $i = 1;
            while (Salon::where('slug', $slug)->exists()) {
                $i++;
                $slug = $baseSlug . '-' . $i;
            }

            $salon = Salon::create([
                'name' => $data['salon_name'],
                'slug' => $slug,
                'phone' => $data['salon_phone'] ?? null,
                'address' => $data['salon_address'] ?? null,
                'status' => 'active',
            ]);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => User::ROLE_OWNER,
                'salon_id' => $salon->id,
            ]);

            $plan = Plan::where('code', 'starter')->first(); // default plan

            Subscription::create([
                'salon_id' => $salon->id,
                'plan_id' => $plan?->id ?? 1,
                'status' => 'trialing',
                'trial_ends_at' => now()->addDays(14),
            ]);

            return $user;
        });

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'salon_id' => $user->salon_id,
                'salon_slug' => $user->salon?->slug,
            ],
        ]);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }
}
