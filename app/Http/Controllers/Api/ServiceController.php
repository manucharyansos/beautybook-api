<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * GET /api/services
     */
    public function index(Request $request)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        $q = Service::query();

        // Tenant filter
        if (!$actor->isSuperAdmin()) {
            $q->where('business_id', $actor->business_id);
        } else {
            // optional filter for super-admin
            if ($request->filled('business_id')) {
                $q->where('business_id', $request->integer('business_id'));
            }
        }

        return response()->json([
            'data' => $q->orderBy('id')->get(),
        ]);
    }

    /**
     * POST /api/services
     */
    public function store(Request $request)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        if (!in_array($actor->role, [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_SUPER_ADMIN], true)) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'business_id' => ['nullable', 'integer'], // super-admin only
        ]);

        $businessId = $actor->business_id;

        // super-admin can create for a specific business if provided
        if ($actor->isSuperAdmin() && !empty($data['business_id'])) {
            $businessId = (int) $data['business_id'];
        }

        $service = Service::create([
            'name' => $data['name'],
            'duration_minutes' => (int) $data['duration_minutes'],
            'price' => $data['price'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'business_id' => $businessId, // 🔒 FORCE TENANT
        ]);

        return response()->json(['data' => $service], 201);
    }

    /**
     * PUT /api/services/{service}
     */
    public function update(Request $request, Service $service)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        // Tenant safety
        if (!$actor->isSuperAdmin() && (int) $service->business_id !== (int) $actor->business_id) {
            abort(404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $service->update($data);

        return response()->json(['data' => $service]);
    }

    /**
     * DELETE /api/services/{service}
     */
    public function destroy(Request $request, Service $service)
    {
        $actor = $request->user();
        if (!$actor) abort(401);

        if (!$actor->isSuperAdmin() && (int) $service->business_id !== (int) $actor->business_id) {
            abort(404);
        }

        $service->delete();

        return response()->json(['ok' => true]);
    }
}
