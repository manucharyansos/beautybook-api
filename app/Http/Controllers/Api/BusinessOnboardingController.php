<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Business; // Ավելացնել Business մոդելը

class BusinessOnboardingController extends Controller
{
    /**
     * POST /api/business/complete-onboarding
     * Mark business onboarding completed (owner/manager/super_admin only)
     */
    public function complete(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // թույլատրված role-եր
        if (!in_array($user->role, [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_SUPER_ADMIN], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $business = $user->business; // Փոխել $salon-ից $business

        if (!$business) {
            return response()->json(['message' => 'Business not found'], 404); // Փոխել հաղորդագրությունը
        }

        $business->update([
            'is_onboarding_completed' => true,
        ]);

        return response()->json([
            'ok' => true,
            'business_id' => $business->id, // Փոխել salon_id-ից business_id
            'business_name' => $business->name,
            'business_type' => $business->business_type,
            'is_onboarding_completed' => true,
        ]);
    }

    /**
     * GET /api/business/onboarding-status
     * Check onboarding status
     */
    public function status(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $business = $user->business;

        if (!$business) {
            return response()->json(['message' => 'Business not found'], 404);
        }

        return response()->json([
            'data' => [
                'business_id' => $business->id,
                'business_name' => $business->name,
                'business_type' => $business->business_type,
                'is_onboarding_completed' => $business->is_onboarding_completed,
                'onboarding_step' => $this->getOnboardingStep($business),
            ]
        ]);
    }

    private function getOnboardingStep(Business $business): string
    {
        if ($business->is_onboarding_completed) {
            return 'completed';
        }

        // Ստուգել քայլերը
        if (!$business->services()->exists()) {
            return 'services'; // Ավելացնել ծառայություններ
        }

        if (!$business->staffSchedules()->exists()) {
            return 'schedule'; // Կարգավորել գրաֆիկը
        }

        return 'settings'; // Վերջին քայլ
    }
}
