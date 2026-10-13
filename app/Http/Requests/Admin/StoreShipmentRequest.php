<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

final class StoreShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $today = Carbon::now('Asia/Jakarta')->format('Y-m-d');

        return [
            'buyer_id' => ['nullable', 'required_without:buyer', 'integer', 'exists:buyers,id'],
            'buyer' => ['nullable', 'required_without:buyer_id', 'array'],
            'buyer.name' => ['required_with:buyer', 'string', 'max:120'],
            'buyer.phone' => ['required_with:buyer', 'string', 'max:20'],
            'buyer.address' => ['required_with:buyer', 'string'],
            'buyer.area' => ['nullable', 'string', 'max:120'],
            'buyer.address_type' => ['nullable', 'string', 'in:RESIDENTIAL,COMMERCIAL'],
            'buyer.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'buyer.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'scheduled_date' => ['required', 'date', 'date_format:Y-m-d', "after_or_equal:{$today}"],
            'window_start' => ['required', 'date_format:H:i'],
            'window_end' => ['required', 'date_format:H:i', 'after:window_start'],
            'weight_kg' => ['required', 'numeric', 'min:0.01', 'max:999.99'],
            'cooler_box_code' => ['nullable', 'string', 'max:30'],
            'package_category' => ['nullable', 'string', 'max:50'],
            'service_type' => ['nullable', 'string', 'in:REGULER,INSTANT_FROZEN'],
            'koli_count' => ['nullable', 'integer', 'min:1'],
            'handling_instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
