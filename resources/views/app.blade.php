<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $config['title'] ?? 'API Workspace' }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/larafied/app.css') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('vendor/larafied/favicon.svg') }}">
</head>
<body class="bg-gray-950 text-gray-100 antialiased">
    <div
        id="app"
        data-config="{{ json_encode($config) }}"
    ></div>
    <script src="{{ asset('vendor/larafied/app.js') }}"></script>
</body>
</html>
