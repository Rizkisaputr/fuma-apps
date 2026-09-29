<main class='login-shell'>
    <section class='login-brand-panel' aria-label='{{ $appSettings->app_name }} Apps'>
        <div class='login-brand-content'>
            <div class='brand-lockup brand-lockup--light'>
                <img class='brand-logo' src='{{ $appSettings->logoUrl() }}' alt='Logo {{ $appSettings->app_name }}'>
                <span><strong>{{ $appSettings->app_name }}</strong></span>
            </div>

            <div class='login-brand-copy'>
                <p class='eyebrow eyebrow--gold'>AREA ADMIN</p>
                <h1>Kelola sesi main, pemain, dan kas FUMA dalam satu tempat.</h1>
            </div>
        </div>

        <p class='login-brand-foot'>{{ $appSettings->app_name }} Apps</p>
    </section>

    <section class='login-form-panel'>
        <div class='login-mobile-brand'>
            <div class='brand-lockup'>
                <img class='brand-logo' src='{{ $appSettings->logoUrl() }}' alt='Logo {{ $appSettings->app_name }}'>
                <span><strong>{{ $appSettings->app_name }}</strong></span>
            </div>
        </div>

        <div class='login-card'>
            <p class='eyebrow'>MASUK KE AKUN</p>
            <h2>Selamat datang</h2>
            <p class='form-intro'>Gunakan akun admin {{ $appSettings->app_name }} untuk melanjutkan.</p>

            <form wire:submit='login' class='login-form'>
                <div class='field-group'>
                    <label for='email'>Email</label>
                    <input
                        wire:model='email'
                        id='email'
                        name='email'
                        type='email'
                        autocomplete='username'
                        placeholder='nama@contoh.com'
                        autofocus
                    >
                    @error('email') <p class='field-error'>{{ $message }}</p> @enderror
                </div>

                <div class='field-group'>
                    <label for='password'>Password</label>
                    <input
                        wire:model='password'
                        id='password'
                        name='password'
                        type='password'
                        autocomplete='current-password'
                        placeholder='Masukkan password'
                    >
                    @error('password') <p class='field-error'>{{ $message }}</p> @enderror
                </div>

                <label class='check-row'>
                    <input wire:model='remember' type='checkbox'>
                    <span>Ingat saya di perangkat ini</span>
                </label>

                <button class='primary-button' type='submit' wire:loading.attr='disabled'>
                    <span wire:loading.remove wire:target='login'>Masuk</span>
                    <span wire:loading wire:target='login'>Memeriksa akun&hellip;</span>
                </button>
            </form>

        </div>
    </section>
</main>
