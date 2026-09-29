<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <meta name='theme-color' content='#123c2c'>
    <title>Masuk · {{ $appSettings->app_name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class='guest-body'>
    {{ $slot }}

    @livewireScripts
</body>
</html>
