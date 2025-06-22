<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user/permissions', function (Request $request) {
        $user = $request->user();

        // Load roles, permissions and department relations in one go
        $user->load(['roles.permissions', 'department']);

        // Flatten permissions to unique list of names
        $permissions = $user->getAllPermissions()->pluck('name')->unique()->values();

        return response()->json([
            'roles' => $user->roles->map(fn($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'display_name' => $r->display_name,
                'description' => $r->description,
                'hierarchy_level' => $r->hierarchy_level,
                'is_active' => $r->is_active,
            ]),
            'permissions' => $permissions,
            'departments' => $user->department ? [
                [
                    'id' => $user->department->id,
                    'name' => $user->department->name,
                    'parent_id' => $user->department->parent_id,
                    'path' => $user->department->path,
                    'is_active' => $user->department->is_active,
                ]
            ] : [],
        ]);
    });
});
