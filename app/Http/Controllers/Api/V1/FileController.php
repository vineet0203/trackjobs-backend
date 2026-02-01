<?php
// app/Http/Controllers/Api/V1/FileController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\File\FileRequest;
use App\Services\File\FileService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;

class FileController extends Controller
{
    use ApiResponse;

    public function __construct(
        private FileService $fileService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/files/temp/{path}",
     *     summary="Serve temporary file",
     *     tags={"Files"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="path",
     *         in="path",
     *         required=true,
     *         description="File path",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="download",
     *         in="query",
     *         required=false,
     *         description="Force download",
     *         @OA\Schema(type="boolean", example=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File served successfully",
     *         @OA\MediaType(
     *             mediaType="application/octet-stream",
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="File not found or expired"
     *     )
     * )
     */
    public function temporary(FileRequest $request, string $path): StreamedResponse|JsonResponse
    {
        try {
            $forceDownload = $request->boolean('download', false);
            $response = $this->fileService->serveTemporaryFile($path, $forceDownload);

            if (!$response) {
                return $this->errorResponse('File not found or expired', 404);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Error serving temporary file', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to serve file', 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/files/public/{path}",
     *     summary="Serve public file",
     *     tags={"Files"},
     *     @OA\Parameter(
     *         name="path",
     *         in="path",
     *         required=true,
     *         description="File path",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="download",
     *         in="query",
     *         required=false,
     *         description="Force download",
     *         @OA\Schema(type="boolean", example=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File served successfully",
     *         @OA\MediaType(
     *             mediaType="application/octet-stream",
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="File not found"
     *     )
     * )
     */
    public function public(FileRequest $request, string $path): StreamedResponse|JsonResponse
    {
        try {
            $forceDownload = $request->boolean('download', false);
            $response = $this->fileService->servePublicFile($path, $forceDownload);

            if (!$response) {
                return $this->errorResponse('File not found', 404);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Error serving public file', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to serve file', 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/files/private/{path}",
     *     summary="Serve private file",
     *     tags={"Files"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="path",
     *         in="path",
     *         required=true,
     *         description="File path",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="download",
     *         in="query",
     *         required=false,
     *         description="Force download",
     *         @OA\Schema(type="boolean", example=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File served successfully",
     *         @OA\MediaType(
     *             mediaType="application/octet-stream",
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="File not found"
     *     )
     * )
     */
    public function private(FileRequest $request, string $path): StreamedResponse|JsonResponse
    {
        try {
            $forceDownload = $request->boolean('download', false);
            $response = $this->fileService->servePrivateFile($path, $forceDownload);

            if (!$response) {
                return $this->errorResponse('File not found', 404);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Error serving private file', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to serve file', 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/files/info",
     *     summary="Get file information",
     *     tags={"Files"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="path",
     *         in="query",
     *         required=true,
     *         description="File path",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         required=false,
     *         description="File type (private, public)",
     *         @OA\Schema(type="string", enum={"private", "public"}, default="private")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File information retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="size", type="integer"),
     *                 @OA\Property(property="mime_type", type="string"),
     *                 @OA\Property(property="last_modified", type="string", format="date-time"),
     *                 @OA\Property(property="url", type="string"),
     *                 @OA\Property(property="path", type="string"),
     *                 @OA\Property(property="disk", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="File not found"
     *     )
     * )
     */
    public function info(FileRequest $request): JsonResponse
    {
        try {
            $path = $request->input('path');
            $type = $request->input('type', 'private');

            if (!$this->fileService->validatePath($path)) {
                return $this->errorResponse('Invalid file path', 400);
            }

            $info = $this->fileService->getFileInfo($path, $type);

            if (!$info) {
                return $this->errorResponse('File not found', 404);
            }

            return $this->successResponse($info, 'File information retrieved');
        } catch (\Exception $e) {
            Log::error('Error getting file info', [
                'path' => $request->input('path'),
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Failed to get file information', 500);
        }
    }
}