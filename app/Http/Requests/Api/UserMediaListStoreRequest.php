<?php

namespace App\Http\Requests\Api;

use App\Support\MediaListStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserMediaListStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'media_id' => ['required', 'integer', 'exists:media,id'],
            'status' => ['required', Rule::in(MediaListStatus::all())],
            'progress' => ['nullable', 'integer', 'min:0'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'user_id' => ['prohibited'],
            'owner_id' => ['prohibited'],
        ];
    }
}
