<?php

namespace App\Services;

use App\Models\Ipcr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** The IPCR as a PDF in the MMSU layout (13 × 8.5 inch landscape). */
class IpcrDocument
{
    public function download(Ipcr $ipcr): Response
    {
        $ipcr->loadMissing(['user.college', 'user.department', 'period', 'template', 'items', 'reviews.user']);

        $seal = public_path('images/mmsu-seal.png');

        return Pdf::loadView('ipcr.pdf', [
            'ipcr' => $ipcr,
            'seal' => is_file($seal) ? $seal : null,
        ])
            ->setPaper([0, 0, 936, 612])
            ->download($this->filename($ipcr));
    }

    public function filename(Ipcr $ipcr): string
    {
        return 'IPCR-'.Str::slug($ipcr->user->name).'-'.Str::slug($ipcr->period->name).'.pdf';
    }
}
