<?php

namespace Escalated\Laravel\Http\Controllers\Api;

use Escalated\Laravel\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class MobileResourceController extends Controller
{
    public function departments(): JsonResponse
    {
        $departments = Department::active()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json([
            'data' => $departments->map(fn (Department $department) => [
                'id' => $department->id,
                'name' => $department->name,
                'slug' => $department->slug,
            ]),
        ]);
    }
}
