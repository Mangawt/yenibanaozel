<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'string',
                'max:255',
            ],

            'confirmed' => [
                'required',
                'accepted',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' =>
                'Hesabını silmek için mevcut şifreni yazmalısın.',

            'confirmed.required' =>
                'Hesap silme işlemini onaylamalısın.',

            'confirmed.accepted' =>
                'Hesap silme işlemini onaylamalısın.',
        ];
    }
}
