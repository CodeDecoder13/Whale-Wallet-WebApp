<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuickEntryRequest extends FormRequest
{
    public function rules(): array
    {
        return ['text' => ['required', 'string', 'max:200']];
    }
}
