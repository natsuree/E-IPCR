<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>IPCR · {{ $ipcr->user->name }} · {{ $ipcr->period->name }}</title>
    <style>
        @page { margin: 0.4in; }
        body { font-family: "DejaVu Sans", sans-serif; color: #28362B; font-size: 9pt; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
@include('ipcr.partials.document', ['seal' => $seal])
</body>
</html>
