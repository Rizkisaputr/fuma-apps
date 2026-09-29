<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $credentials = $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->email = Str::lower(trim($this->email));
        $throttleKey = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('email', 'Terlalu banyak percobaan. Coba lagi dalam '.$seconds.' detik.');

            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $credentials['password']], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);
            $this->addError('email', 'Email atau password tidak sesuai.');

            return;
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
