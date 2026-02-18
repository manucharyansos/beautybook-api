<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('price')
            ->get(['id','name','code','price','currency','seats','locations']);

        return response()->json(['data' => $plans]);
    }
}
