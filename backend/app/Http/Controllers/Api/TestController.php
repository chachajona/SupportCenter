<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TestController extends Controller
{
    /**
     * Return a simple greeting.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sayHello(): JsonResponse
    {
        return response()->json(['message' => 'Hello from Laravel API!']);
    }
}
