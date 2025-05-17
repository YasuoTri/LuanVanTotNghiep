<?php

namespace App\Http\Controllers;

use App\Http\Requests\Certificate\StoreCertificateRequest;
use App\Http\Requests\Certificate\UpdateCertificateRequest;
use App\Models\Certificate;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\JsonResponse;
use App\Services\CloudinaryService;
use Exception;
class CertificateController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }
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
        try {
            $data = $request->validated();

            // Upload certificate PDF to Cloudinary
            if ($request->hasFile('certificate_file')) {
                $data['download_url'] = $this->cloudinaryService->upload(
                    $request->file('certificate_file'),
                    'certificates',
                );
            }

            $certificate = Certificate::create($data);
            return response()->json(['message' => 'Certificate created successfully', 'data' => $certificate], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'error' => 'An error occurred while creating the certificate.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
   public function update(UpdateCertificateRequest $request, $id): JsonResponse
    {
        try {
            $certificate = Certificate::findOrFail($id);
            $data = $request->validated();

            // Update certificate PDF if provided
            if ($request->hasFile('certificate_file')) {
                // Delete old file from Cloudinary if exists
                if ($certificate->download_url) {
                    $this->cloudinaryService->deleteByUrl($certificate->download_url);
                }

                // Upload new file to Cloudinary
                $data['download_url'] = $this->cloudinaryService->upload(
                    $request->file('certificate_file'),
                    'certificates',
                    // 'raw',
                    // 'cert_' . ($data['certificate_code'] ?? $certificate->certificate_code)
                );
            }

            $certificate->update($data);
            return response()->json(['message' => 'Certificate updated successfully', 'data' => $certificate], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'error' => 'An error occurred while updating the certificate.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
public function destroy($id): JsonResponse
    {
        try {
            $certificate = Certificate::findOrFail($id);

            // Delete certificate PDF from Cloudinary if exists
            if ($certificate->download_url) {
                $this->cloudinaryService->deleteByUrl($certificate->download_url);
            }

            $certificate->delete();
            return response()->json(['message' => 'Certificate deleted successfully'], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'error' => 'An error occurred while deleting the certificate.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
     /**
     * Display a listing of trashed enrollments.
     */
    public function trashed(): JsonResponse
    {
        $enrollments = Certificate::onlyTrashed()->get();
        return response()->json(['data' => $enrollments], 200);
    }

    /**
     * Restore a soft-deleted enrollment.
     */
    public function restore($id): JsonResponse
    {
        $enrollment = Certificate::onlyTrashed()->findOrFail($id);
        $enrollment->restore();
        return response()->json(['message' => 'Certificate restored successfully'], 200);
    }

    /**
     * Permanently delete a soft-deleted enrollment.
     */
    public function forceDelete($id): JsonResponse
    {
        $enrollment = Certificate::onlyTrashed()->findOrFail($id);
        $enrollment->forceDelete();
        return response()->json(['message' => 'Certificate permanently deleted'], 200);
    }
}