<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="E-IPCR performance management application for Mariano Marcos State University employees.">
    <title>E-IPCR · MMSU</title>
    <link rel="icon" href="{{ asset('prototype/assets/favicon.svg') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('prototype/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('prototype/css/form.css') }}">
    <link rel="stylesheet" href="{{ asset('prototype/css/analytics.css') }}">
</head>
<body>
<div id="app"></div>
<div id="feedback" role="status" aria-live="polite"></div>
<dialog id="confirm-dialog" aria-labelledby="confirm-title">
    <form method="dialog">
        <h2 id="confirm-title">Confirm action</h2>
        <p id="confirm-copy"></p>
        <div class="actions">
            <button value="cancel" class="btn btn-light">Cancel</button>
            <button value="confirm" class="btn btn-primary" id="confirm-action">Confirm</button>
        </div>
    </form>
</dialog>
<script>
    window.__EIPCR__ = { base: @json(rtrim(url('/'), '/')) };
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.js"></script>
<script src="{{ asset('prototype/js/prototype.js') }}"></script>
</body>
</html>
