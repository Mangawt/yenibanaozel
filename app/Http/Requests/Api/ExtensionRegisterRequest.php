<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ExtensionRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'username' => mb_strtolower(
                trim((string) $this->input('username')),
            ),
            'email' => mb_strtolower(
                trim((string) $this->input('email')),
            ),
            'device_name' => trim(
                (string) $this->input(
                    'device_name',
                    'Nozu Android Uygulaması',
                ),
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:80',
            ],

            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('users', 'username'),
            ],

            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email'),
            ],

            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->numbers(),
                'max:1024',
            ],

            'terms_accepted' => [
                'required',
                'accepted',
            ],

            'device_name' => [
                'required',
                'string',
                'min:3',
                'max:80',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' =>
                'Görünen adını yazmalısın.',
            'name.min' =>
                'Görünen ad en az 2 karakter olmalıdır.',
            'name.max' =>
                'Görünen ad en fazla 80 karakter olabilir.',

            'username.required' =>
                'Kullanıcı adını yazmalısın.',
            'username.min' =>
                'Kullanıcı adı en az 3 karakter olmalıdır.',
            'username.max' =>
                'Kullanıcı adı en fazla 30 karakter olabilir.',
            'username.regex' =>
                'Kullanıcı adı yalnızca küçük harf, rakam ve alt çizgi içerebilir.',
            'username.unique' =>
                'Bu kullanıcı adı daha önce alınmış.',

            'email.required' =>
                'E-posta adresini yazmalısın.',
            'email.email' =>
                'Geçerli bir e-posta adresi yazmalısın.',
            'email.unique' =>
                'Bu e-posta adresiyle daha önce hesap oluşturulmuş.',

            'password.required' =>
                'Şifreni yazmalısın.',
            'password.confirmed' =>
                'Şifreler birbiriyle eşleşmiyor.',
            'password.min' =>
                'Şifre en az 8 karakter olmalıdır.',
            'password.letters' =>
                'Şifre en az bir harf içermelidir.',
            'password.numbers' =>
                'Şifre en az bir rakam içermelidir.',

            'terms_accepted.accepted' =>
                'Kullanım şartlarını ve gizlilik politikasını kabul etmelisin.',

            'device_name.required' =>
                'Cihaz adı gönderilmelidir.',
        ];
    }
}
