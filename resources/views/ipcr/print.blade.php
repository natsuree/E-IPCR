<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IPCR · {{ $ipcr->user->name }} · {{ $ipcr->period->name }}</title>
    @include('layouts.partials.head')
</head>
<body style="background:#fff">
<div class="print-sheet">
    <div class="no-print d-flex gap-2 justify-content-end mb-3">
        <a href="{{ $back }}" class="btn btn-outline-secondary btn-sm">Back</a>
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>

    @include('ipcr.partials.document', ['seal' => is_file(public_path('images/mmsu-seal.png')) ? asset('images/mmsu-seal.png') : null])
</div>
</body>
</html>
