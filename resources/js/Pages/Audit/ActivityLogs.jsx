import React from 'react';
import { Link, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { formatDateTime } from '@/utils/formatDateTime';

export default function ActivityLogs({ auth, logs = { data: [], links: [] }, filters = {} }) {
    const [search, setSearch] = React.useState(filters.search ?? '');
    const [event, setEvent] = React.useState(filters.event ?? '');
    const [userId, setUserId] = React.useState(filters.user_id ?? '');

    const submit = (e) => {
        e.preventDefault();
        router.get(route('employee-system.audit.activity-logs'), {
            search,
            event,
            user_id: userId,
        }, { preserveState: true, replace: true });
    };

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-4 p-6">
                <h1 className="text-xl font-semibold text-primary">操作稽核紀錄</h1>

                <div className="flex items-center gap-2">
                    <Link
                        href={route('employee-system.audit.activity-logs')}
                        className="rounded-lg border border-accent bg-accent px-3 py-1.5 text-sm font-medium text-white"
                    >
                        操作稽核紀錄
                    </Link>
                    <Link
                        href={route('employee-system.audit.login-logs')}
                        className="rounded-lg border border-default px-3 py-1.5 text-sm font-medium text-secondary"
                    >
                        登入紀錄
                    </Link>
                </div>

                <form onSubmit={submit} className="rounded-2xl border border-default bg-surface p-4">
                    <div className="grid gap-3 md:grid-cols-4">
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="搜尋 action / event / description" className="rounded-lg border border-default bg-surface px-3 py-2 text-sm" />
                        <input value={event} onChange={(e) => setEvent(e.target.value)} placeholder="事件" className="rounded-lg border border-default bg-surface px-3 py-2 text-sm" />
                        <input value={userId} onChange={(e) => setUserId(e.target.value)} placeholder="user_id" className="rounded-lg border border-default bg-surface px-3 py-2 text-sm" />
                        <button type="submit" className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white">查詢</button>
                    </div>
                </form>

                <div className="overflow-x-auto rounded-2xl border border-default bg-surface">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-default text-left text-secondary">
                                <th className="px-4 py-3">時間</th>
                                <th className="px-4 py-3">事件</th>
                                <th className="px-4 py-3">模組</th>
                                <th className="px-4 py-3">使用者</th>
                                <th className="px-4 py-3">詳細資料</th>
                                <th className="px-4 py-3">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((row) => (
                                <tr key={row.id} className="border-b border-default/70 last:border-b-0">
                                    <td className="px-4 py-3">{formatDateTime(row.created_at)}</td>
                                    <td className="px-4 py-3" title={row.display?.event_key ?? row.event ?? row.action ?? '-'}>
                                        <div className="font-medium text-primary">{row.display?.event_label ?? row.event ?? row.action ?? '-'}</div>
                                        <div className="text-xs text-secondary">{row.display?.event_key ?? row.event ?? row.action ?? '-'}</div>
                                    </td>
                                    <td className="px-4 py-3">{row.display?.module_label ?? '-'}</td>
                                    <td className="px-4 py-3">{row.user?.email ?? '-'}</td>
                                    <td className="px-4 py-3">{row.display?.description_label ?? row.description ?? '-'}</td>
                                    <td className="px-4 py-3">{row.ip_address ?? '-'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {logs.links?.map((link, idx) => (
                        <Link key={`${link.url ?? 'null'}-${idx}`} href={link.url ?? '#'} className={`rounded-md border px-3 py-1.5 text-sm ${link.active ? 'border-accent text-accent' : 'border-default text-secondary'} ${link.url === null ? 'pointer-events-none opacity-50' : ''}`}>
                            {link.label.replace('&laquo; Previous', '上一頁').replace('Next &raquo;', '下一頁')}
                        </Link>
                    ))}
                </div>
            </div>
        </DashboardLayout>
    );
}
