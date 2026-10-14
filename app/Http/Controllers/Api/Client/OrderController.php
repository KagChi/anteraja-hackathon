<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Actions\Client\CreateClientOrder;
use App\Http\Requests\Api\Client\OrderIndexRequest;
use App\Http\Requests\Api\Client\StoreOrderRequest;
use App\Http\Resources\Client\OrderSummaryResource;
use App\Http\Resources\Client\OrderReceiptResource;
use App\Models\Buyer;
use App\Models\Package;
use App\Queries\ClientOrderListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrderController extends Controller
{
    /**
     * Checkout: the customer picks frozen goods, a service and a delivery
     * window, and the order becomes a shipment in the hub queue.
     */
    public function store(StoreOrderRequest $request, CreateClientOrder $action): JsonResponse
    {
        /** @var Buyer $buyer */
        $buyer = $request->user();

        $package = $action->execute($buyer, $request->validated());
        $package->loadMissing(['buyer', 'items.product', 'statusRef']);

        return response()->json([
            'message' => 'Pesanan berhasil dibuat dengan resi '.$package->tracking_number.'.',
            'data' => new OrderReceiptResource($package),
        ], 201);
    }

    public function index(OrderIndexRequest $request, ClientOrderListQuery $listQuery): JsonResponse
    {
        /** @var Buyer $buyer */
        $buyer = $request->user();

        $tab = (string) $request->validated('tab', 'active');
        $search = $request->validated('q');
        $perPage = (int) $request->validated('per_page', 10);
        $page = (int) $request->validated('page', 1);

        $result = $listQuery->execute(
            buyerId: $buyer->id,
            tab: $tab,
            search: $search,
            perPage: $perPage,
            page: $page
        );

        $paginator = $result['paginator'];
        $counts = $result['counts'];

        return response()->json([
            'data' => OrderSummaryResource::collection($paginator->items()),
            'meta' => [
                'active_count' => $counts['active'],
                'completed_count' => $counts['completed'],
                'standard_temp' => 'Beku -18°C',
                'sla_rate' => '96%',
                'tab_counts' => [
                    'active' => $counts['active'],
                    'completed' => $counts['completed'],
                    'issue' => $counts['issue'],
                    'total' => $counts['total'],
                ],
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ],
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        /** @var Package $package */
        $package = $request->attributes->get('client_package');

        return response()->json([
            'data' => new OrderSummaryResource($package),
        ]);
    }
}
