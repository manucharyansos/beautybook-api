<?php
// app/Http/Controllers/Api/ClientController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->user()->business;

        $query = $business->clients();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $clients = $query->orderBy('name')->paginate(20);

        return response()->json($clients);
    }

    public function store(Request $request)
    {
        $business = $request->user()->business;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],

            // Dental fields
            'birth_date' => ['nullable', 'date'],
            'blood_type' => ['nullable', 'string', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'medical_history' => ['nullable', 'string'],
            'allergies' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
        ]);

        $data['business_id'] = $business->id;

        $client = Client::create($data);

        return response()->json(['data' => $client], 201);
    }

    public function show(Client $client, Request $request)
    {
        $business = $request->user()->business;

        if ($client->business_id !== $business->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $client->load(['bookings' => function($q) {
            $q->latest()->limit(10);
        }]);

        return response()->json(['data' => $client]);
    }

    public function update(Request $request, Client $client)
    {
        $business = $request->user()->business;

        if ($client->business_id !== $business->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'birth_date' => ['nullable', 'date'],
            'blood_type' => ['nullable', 'string', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'medical_history' => ['nullable', 'string'],
            'allergies' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
        ]);

        $client->update($data);

        return response()->json(['data' => $client]);
    }

    public function bookings(Client $client, Request $request)
    {
        $business = $request->user()->business;

        if ($client->business_id !== $business->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $bookings = $client->bookings()
            ->with(['service', 'staff'])
            ->orderByDesc('starts_at')
            ->paginate(20);

        return response()->json($bookings);
    }
}
