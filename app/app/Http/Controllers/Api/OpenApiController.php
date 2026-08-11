<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class OpenApiController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(json_decode(file_get_contents(base_path('openapi.json')), true, flags: JSON_THROW_ON_ERROR));
    }
}
