<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportUsersRequest;
use App\Models\User;
use App\Services\Accounts;
use App\Services\Audit;
use App\Services\UserImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Upload a CSV, review every row and its errors, then import the valid rows. */
class UserImportController extends Controller
{
    private const SESSION_KEY = 'user_import';

    public function create(): View
    {
        return view('admin.users.import', ['preview' => null]);
    }

    public function preview(ImportUsersRequest $request, UserImporter $importer): View
    {
        $preview = $importer->preview($request->file('file')->getRealPath());
        $request->session()->put(self::SESSION_KEY, collect($preview['rows'])->pluck('data')->filter()->values()->all());

        return view('admin.users.import', ['preview' => $preview]);
    }

    public function store(Request $request, Accounts $accounts, UserImporter $importer): RedirectResponse
    {
        $rows = $request->session()->pull(self::SESSION_KEY, []);
        if (! $rows) {
            return redirect()->route('admin.users.import')->withErrors(['file' => 'There is nothing to import. Upload the CSV file again.']);
        }

        $created = DB::transaction(function () use ($rows, $accounts, $request) {
            $created = 0;
            foreach ($rows as $data) {
                // Skip anything created since the preview (for example by another Administrator).
                if (User::withTrashed()->where('email', $data['email'])->exists()
                    || ($data['employee_id'] && User::withTrashed()->where('employee_id', $data['employee_id'])->exists())) {
                    continue;
                }
                $accounts->create($data, $request->user());
                $created++;
            }

            return $created;
        });

        Audit::log('user.imported', "{$request->user()->name} imported {$created} account(s) from a CSV file.");

        return redirect()->route('admin.users.index')
            ->with('status', "{$created} account(s) were imported. Each person was emailed a link to set their password.");
    }

    public function sample(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, UserImporter::COLUMNS);
            fputcsv($out, ['Juan Dela Cruz', 'juan.delacruz@mmsu.edu.ph', '2026-0001', 'Teaching Employee', 'Teaching', 'College of Engineering', 'Computer Engineering']);
            fputcsv($out, ['Maria Santos', 'maria.santos@mmsu.edu.ph', '2026-0002', 'Non-Teaching Employee', 'Non-teaching', 'Records Office', '']);
            fclose($out);
        }, 'eipcr-users-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
