<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Users type pesos; fields listed in $pesos reach the rules (and the Action) as integer centavos. */
abstract class PesoRequest extends FormRequest
{
    protected array $pesos = [];

    protected function prepareForValidation(): void
    {
        if ($this->is('api/*')) {
            return; // the API already sends centavos
        }

        foreach ($this->pesos as $field) {
            $this->merge([$field => self::centavos($this->input($field))]);
        }
    }

    /** Non-numeric input is left as is so the integer rule rejects it. */
    public static function centavos(mixed $pesos): mixed
    {
        return is_numeric($pesos) ? (int) round($pesos * 100) : $pesos;
    }
}
