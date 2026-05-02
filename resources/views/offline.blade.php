<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#10b981">
    <title>{{ config('app.name') }} — Offline</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f9fafb; display: flex; align-items: center; justify-content: center; min-height: 100vh; color: #374151; }
        .container { text-align: center; padding: 2rem; }
        .icon { width: 64px; height: 64px; margin: 0 auto 1.5rem; color: #9ca3af; }
        h1 { font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem; }
        p { font-size: 0.875rem; color: #6b7280; }
        button { margin-top: 1.5rem; padding: 0.5rem 1.5rem; background: #10b981; color: #fff; border: none; border-radius: 0.5rem; font-size: 0.875rem; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
        <h1>You're offline</h1>
        <p>Please check your internet connection and try again.</p>
        <button onclick="location.reload()">Retry</button>
    </div>
</body>
</html>
