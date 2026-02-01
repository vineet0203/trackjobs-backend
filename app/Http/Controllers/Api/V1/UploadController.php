<?php
// app/Http/Controllers/Api/V1/UploadController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Upload\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Traits\ApiResponse;

class UploadController extends Controller
{
    use ApiResponse;
    public function __construct(
        private FileUploadService $uploadService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/uploads/temp",
     *     summary="Upload temporary file",
     *     tags={"Uploads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="File to upload"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="temp_id", type="string", example="tmp_abc123"),
     *             @OA\Property(property="url", type="string", example="http://example.com/storage/temp/file.jpg"),
     *             @OA\Property(property="expires_at", type="string", format="date-time")
     *         )
     *     )
     * )
     */
    public function uploadTemporary(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file',
        ]);

        try {
            $result = $this->uploadService->uploadTemporaryFile(
                $request->file('file'),
                Auth::id()
            );

            return $this->successResponse($result, 'File uploaded successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/uploads/limits",
     *     summary="Get upload limits and allowed types",
     *     tags={"Uploads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Upload limits retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="max_size_mb", type="number", format="float", example=10),
     *             @OA\Property(
     *                 property="allowed_types",
     *                 type="object",
     *                 example={"image/jpeg": "JPEG Image", "application/pdf": "PDF Document"}
     *             )
     *         )
     *     )
     * )
     */
    public function getUploadLimits(): JsonResponse
    {
        $limits = $this->uploadService->getUploadLimits();

        return $this->successResponse($limits, 'Upload limits retrieved');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/uploads/system-limits",
     *     summary="Get system upload limits",
     *     tags={"Uploads"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="System limits retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="php_limits", type="object"),
     *             @OA\Property(property="service_limits", type="object")
     *         )
     *     )
     * )
     */
    public function getSystemLimits(): JsonResponse
    {
        $limits = [
            'php_limits' => $this->uploadService->getPhpLimits(),
            'service_limits' => $this->uploadService->getUploadLimits(),
            'recommended_max_size' => min(
                $this->uploadService->getPhpLimits()['upload_max_filesize_bytes'],
                $this->uploadService->getPhpLimits()['post_max_size_bytes'],
                $this->uploadService->getUploadLimits()['max_size_kb'] * 1024
            ),
        ];

        return $this->successResponse($limits, 'System limits retrieved');
    }
}
