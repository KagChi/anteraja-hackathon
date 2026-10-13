<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Packages\CreatePackage;
use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexShipmentRequest;
use App\Http\Requests\Admin\StoreShipmentRequest;
use App\Models\Buyer;
use App\Models\Package;
use App\Models\Product;
use App\Models\Staff;
use App\Queries\ShipmentIndexQuery;
use App\Services\Client\ClientLinkService;
use App\Support\DisplayTime;
use App\Support\PackageTimelineBuilder;
use Illuminate\Http\JsonResponse;

final class ShipmentController extends Controller
{
    public function index(IndexShipmentRequest $request, ShipmentIndexQuery $query): JsonResponse
    {
        $filters = $request->toFilters();
        $paginated = $query->paginate($filters);

        $couriers = Staff::where('role', StaffRole::COURIER)
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'phone']);

        $transformedRows = collect($paginated->items())->map(function (Package $pkg) {
            $catLabel = $pkg->latestIssue?->issue_category?->label();
            $statusLabel = $pkg->statusRef?->label ?? $pkg->status->value;
            if ($pkg->status->value === 'ISSUE_REPORTED' && $catLabel !== null) {
                $statusLabel = "Kendala ({$catLabel})";
            }

            return [
                'id' => $pkg->id,
                'tracking' => $pkg->tracking_number,
                'buyer' => $pkg->buyer?->name ?? '-',
                'location' => $pkg->buyer?->area ?? $pkg->buyer?->address ?? '-',
                'schedule' => DisplayTime::windowLabel($pkg->scheduled_at, $pkg->scheduled_end_at),
                'courier' => $pkg->courier?->name,
                'courier_id' => $pkg->courier_id,
                'status' => $statusLabel,
                'statusKey' => strtolower($pkg->status->value),
                'tone' => $pkg->status->tone()->value,
                'hasIssue' => $pkg->status->value === 'ISSUE_REPORTED',
                'availableAction' => $pkg->availableAdminActions(),
            ];
        });

        $data = [
            'shipments' => $transformedRows,
            'paginator' => [
                'total' => $paginated->total(),
                'per_page' => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'filters' => [
                'q' => $filters->q,
                'status' => $filters->status,
                'courier' => $filters->courier,
                'schedule' => $filters->schedule,
                'per_page' => $filters->perPage,
            ],
            'couriers' => $couriers,
        ];

        return response()->json($data);
    }

    public function create(): JsonResponse
    {
        $products = Product::orderBy('name', 'asc')->get();
        $buyers = Buyer::orderBy('name', 'asc')->limit(50)->get();

        return response()->json([
            'products' => $products,
            'buyers' => $buyers,
        ]);
    }

    public function store(StoreShipmentRequest $request, CreatePackage $action): JsonResponse
    {
        /** @var Staff $user */
        $user = $request->user();
        $package = $action->execute($request->validated(), $user);

        return response()->json([
            'message' => "Shipment {$package->tracking_number} dibuat dan konfirmasi dikirim ke pembeli.",
            'package' => $package->load(['buyer', 'items.product', 'statusRef']),
        ], 201);
    }

    public function show(string $trackingNumber, PackageTimelineBuilder $timelineBuilder): JsonResponse
    {
        $package = Package::where('tracking_number', $trackingNumber)
            ->with([
                'buyer',
                'courier',
                'statusRef',
                'items.product',
                'delivery',
                'conditionChecks.issueReport.decidedByStaff',
                'notifications',
                'statusLogs.changedByStaff',
            ])
            ->firstOrFail();

        $timeline = $timelineBuilder->build($package);

        return response()->json([
            'package' => $package,
            'timeline' => $timeline,
            'client_url' => app(ClientLinkService::class)->entry($package),
        ]);
    }
}
