<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\GeofencePolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The hub editing the tolerated radius. Only the segments the console offers
 * can be written, and each one carries its own bounds.
 */
final class UpdateGeofenceRequest extends FormRequest
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
        $rules = ['policies' => ['required', 'array', 'min:1']];

        foreach (array_keys(GeofencePolicy::SEGMENTS) as $serviceType) {
            $segment = GeofencePolicy::SEGMENTS[$serviceType];

            $rules["policies.{$serviceType}"] = ['required', 'integer', 'between:'.$segment['min'].','.$segment['max']];
        }

        return $rules;
    }

    /**
     * @return array<string, int>
     */
    public function policies(): array
    {
        $input = $this->validated('policies');

        return is_array($input) ? array_map(static fn (mixed $value): int => (int) $value, $input) : [];
    }
}
