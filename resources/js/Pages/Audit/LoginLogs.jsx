import React from 'react';
import { Link, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

export default function LoginLogs({ auth, logs = { data: [], links: [] }, filters = {} }) {
    const [search, setSearch] = React.useState(filters.search ?? '');
    const [event, setEvent] = React.useState(filters.event ?? '');
    const [userId, setUserId] = React.useState(filters.user_id ?? '');

    const submit = (e) => {
        e.preventDefault();
        router.get(route('employee-system.audit.login-logs'), {
            search,
            event,
            user_id: userId,
        }, { preserveState: true, replace: true });
    };

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-4 p-6">
                <h1 className="text-xl font-semibold text-primary">Login Logs</h1>

                <form onSubmit={submit} className="rounded-2xl border border-default bg-surface p-4">
                    <div className="grid gap-3 md:grid-cols-4">
                        <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="搜尋 event / identifier" className="rounded-lg border border-default bg-surface px-3 py-2 text-sm" />
                        <input value={event} onChange={(e) => setEvent(e.target.value)} placeholder="event" className="rounded-lg border border-default bg-surface px-3 py-2 text-sm" />
                        <input value={userId} onChange={(e) => setUserId(e.target.value)} placeholder="user_id" className="rounded-lg border border-default bg-surface px-3 py-2 text-sm" />
                        <button type="submit" className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white">查詢</button>
                    </div>
                </form>

                <div className="overflow-x-auto rounded-2xl border border-default bg-surface">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-default text-left text-secondary">
                                <th className="px-4 py-3">時間</th>
                                <th className="px-4 py-3">Event</th>
                                <th className="px-4 py-3">Identifier</th>
                                <th className="px-4 py-3">User</th>
                                <th className="px-4 py-3">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((row) => (
                                <tr key={row.id} className="border-b border-default/70 last:border-b-0">
                                    <td className="px-4 py-3">{row.created_at ?? '-'}</td>
                                    <td className="px-4 py-3">{row.event ?? '-'}</td>
                                    <td className="px-4 py-3">{row.login_identifier ?? '-'}</td>
                                    <td className="px-4 py-3">{row.user?.email ?? '-'}</td>
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

