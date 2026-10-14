<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Support\PhoneFormatter;
use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModelClass of \App\Models\Buyer
 *
 * @extends Builder<TModelClass>
 */
final class BuyerBuilder extends Builder
{
    /**
     * @param  array<string, mixed>|string|\Closure  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and'): static
    {
        if (is_string($column) && ($column === 'phone' || str_ends_with($column, '.phone'))) {
            if ($operator !== null && $value === null) {
                $operator = PhoneFormatter::toE164Id((string) $operator);
            } elseif ($value !== null) {
                $value = PhoneFormatter::toE164Id((string) $value);
            }
        } elseif (is_array($column) && isset($column['phone'])) {
            $column['phone'] = PhoneFormatter::toE164Id((string) $column['phone']);
        }

        return parent::where($column, $operator, $value, $boolean);
    }
}
