<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Http\Controllers\Controller;
use App\Models\RiderDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profile = $request->user()->riderProfile;
        abort_unless($profile, 404);

        $docs = RiderDocument::where('rider_profile_id', $profile->id)->latest()->get();

        return response()->json([
            'data' => $docs->map(fn (RiderDocument $d) => [
                'id' => $d->id,
                'document_type' => $d->document_type,
                'file_url' => url('storage/'.$d->file_path),
                'status' => $d->status,
                'rejection_reason' => $d->rejection_reason,
                'created_at' => $d->created_at,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $profile = $request->user()->riderProfile;
        abort_unless($profile, 404);

        $data = $request->validate([
            'document_type' => 'required|string|in:cnic,license,bike_registration,other',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $path = $request->file('file')->store('rider-documents', 'public');

        $doc = RiderDocument::create([
            'rider_profile_id' => $profile->id,
            'document_type' => $data['document_type'],
            'file_path' => $path,
            'status' => 'pending',
        ]);

        $profile->update(['documents_verified' => false]);

        return response()->json([
            'data' => [
                'id' => $doc->id,
                'document_type' => $doc->document_type,
                'file_url' => url('storage/'.$doc->file_path),
                'status' => $doc->status,
            ],
        ], 201);
    }
}
