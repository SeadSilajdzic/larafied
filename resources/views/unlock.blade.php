<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('larafied.title', 'Larafied') }} — Unlock</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 2rem;
            width: 100%;
            max-width: 360px;
        }
        .logo {
            font-size: 1.25rem;
            font-weight: 700;
            color: #f8fafc;
            margin-bottom: 0.25rem;
        }
        .subtitle {
            font-size: 0.875rem;
            color: #94a3b8;
            margin-bottom: 1.75rem;
        }
        label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 500;
            color: #cbd5e1;
            margin-bottom: 0.375rem;
        }
        input[type="password"] {
            width: 100%;
            padding: 0.5rem 0.75rem;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 6px;
            color: #f1f5f9;
            font-size: 0.9375rem;
            outline: none;
            transition: border-color 0.15s;
        }
        input[type="password"]:focus { border-color: #6366f1; }
        input[type="password"].error { border-color: #f87171; }
        .error-msg {
            font-size: 0.8125rem;
            color: #f87171;
            margin-top: 0.375rem;
        }
        button {
            margin-top: 1.25rem;
            width: 100%;
            padding: 0.5625rem 1rem;
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 0.9375rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.15s;
        }
        button:hover { background: #4f46e5; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">{{ config('larafied.title', 'Larafied') }}</div>
        <div class="subtitle">Enter the workspace password to continue.</div>

        <form method="POST" action="{{ route('larafied.unlock.store') }}">
            @csrf
            <label for="password">Password</label>
            <input
                id="password"
                type="password"
                name="password"
                autofocus
                autocomplete="current-password"
                class="{{ $errors->has('password') ? 'error' : '' }}"
            >
            @error('password')
                <div class="error-msg">{{ $message }}</div>
            @enderror
            <button type="submit">Unlock</button>
        </form>
    </div>
</body>
</html>
