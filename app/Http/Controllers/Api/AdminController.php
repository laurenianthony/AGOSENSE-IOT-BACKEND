<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function pendingUsers(): JsonResponse
    {
        $users = User::query()
            ->where('is_approved', false)
            ->orderBy('created_at')
            ->get(['id', 'name', 'email', 'created_at']);

        return response()->json(['users' => $users]);
    }

    public function approveUser(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $user->forceFill(['is_approved' => true])->save();

        return response()->json([
            'message' => 'User approved successfully.',
            'user' => $user->only(['id', 'name', 'email', 'is_approved']),
        ]);
    }

    public function rejectUser(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $user->delete();

        return response()->json([
            'message' => 'User registration rejected and deleted.',
        ]);
    }
}
