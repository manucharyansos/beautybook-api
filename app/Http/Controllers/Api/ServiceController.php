<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    // GET /api/services
    public function index(Request $request)
    {
        // staff-ը կարող է տեսնել, owner/manager նույնպես
        $services = Service::query()
            ->orderByDesc('id')
            ->get(['id','salon_id','name','duration_minutes','price','is_active','created_at']);

        return response()->json(['data' => $services]);
    }

    // POST /api/services
    public function store(Request $request)
    {
        $this->requireManagePermission($request->user());

        $data = $request->validate([
            'name' => ['required','string','min:2','max:120'],
            'duration_minutes' => ['required','integer','min:5','max:600'],
            'price' => ['nullable','integer','min:0','max:100000000'],
            'is_active' => ['sometimes','boolean'],
            'currency' => ['nullable','in:AMD,USD,EUR'],
        ]);

        // salon_id ավտոմատ կդրվի BelongsToSalon trait-ի creating-ում (եթե ճիշտ է fix արել)
        $service = Service::create([
            'name' => $data['name'],
            'duration_minutes' => (int)$data['duration_minutes'],
            'price' => $data['price'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'currency' => $data['currency'] ?? 'AMD',
        ]);

        return response()->json(['data' => $service], 201);
    }

    // PUT /api/services/{service}
    public function update(Request $request, Service $service)
    {
        $this->requireManagePermission($request->user());

        // Tenant safety: եթե BelongsToSalon scope-ը միացված է, սա արդեն կսահմանափակի,
        // բայց լրացուցիչ ստուգումը լավ practice է։
        if ($request->user()->salon_id && $service->salon_id !== $request->user()->salon_id) {
            abort(404);
        }

        $data = $request->validate([
            'name' => ['sometimes','string','min:2','max:120'],
            'duration_minutes' => ['sometimes','integer','min:5','max:600'],
            'price' => ['nullable','integer','min:0','max:100000000'],
            'is_active' => ['sometimes','boolean'],
            'currency' => ['sometimes','in:AMD,USD,EUR'],
        ]);

        $service->fill($data);
        $service->save();

        return response()->json(['data' => $service]);
    }

    // DELETE /api/services/{service}
    public function destroy(Request $request, Service $service)
    {
        $this->requireManagePermission($request->user());

        if ($request->user()->salon_id && $service->salon_id !== $request->user()->salon_id) {
            abort(404);
        }

        $service->delete();

        return response()->json(['ok' => true]);
    }

    private function requireManagePermission(User $user): void
    {
        // owner/manager/super_admin կարող են CRUD անել
        if (in_array($user->role, [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_SUPER_ADMIN], true)) {
            return;
        }
        abort(403);
    }
}
