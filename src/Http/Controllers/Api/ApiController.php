<?php

namespace ThinkNeverland\Tapped\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ApiController extends Controller
{
    /**
     * Base API controller for the Tapped package.
     * This serves as the parent class for all API endpoints.
     */

    /**
     * Return a standardized JSON response.
     *
     * @param  mixed  $data
     * @param  string  $message
     * @param  int  $statusCode
     * @param  array  $headers
     * @return JsonResponse
     */
    protected function respondWithJson($data = null, string $message = '', int $statusCode = 200, array $headers = []): JsonResponse
    {
        $response = [
            'success' => $statusCode >= 200 && $statusCode < 300,
            'message' => $message,
            'data' => $data,
        ];

        return response()->json($response, $statusCode, $headers);
    }

    /**
     * Return a success response.
     *
     * @param  mixed  $data
     * @param  string  $message
     * @param  array  $headers
     * @return JsonResponse
     */
    protected function respondWithSuccess($data = null, string $message = 'Success', array $headers = []): JsonResponse
    {
        return $this->respondWithJson($data, $message, 200, $headers);
    }

    /**
     * Return an error response.
     *
     * @param  string  $message
     * @param  int  $statusCode
     * @param  array  $headers
     * @return JsonResponse
     */
    protected function respondWithError(string $message = 'Error', int $statusCode = 400, array $headers = []): JsonResponse
    {
        return $this->respondWithJson(null, $message, $statusCode, $headers);
    }

    /**
     * Return a not found response.
     *
     * @param  string  $message
     * @param  array  $headers
     * @return JsonResponse
     */
    protected function respondNotFound(string $message = 'Resource not found', array $headers = []): JsonResponse
    {
        return $this->respondWithError($message, 404, $headers);
    }

    /**
     * Return a validation error response.
     *
     * @param  array  $errors
     * @param  string  $message
     * @param  array  $headers
     * @return JsonResponse
     */
    protected function respondValidationError(array $errors, string $message = 'Validation failed', array $headers = []): JsonResponse
    {
        return $this->respondWithJson(['errors' => $errors], $message, 422, $headers);
    }

    /**
     * Return an unauthorized response.
     *
     * @param  string  $message
     * @param  array  $headers
     * @return JsonResponse
     */
    protected function respondUnauthorized(string $message = 'Unauthorized', array $headers = []): JsonResponse
    {
        return $this->respondWithError($message, 401, $headers);
    }
}
