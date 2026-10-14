<?php

declare(strict_types=1);

namespace App\Http\Requests\Courier;

use App\Http\Requests\Courier\Concerns\ValidatesGpsSignals;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Tiba di lokasi" with the current fix. The radius is not enforced here: the
 * courier may still be walking from where the vehicle stopped, so arriving only
 * records the distance.
 */
final class MarkArrivedRequest extends FormRequest
{
    use ValidatesGpsSignals;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->gpsSignalRules();
    }
}
