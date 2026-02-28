<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $query = Plan::query();

        $showHidden = $request->boolean('show_hidden', false);
        if (!$showHidden) {
            $query->where('is_visible', true);
        }

        $plans = $query->orderBy('sort_order')->orderBy('id')->get();

        return response()->json(['success' => true, 'data' => $plans]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:plans,code',
            'business_type' => 'nullable|in:salon,clinic,beauty,dental',
            'description' => 'nullable|string',
            'price_beauty' => 'required|numeric|min:0',
            'price_dental' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'seats' => 'required|integer|min:1',
            'duration_days' => 'required|integer|min:1',
            'locations' => 'nullable|integer|min:1',
            'features' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'is_visible' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        // keep seats synced if staff_limit sent
        if (isset($validated['features']['staff_limit'])) {
            $validated['seats'] = (int) $validated['features']['staff_limit'];
        }

        $plan = Plan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Plan created successfully',
            'data' => $plan
        ], 201);
    }

    public function show(Plan $plan)
    {
        return response()->json(['success' => true, 'data' => $plan]);
    }

    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => ['sometimes', 'string', Rule::unique('plans', 'code')->ignore($plan->id)],
            'business_type' => 'nullable|in:salon,clinic,beauty,dental',
            'description' => 'nullable|string',
            'price_beauty' => 'sometimes|numeric|min:0',
            'price_dental' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|max:10',
            'seats' => 'sometimes|integer|min:1',
            'duration_days' => 'sometimes|integer|min:1',
            'locations' => 'nullable|integer|min:1',
            'features' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
            'is_visible' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        if (isset($validated['features']['staff_limit'])) {
            $validated['seats'] = (int) $validated['features']['staff_limit'];
        }

        $plan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Plan updated successfully',
            'data' => $plan
        ]);
    }

    public function destroy(Plan $plan)
    {
        if ($plan->subscriptions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete plan with active subscriptions. Deactivate it instead.'
            ], 422);
        }

        $plan->delete();

        return response()->json(['success' => true, 'message' => 'Plan deleted successfully']);
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'plans' => 'required|array',
            'plans.*.id' => 'required|exists:plans,id',
            'plans.*.sort_order' => 'required|integer',
        ]);

        foreach ($validated['plans'] as $item) {
            Plan::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['success' => true]);
    }

    public function toggleActive(Plan $plan)
    {
        $plan->update(['is_active' => !$plan->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $plan->is_active
        ]);
    }

    public function toggleVisible(Plan $plan)
    {
        $plan->update(['is_visible' => !$plan->is_visible]);

        return response()->json([
            'success' => true,
            'is_visible' => $plan->is_visible
        ]);
    }
}
