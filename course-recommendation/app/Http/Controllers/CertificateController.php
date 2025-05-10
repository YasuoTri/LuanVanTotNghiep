<?php

namespace App\Http\Controllers;

use App\Http\Requests\Certificate\StoreCertificateRequest;
use App\Http\Requests\Certificate\UpdateCertificateRequest;
use App\Models\Certificate;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\JsonResponse;

class CertificateController extends Controller
{
    public function index(): JsonResponse
    {
        $certificates = Certificate::all();
        return response()->json(['data' => $certificates]);
    }

    public function show($id): JsonResponse
    {
        $certificate = Certificate::findOrFail($id);
        return response()->json(['data' => $certificate]);
    }

    public function store(StoreCertificateRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Upload certificate PDF to Cloudinary
        if ($request->hasFile('certificate_file')) {
            $uploadedFile = Cloudinary::upload($request->file('certificate_file')->getRealPath(), [
                'folder' => 'certificates',
                'resource_type' => 'raw',
                'public_id' => 'cert_' . $data['certificate_code'], // Dùng certificate_code làm public_id
            ]);
            $data['download_url'] = $uploadedFile->getSecurePath();
        }

        $certificate = Certificate::create($data);
        return response()->json(['message' => 'Certificate created successfully', 'data' => $certificate], 201);
    }

    public function update(UpdateCertificateRequest $request, $id): JsonResponse
    {
        $certificate = Certificate::findOrFail($id);
        $data = $request->validated();

        // Update certificate PDF if provided
        if ($request->hasFile('certificate_file')) {
            // Delete old file from Cloudinary if exists
            if ($certificate->download_url) {
                $publicId = 'certificates/' . pathinfo($certificate->download_url, PATHINFO_FILENAME);
                Cloudinary::destroy($publicId, ['resource_type' => 'raw']);
            }
            $uploadedFile = Cloudinary::upload($request->file('certificate_file')->getRealPath(), [
                'folder' => 'certificates',
                'resource_type' => 'raw',
                'public_id' => 'cert_' . ($data['certificate_code'] ?? $certificate->certificate_code),
            ]);
            $data['download_url'] = $uploadedFile->getSecurePath();
        }

        $certificate->update($data);
        return response()->json(['message' => 'Certificate updated successfully', 'data' => $certificate]);
    }

    public function destroy($id): JsonResponse
    {
        $certificate = Certificate::findOrFail($id);

        // Delete certificate PDF from Cloudinary if exists
        if ($certificate->download_url) {
            $publicId = 'certificates/' . pathinfo($certificate->download_url, PATHINFO_FILENAME);
            Cloudinary::destroy($publicId, ['resource_type' => 'raw']);
        }

        $certificate->delete();
        return response()->json(['message' => 'Certificate deleted successfully']);
    }
}