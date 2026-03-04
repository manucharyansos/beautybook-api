<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BusinessTypeController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $business = $user?->business;

        if (!$business) {
            return response()->json([
                'message' => 'Business context required.',
                'code' => 'business_required',
            ], 403);
        }

        $key = $business->business_type ?? 'salon';

        $map = [
            'salon' => ['label' => 'Beauty Salon', 'icon' => 'sparkles'],
            'clinic' => ['label' => 'Dental Clinic', 'icon' => 'award'],
        ];

        $meta = $map[$key] ?? ['label' => ucfirst((string) $key), 'icon' => 'sparkles'];

        return response()->json([
            'data' => [
                'key' => $key,
                'label' => $meta['label'],
                'icon' => $meta['icon'],
            ],
        ]);
    }
}