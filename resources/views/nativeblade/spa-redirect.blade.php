<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting</title>
</head>
<body>
<script>
(function () {
    var path = @json($path);
    window.parent.postMessage({ type: 'nativeblade-navigate', path: path, replace: true }, '*');
})();
</script>
</body>
</html>
