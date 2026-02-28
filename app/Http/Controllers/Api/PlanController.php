<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $businessType = Plan::normalizeBusinessType($request->get('business_type', 'salon'));

        $plans = Plan::query()
            ->where('is_active', true)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (Plan $p) => $p->allowsBusinessType($businessType))
            ->values()
            ->map(function (Plan $plan) use ($businessType) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'code' => $plan->code,
                    'version' => (int)($plan->version ?? 1),
                    'allowed_business_types' => $plan->allowed_business_types ?? ['salon','clinic'],
                    'description' => $plan->description,
                    'price' => $plan->getPriceForBusinessType($businessType),
                    'currency' => $plan->currency,
                    'staff_limit' => $plan->staffLimit(),
                    'duration_days' => $plan->duration_days,
                    'locations' => $plan->locations,
                    'features' => $plan->getFeaturesList(),
                    'period' => $plan->duration_days === 365 ? 'տարի' : 'ամիս',
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $plans
        ]);
    }
}
