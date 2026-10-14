<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

/**
 * The frozen-goods storefront.
 *
 * Browsing is open (the customer app lets someone look around before signing
 * up), so both endpoints are public; only the order itself needs an account.
 */
final class CatalogController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::query()
            ->where('is_available', true)
            ->inCatalogOrder()
            ->get();

        return response()->json([
            'data' => ProductResource::collection($products),
            'meta' => [
                'count' => $products->count(),
                'standard_temp' => 'Beku -18°C',
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::query()
            ->where('is_available', true)
            ->where('slug', $slug)
            ->first();

        if ($product === null) {
            return response()->json([
                'message' => 'Produk tidak ditemukan.',
                'code' => 'PRODUCT_NOT_FOUND',
            ], 404);
        }

        return response()->json(['data' => new ProductResource($product)]);
    }
}
