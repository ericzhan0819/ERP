import React from 'react';
import { Link } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：明細頁只呈現後端已淨化 payload 與關聯 allowlist，前端不自行推導權限或顯示任何 mutation 表單。
 */
export default function AccountingEventsShow({ auth, event, sourceTypes = {}, eventTypes = {}, statuses = {}, can = {} }) {
    const title = event.source_number || `AE-${event.id}`;

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-5 p-4 md:p-6">
                <section className="rounded-2xl border border-default bg-surface p-5">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">Accounting Event Detail</p>
                            <h1 className="mt-1 font-mono text-2xl font-semibold text-primary">{title}</h1>
                            <p className="mt-2 max-w-3xl text-sm text-secondary">此頁只讀；不會覆核、轉傳票、作廢或產生分錄。</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('employee-system.accounting.events.index')} className="rounded-md border border-default px-3 py-2 text-sm font-medium text-secondary transition hover:bg-slate-50 dark:hover:bg-slate-900/40">返回列表</Link>
                            {event.converted_journal_entry?.id && (
                                <Link href={route('employee-system.accounting.journal-entries.show', event.converted_journal_entry.id)} className="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white transition hover:opacity-90">查看傳票草稿</Link>
                            )}
                        </div>
                    </div>
                    <StatusBar currentStatus={event.status} statuses={statuses} />
                </section>

                <section className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <InfoCard label="來源類型" value={event.source_type_label ?? sourceTypes[event.source_type] ?? event.source_type ?? '—'} />
                    <InfoCard label="事件類型" value={event.event_type_label ?? eventTypes[event.event_type] ?? event.event_type ?? '—'} />
                    <InfoCard label="事件日期" value={event.event_date ?? '—'} />
                    <InfoCard label="狀態" value={event.status_label ?? statuses[event.status] ?? event.status ?? '—'} />
                    <InfoCard label="幣別" value={event.currency ?? '—'} />
                    <InfoCard label="金額" value={formatMoney(event.amount, event.currency)} mono />
                    <InfoCard label="建立者" value={event.creator?.name ?? '—'} />
                    <InfoCard label="覆核者" value={event.reviewer?.name ?? '—'} />
                    <InfoCard label="作廢時間" value={formatDateTime(event.voided_at)} />
                    <InfoCard label="已轉傳票" value={event.converted_journal_entry?.journal_number ?? '—'} mono />
                </section>

                <section className="rounded-2xl border border-default bg-surface p-4">
                    <div className="mb-4 border-b border-default pb-3">
                        <h2 className="text-base font-semibold text-primary">Payload</h2>
                        <p className="mt-1 text-xs text-secondary">payload 是後端控制的非正式會計候選摘要，不代表已過帳。</p>
                    </div>
                    <PayloadViewer payload={event.payload} />
                </section>

                <section className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <TextPanel label="Review Note" value={event.review_note} />
                    <TextPanel label="Void Reason" value={event.void_reason} />
                </section>

                <section className="rounded-2xl border border-dashed border-default bg-slate-50 p-4 text-sm text-secondary dark:bg-slate-900/30">
                    Accounting Event 目前不會自動產生 Journal Draft，也不會自動認列 revenue 或 COGS。
                </section>
            </div>
        </DashboardLayout>
    );
}

function StatusBar({ currentStatus, statuses }) {
    const order = ['pending', 'reviewed', 'converted', 'voided'];

    return (
        <div className="mt-5 grid grid-cols-1 overflow-hidden rounded-xl border border-default md:grid-cols-4">
            {order.map((status) => {
                const active = status === currentStatus;

                return (
                    <div key={status} className={`px-4 py-3 text-sm font-semibold ${active ? 'bg-primary text-white' : 'bg-slate-50 text-muted dark:bg-slate-900/30'}`}>
                        {statuses[status] ?? status}
                    </div>
                );
            })}
        </div>
    );
}

function InfoCard({ label, value, mono = false }) {
    return (
        <div className="rounded-2xl border border-default bg-surface p-4">
            <p className="text-xs uppercase tracking-[0.18em] text-muted">{label}</p>
            <p className={`mt-2 text-base font-semibold text-primary ${mono ? 'font-mono' : ''}`}>{value}</p>
        </div>
    );
}

function PayloadViewer({ payload }) {
    if (!payload || (typeof payload === 'object' && Object.keys(payload).length === 0)) {
        return <div className="rounded-xl border border-default bg-slate-50 p-4 text-sm text-muted dark:bg-slate-900/30">—</div>;
    }

    if (typeof payload !== 'object') {
        return <pre className="overflow-x-auto rounded-xl border border-default bg-slate-950 p-4 text-xs text-slate-100">{String(payload)}</pre>;
    }

    return (
        <div className="overflow-x-auto rounded-xl border border-default">
            <table className="min-w-full text-sm">
                <thead className="bg-slate-50 text-left text-xs uppercase tracking-[0.14em] text-muted dark:bg-slate-900/40">
                    <tr>
                        <th className="px-4 py-3 font-semibold">Key</th>
                        <th className="px-4 py-3 font-semibold">Value</th>
                    </tr>
                </thead>
                <tbody>
                    {Object.entries(payload).map(([key, value]) => (
                        <tr key={key} className="border-t border-default">
                            <td className="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-primary">{key}</td>
                            <td className="px-4 py-3 text-secondary"><PayloadValue value={value} /></td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function PayloadValue({ value }) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'object') {
        return <pre className="max-w-3xl overflow-x-auto rounded-lg bg-slate-950 p-3 text-xs text-slate-100">{JSON.stringify(value, null, 2)}</pre>;
    }

    return String(value);
}

function TextPanel({ label, value }) {
    return (
        <section className="rounded-2xl border border-default bg-surface p-4">
            <h2 className="text-base font-semibold text-primary">{label}</h2>
            <div className="mt-3 min-h-[72px] rounded-xl border border-default bg-slate-50 p-3 text-sm text-secondary dark:bg-slate-900/30">{value || '—'}</div>
        </section>
    );
}

function formatMoney(value, currency) {
    const amount = Number(value || 0).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    return `${currency || 'TWD'} ${amount}`;
}

function formatDateTime(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('zh-TW', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}
