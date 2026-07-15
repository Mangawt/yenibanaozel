<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateAdminUser extends Command
{
    protected $signature = 'nozu:create-admin';

    protected $description = 'Ilk Super Admin kullanicisini guvenli sekilde olusturur.';

    public function handle(): int
    {
        $name = $this->ask('Ad soyad');
        $email = $this->ask('E-posta');
        $username = $this->ask('Kullanici adi', Str::slug((string) $name) ?: 'admin');
        $password = $this->secret('Parola');

        $validator = Validator::make(compact('name', 'email', 'username', 'password'), [
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:40', 'unique:users,username'],
            'password' => ['required', Password::min(10)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'username' => $username,
            'password' => Hash::make($password),
            'role' => 'super_admin',
            'theme' => 'system',
        ]);

        $this->info('Super Admin olusturuldu.');

        return self::SUCCESS;
    }
}
