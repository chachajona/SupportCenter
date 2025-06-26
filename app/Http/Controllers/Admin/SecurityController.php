<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Response;

final class SecurityController extends Controller
{
    public function index(Request $request): Response
    {
        return inertia('admin/security/index');
    }
}
