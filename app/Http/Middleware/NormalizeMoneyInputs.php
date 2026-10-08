<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * Amount fields are typed with thousands separators ("150 000", see [data-money] in
 * public/js/admin.js): strip the spaces (regular, non-breaking, narrow) before validation, so
 * the form requests keep seeing plain numbers — even if the browser's JS didn't run.
 */
class NormalizeMoneyInputs extends TransformsRequest
{
    private const KEYS = '/^(unit_rate|hourly_rate|day_rate|night_rate|stay_rate|amount|received|payments\.\d+\.(amount|received))$/';

    protected function transform($key, $value)
    {
        if (! is_string($value) || ! preg_match(self::KEYS, $key)) {
            return $value;
        }

        return preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $value);
    }
}
