import React from 'react';
import { Link, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：會計事件列表僅呈現後端 allowlist，不讀取 payload JSON，避免只讀工作台外洩候選摘要內的敏感或毛利資訊。
 */
export default function AccountingEventsIndex({ auth, events, filters = {}, sourceTypes = {}, eventTypes = {}, statuses = {}, can = {} }) {
    const rows = events?.data ?? [];
    const links = events?.links ?? [];
    const rowSummary = rows.reduce((summary, event) => ({
        ...summary,
        [event.status]: (summary[event.status] ?? 0) + 1,
    }), { total: rows.length, pending: 0, reviewed: 0, converted: 0, voided: 0 });

    const updateFilter = (key, value) => {
        router.get(route('employee-system.accounting.events.index'), {
            q: filters.q ?? '',
            date_from: filters.date_from ?? '',
            date_to: filters.date_to ?? '',
            source_type: filters.source_type ?? '',
            event_type: filters.event_type ?? '',
            status: filters.status ?? '',
            [key]: value,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-5 p-4 md:p-6">
                <section className="rounded-2xl border border-default bg-surface p-5">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">Accounting Event Workspace</p>
                            <h1 className="mt-1 text-2xl font-semibold text-primary">會計事件</h1>
                            <p className="mt-2 max-w-3xl text-sm text-secondary">這是只讀候選會計事件工作台，目前不提供覆核、轉傳票、作廢或自動入帳。</p>
                        </div>
                        <Link href={route('employee-system.accounting.journal-entries.index')} className="inline-flex items-center justify-center rounded-lg border border-default px-4 py-2 text-sm font-semibold text-secondary transition hover:border-primary hover:text-primary">
                            傳票管理
                        </Link>
                    </div>
                </section>

                <section className="grid grid-cols-1 gap-3 md:grid-cols-5">
                    <SummaryCard label="目前列表" value={rowSummary.total} />
                    <SummaryCard label="待覆核 pending" value={rowSummary.pending} />
                    <SummaryCard label="已覆核 reviewed" value={rowSummary.reviewed} />
                    <SummaryCard label="已轉傳票 converted" value={rowSummary.converted} />
                    <SummaryCard label="已作廢 voided" value={rowSummary.voided} />
                </section>

                <section className="rounded-2xl border border-default bg-surface p-4">
                    <div className="mb-4 border-b border-default pb-3">
                        <h2 className="text-base font-semibold text-primary">事件篩選</h2>
                        <p className="mt-1 text-xs text-secondary">依來源單號、來源類型、事件類型、狀態與日期縮小候選事件清單。</p>
                    </div>
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-3 xl:grid-cols-6">
                        <FilterInput label="關鍵字" value={filters.q ?? ''} placeholder="來源單號 / 類型 / 狀態" onChange={(value) => updateFilter('q', value)} />
                        <FilterInput label="起始日期" type="date" value={filters.date_from ?? ''} onChange={(value) => updateFilter('date_from', value)} />
                        <FilterInput label="結束日期" type="date" value={filters.date_to ?? ''} onChange={(value) => updateFilter('date_to', value)} />
                        <FilterSelect label="來源類型" value={filters.source_type ?? ''} options={sourceTypes} emptyLabel="全部來源" onChange={(value) => updateFilter('source_type', value)} />
                        <FilterSelect label="事件類型" value={filters.event_type ?? ''} options={eventTypes} emptyLabel="全部事件" onChange={(value) => updateFilter('event_type', value)} />
                        <FilterSelect label="狀態" value={filters.status ?? ''} options={statuses} emptyLabel="全部狀態" onChange={(value) => updateFilter('status', value)} />
                    </div>
                </section>

                <section className="overflow-x-auto rounded-2xl border border-default bg-surface">
                    {rows.length === 0 ? (
                        <div className="p-10 text-center text-sm font-medium text-muted">目前沒有符合條件的會計事件。</div>
                    ) : (
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs text-muted dark:bg-slate-900/40">
                                <tr>
                                    <th className="px-3 py-3 font-medium">事件日期</th>
                                    <th className="px-3 py-3 font-medium">來源單號</th>
                                    <th className="px-3 py-3 font-medium">來源類型</th>
                                    <th className="px-3 py-3 font-medium">事件類型</th>
                                    <th className="px-3 py-3 text-right font-medium">金額</th>
                                    <th className="px-3 py-3 font-medium">狀態</th>
                                    <th className="px-3 py-3 font-medium">建立者</th>
                                    <th className="px-3 py-3 font-medium">已轉傳票</th>
                                    <th className="px-3 py-3 font-medium">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((event) => (
                                    <tr key={event.id} className="border-t border-default hover:bg-slate-50/70 dark:hover:bg-slate-900/20">
                                        <td className="whitespace-nowrap px-3 py-3">{event.event_date ?? '—'}</td>
                                        <td className="whitespace-nowrap px-3 py-3 font-mono text-xs font-semibold text-primary">{event.source_number ?? `AE-${event.id}`}</td>
                                        <td className="whitespace-nowrap px-3 py-3">{event.source_type_label ?? event.source_type}</td>
                                        <td className="whitespace-nowrap px-3 py-3">{event.event_type_label ?? event.event_type}</td>
                                        <td className="whitespace-nowrap px-3 py-3 text-right font-mono">{formatMoney(event.amount, event.currency)}</td>
                                        <td className="whitespace-nowrap px-3 py-3"><StatusBadge status={event.status} label={event.status_label ?? statuses[event.status] ?? event.status} /></td>
                                        <td className="whitespace-nowrap px-3 py-3">{event.created_by_name ?? '—'}</td>
                                        <td className="whitespace-nowrap px-3 py-3 font-mono text-xs">{event.converted_journal_entry?.journal_number ?? '—'}</td>
                                        <td className="whitespace-nowrap px-3 py-3">
                                            {can.view && <Link href={route('employee-system.accounting.events.show', event.id)} className="text-primary underline underline-offset-2">查看</Link>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>

                {links.length > 0 && (
                    <nav className="flex flex-wrap gap-2">
                        {links.map((link, index) => (
                            <Link key={`${link.label}-${index}`} href={link.url ?? '#'} preserveScroll className={`rounded-lg border border-default px-3 py-2 text-sm ${link.active ? 'bg-primary text-white' : 'bg-surface text-secondary'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}>
                                {link.label.replace('&laquo;', '‹').replace('&raquo;', '›')}
                            </Link>
                        ))}
                    </nav>
                )}
            </div>
        </DashboardLayout>
    );
}

function SummaryCard({ label, value }) {
    return (
        <div className="rounded-2xl border border-default bg-surface p-4">
            <p className="text-xs uppercase tracking-[0.18em] text-muted">{label}</p>
            <p className="mt-2 font-mono text-2xl font-semibold text-primary">{Number(value || 0).toLocaleString('zh-TW')}</p>
        </div>
    );
}

function FilterInput({ label, value, onChange, placeholder = '', type = 'text' }) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs font-medium text-secondary">{label}</span>
            <input type={type} value={value} placeholder={placeholder} onChange={(event) => onChange(event.target.value)} className="w-full rounded-lg border border-default bg-transparent px-3 py-2 text-sm text-primary focus:outline-none focus:ring-2 focus:ring-accent" />
        </label>
    );
}

function FilterSelect({ label, value, options, emptyLabel, onChange }) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs font-medium text-secondary">{label}</span>
            <select value={value} onChange={(event) => onChange(event.target.value)} className="w-full rounded-lg border border-default bg-transparent px-3 py-2 text-sm text-primary focus:outline-none focus:ring-2 focus:ring-accent">
                <option value="">{emptyLabel}</option>
                {Object.entries(options).map(([key, labelText]) => <option key={key} value={key}>{labelText}</option>)}
            </select>
        </label>
    );
}

function StatusBadge({ status, label }) {
    const classes = {
        pending: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300',
        reviewed: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300',
        converted: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300',
        voided: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300',
    };

    return <span className={`inline-flex rounded-full border px-2 py-1 text-xs font-semibold ${classes[status] ?? 'border-slate-300 bg-slate-100 text-slate-600 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-300'}`}>{label}</span>;
}

function formatMoney(value, currency) {
    const amount = Number(value || 0).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    return `${currency || 'TWD'} ${amount}`;
}
