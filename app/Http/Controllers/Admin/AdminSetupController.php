<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SetupStatus;
use App\Services\Setup\SetupResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AdminSetupController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'permission:system.manage']);
    }

    public function status()
    {
        $setupLockFile = storage_path('app/setup.lock');
        $lockData = null;

        if (file_exists($setupLockFile)) {
            $lockData = json_decode(file_get_contents($setupLockFile), true);
        }

        return Inertia::render('Admin/SetupStatus', [
            'setupCompleted' => file_exists($setupLockFile),
            'lockData' => $lockData,
            'setupStatuses' => SetupStatus::orderBy('id')->get(),
            'setupProgress' => SetupStatus::getProgress(),
        ]);
    }

    public function resetSetup(Request $request, SetupResetService $resetService)
    {
        // This is a dangerous operation - require additional confirmation
        $request->validate([
            'confirmation' => 'required|in:RESET_SETUP_CONFIRM',
            'reason' => 'required|string|min:10',
        ]);

        try {
            DB::transaction(function () use ($request, $resetService) {
                $resetService->reset(Auth::id(), $request->reason, [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Setup system has been reset. You will be redirected to setup.',
                'redirect' => route('setup.index'),
            ]);
        } catch (\Exception $e) {
            Log::error('Setup reset failed', [
                'error' => $e->getMessage(),
                'admin_user' => Auth::user()?->email,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Setup reset failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getSetupInfo()
    {
        $setupLockFile = storage_path('app/setup.lock');

        return response()->json([
            'setup_completed' => file_exists($setupLockFile),
            'lock_file_exists' => file_exists($setupLockFile),
            'setup_statuses' => SetupStatus::orderBy('id')->get(),
            'progress' => SetupStatus::getProgress(),
            'current_step' => SetupStatus::getCurrentStep(),
        ]);
    }
}
