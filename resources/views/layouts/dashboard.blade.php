<!-- resources/views/ai-dashboard/index.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Dashboard - Laravel Anthropic</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @viteReactRefresh
    @vite(['resources/js/app.js'])
    <script>
        window.config = {
            baseUrl: '{{ url('/') }}',
            apiUrl: '{{ url('/api') }}',
            wsHost: '{{ config('broadcasting.connections.pusher.options.host') }}',
            wsPort: {{ config('broadcasting.connections.pusher.options.port') }},
            wsKey: '{{ config('broadcasting.connections.pusher.key') }}',
        };
    </script>
</head>
<body class="bg-gray-100">
    <div id="app"></div>
</body>
</html>
