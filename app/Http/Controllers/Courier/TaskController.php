<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Queries\CourierTaskQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TaskController extends Controller
{
    public function index(Request $request, CourierTaskQuery $query): JsonResponse
    {
        /** @var Staff $courier */
        $courier = $request->user();

        $data = $query->getTasksForCourier($courier);

        return response()->json([
            'newTasks' => $data['newTasks'],
            'activeTasks' => $data['activeTasks'],
            'courier' => [
                'name' => $courier->name,
                'status' => $courier->is_active ? 'Aktif' : 'Nonaktif',
            ],
        ]);
    }
}
