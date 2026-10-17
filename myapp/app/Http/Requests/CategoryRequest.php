<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('categories')->where('user_id', $this->user()->id)->ignore($this->route('category'))],
        ];
    }
}
