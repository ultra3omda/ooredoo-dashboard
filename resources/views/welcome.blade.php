<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ooredoo Dashboard Test</title>
</head>
<body>
    <h1>API Dashboard Data Test</h1>
    <pre id="api-response"></pre>
    <script src="{{ asset(\'js/app.js\') }}"></script>
</body>
</html>

