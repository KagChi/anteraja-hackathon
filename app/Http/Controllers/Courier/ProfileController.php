<?php

declare(strict_types=1);

namespace App\Http\Controllers\Courier;

use App\Enums\PackageStatus;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var Staff $courier */
        $courier = $request->user();

        $completedToday = Package::where('courier_id', $courier->id)
            ->where('status', PackageStatus::RECEIVED)
            ->whereDate('updated_at', Carbon::today())
            ->count();

        return response()->json([
            'profile' => [
                'name' => $courier->name,
                'status' => $courier->is_active ? 'Aktif' : 'Nonaktif',
                'id' => 'KRR-'.$courier->id,
                'phone' => $courier->phone ?? '-',
                'hub' => (string) config('coldchain.hub_name', 'Hub Jakarta Selatan'),
                'vehicle' => 'Motor Box #04',
                'stats' => [
                    ['label' => 'Selesai hari ini', 'value' => (string) $completedToday],
                    ['label' => 'Tepat waktu', 'value' => '97%'],
                    ['label' => 'Rating', 'value' => '4.8'],
                ],
            ],
        ]);
    }
}
