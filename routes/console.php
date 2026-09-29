<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:create', function () {
    $name = trim((string) $this->ask('Nama admin'));
    $email = Str::lower(trim((string) $this->ask('Email admin')));
    $password = (string) $this->secret('Password admin');
    $passwordConfirmation = (string) $this->secret('Ulangi password admin');

    $validator = Validator::make([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $passwordConfirmation,
    ], [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email:rfc', 'max:255', Rule::unique(User::class, 'email')],
        'password' => [
            'required',
            'confirmed',
            Password::min(12)->mixedCase()->numbers()->symbols(),
        ],
    ], [
        'password.confirmed' => 'Konfirmasi password tidak sama.',
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
        'password' => $password,
    ]);

    $this->info('Akun admin berhasil dibuat.');

    return self::SUCCESS;
})->purpose('Buat akun admin FUMA secara interaktif');
