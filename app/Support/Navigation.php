<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;

/** Builds each role's sidebar. Every item points at a route in that role's own group. */
class Navigation
{
    /**
     * @return list<array{label: string, url: string, icon: string, active: bool, badge?: int, indent?: bool}>
     */
    public static function for(User $user): array
    {
        $p = $user->routePrefix();
        $unread = $user->unreadNotifications()->count();

        $items = match ($user->role) {
            Role::Teaching, Role::NonTeaching => [
                ['Dashboard', 'employee.dashboard', 'speedometer2', ['employee.dashboard']],
                ['My IPCRs', 'employee.ipcrs.index', 'folder2-open', ['employee.ipcrs.index', 'employee.ipcrs.show', 'employee.ipcrs.print']],
                ['Create / Edit IPCR', 'employee.ipcrs.current', 'pencil-square', ['employee.ipcrs.current', 'employee.ipcrs.edit', 'employee.ipcrs.confirm']],
                ['Strategic Functions', 'employee.ipcrs.current', 'flag', [], '#strategic', true],
                ['Core Functions', 'employee.ipcrs.current', 'bullseye', [], '#core', true],
                ['Support Functions', 'employee.ipcrs.current', 'life-preserver', [], '#support', true],
                ['Status Tracking', 'employee.tracking', 'signpost-split', ['employee.tracking']],
            ],
            Role::DepartmentHead => [
                ['Dashboard', 'depthead.dashboard', 'speedometer2', ['depthead.dashboard']],
                ['Review Queue', 'depthead.queue', 'inbox', ['depthead.queue', 'depthead.review']],
                ['IPCR Records', 'depthead.records', 'archive', ['depthead.records', 'depthead.records.*']],
                ['Analytics', 'depthead.analytics', 'bar-chart-line', ['depthead.analytics', 'depthead.ipcrs.show']],
            ],
            Role::Dean => [
                ['Dashboard', 'dean.dashboard', 'speedometer2', ['dean.dashboard']],
                ['Review Queue', 'dean.queue', 'inbox', ['dean.queue', 'dean.review']],
                ['IPCR Records', 'dean.records', 'archive', ['dean.records', 'dean.records.*']],
                ['Analytics', 'dean.analytics', 'bar-chart-line', ['dean.analytics', 'dean.ipcrs.show']],
            ],
            Role::Hr => [
                ['Dashboard', 'hr.dashboard', 'speedometer2', ['hr.dashboard']],
                ['Review Queue', 'hr.queue', 'inbox', ['hr.queue', 'hr.review']],
                ['IPCR Records', 'hr.records', 'archive', ['hr.records', 'hr.records.*']],
                ['IPCR Templates', 'hr.templates', 'file-earmark-ruled', ['hr.templates']],
                ['Rating Periods', 'hr.periods.index', 'calendar3', ['hr.periods.*']],
                ['Reports & Analytics', 'hr.reports', 'bar-chart-line', ['hr.reports']],
                ['Rating Settings', 'hr.settings', 'sliders', ['hr.settings']],
            ],
            Role::Admin => [
                ['Dashboard', 'admin.dashboard', 'speedometer2', ['admin.dashboard']],
                ['Users', 'admin.users.index', 'people', ['admin.users.*']],
                ['Colleges & Offices', 'admin.units.index', 'building', ['admin.units.*']],
                ['Audit Log', 'admin.audit', 'journal-text', ['admin.audit']],
            ],
        };

        if ($user->role !== Role::Admin) {
            $items[] = ['Notifications', $p.'.notifications', 'bell', [$p.'.notifications'], null, false, $unread];
        }
        $items[] = ['Profile', $p.'.profile', 'person-circle', [$p.'.profile']];

        return array_map(fn (array $i) => [
            'label' => $i[0],
            'url' => route($i[1]).($i[4] ?? ''),
            'icon' => $i[2],
            'active' => $i[3] !== [] && request()->routeIs(...$i[3]),
            'indent' => $i[5] ?? false,
            'badge' => $i[6] ?? 0,
        ], $items);
    }

    /** Breadcrumb page name for the header. */
    public static function pageName(): string
    {
        return trim(view()->yieldContent('title', 'Dashboard'));
    }
}
