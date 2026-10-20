<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\RatingSettingsRequest;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Audit;
use App\Services\FinalRating;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Final rating method, section weights and adjectival ranges. Completed IPCRs keep the settings they were rated with. */
class RatingSettingsController extends Controller
{
    public function show(): View
    {
        return view('hr.settings', [
            'settings' => FinalRating::settings(),
            'saved' => Setting::where('key', FinalRating::SETTING)->with('updatedBy')->first(),
            'history' => AuditLog::where('event', 'settings.rating_updated')->with('user')->latest('id')->paginate(10),
        ]);
    }

    public function update(RatingSettingsRequest $request): RedirectResponse
    {
        $before = FinalRating::settings();
        $after = $request->settings();

        if ($before == $after) {
            return back()->with('status', 'Nothing changed.');
        }

        Setting::put(FinalRating::SETTING, $after, $request->user());
        Audit::log(
            'settings.rating_updated',
            $request->user()->name.' changed the rating settings: '.FinalRating::describeMethod($after).' '.FinalRating::describeBands($after['bands']).'.',
            changes: ['before' => $before, 'after' => $after],
        );

        return back()->with('status', 'Rating settings saved. They apply to IPCRs that get final approval from now on; completed IPCRs keep their ratings.');
    }
}
