import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：明細頁只呈現後端已淨化 payload 與關聯 allowlist，前端不自行推導權限或顯示任何 mutation 表單。
 */
export default function AccountingEventsShow({ auth, event, sourceTypes = {}, eventTypes = {}, statuses = {}, can = {} }) {
    const title = event.source_number || `AE-${event.id}`;
    const canReview = event.status === 'pending' && Boolean(can.review);
    const canVoid = ['pending', 'reviewed'].includes(event.status) && Boolean(can.void);
    const canConvert = event.status === 'reviewed' && Boolean(can.convert);
    const reviewForm = useForm({
        review_note: event.review_note ?? '',
    });
    const voidForm = useForm({
        void_reason: '',
    });
    const convertForm = useForm({});

    const submitReview = (submitEvent) => {
        submitEvent.preventDefault();
        reviewForm.patch(route('employee-system.accounting.events.review', event.id), {
            preserveScroll: true,
        });
    };

    const submitVoid = (submitEvent) => {
        submitEvent.preventDefault();
        voidForm.patch(route('employee-system.accounting.events.void', event.id), {
            preserveScroll: true,
        });
    };

    const submitConvert = () => {
        if (!window.confirm('確認要依目前映射設定產生草稿傳票？系統不會自動過帳。')) {
            return;
        }

        convertForm.patch(route('employee-system.accounting.events.convert', event.id), {
            preserveScroll: true,
        });
    };

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-5 p-4 md:p-6">
                <section className="rounded-2xl border border-default bg-surface p-5">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">Accounting Event Detail</p>
                            <h1 className="mt-1 font-mono text-2xl font-semibold text-primary">{title}</h1>
                            <p className="mt-2 max-w-3xl text-sm text-secondary">此頁可在 reviewed 狀態下產生草稿傳票；系統只建立 revenue-side draft journal，不會自動過帳，也不會認列 COGS / tax。</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('employee-system.accounting.events.index')} className="rounded-md border border-default px-3 py-2 text-sm font-medium text-secondary transition hover:bg-slate-50 dark:hover:bg-slate-900/40">返回列表</Link>
                            {canConvert && (
                                <button type="button" onClick={submitConvert} disabled={convertForm.processing} className="rounded-md bg-accent px-3 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50">產生傳票草稿</button>
                            )}
                            {event.converted_journal_entry?.id && (
                                <Link href={route('employee-system.accounting.journal-entries.show', event.converted_journal_entry.id)} className="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white transition hover:opacity-90">查看傳票草稿</Link>
                            )}
                        </div>
                    </div>
                    <div className="mt-4 rounded-xl border border-default bg-slate-50 p-3 text-sm text-secondary dark:bg-slate-900/30">
                        {convertHint(event, canConvert)}
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
                    <InfoCard label="覆核時間" value={formatDateTime(event.reviewed_at)} />
                    <InfoCard label="作廢時間" value={formatDateTime(event.voided_at)} />
                    <InfoCard label="已轉傳票" value={event.converted_journal_entry?.journal_number ?? '—'} mono />
                </section>

                <section className="rounded-2xl border border-default bg-surface p-4">
                    <div className="mb-4 border-b border-default pb-3">
                        <h2 className="text-base font-semibold text-primary">轉傳票草稿</h2>
                        <p className="mt-1 text-xs text-secondary">reviewed event 可依 DB-backed AR / Sales Revenue mapping 產生 draft journal；產生後 event 會變成 converted，journal 仍是 draft，需人工檢查後另行過帳。</p>
                        <p className="mt-1 text-xs text-secondary">本階段不會自動建立 COGS / tax / refund / reversal。</p>
                    </div>
                    {event.converted_journal_entry ? (
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
                            <InfoCard label="傳票編號" value={event.converted_journal_entry.journal_number ?? '—'} mono />
                            <InfoCard label="狀態" value={event.converted_journal_entry.status ?? '—'} />
                            <InfoCard label="傳票日期" value={event.converted_journal_entry.entry_date ?? '—'} />
                            <div className="rounded-2xl border border-default bg-slate-50 p-4 dark:bg-slate-900/30">
                                <p className="text-xs uppercase tracking-[0.18em] text-muted">Journal Link</p>
                                <Link href={route('employee-system.accounting.journal-entries.show', event.converted_journal_entry.id)} className="mt-2 inline-flex rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white transition hover:opacity-90">查看傳票草稿</Link>
                            </div>
                        </div>
                    ) : (
                        <div className="rounded-xl border border-default bg-slate-50 p-4 text-sm text-secondary dark:bg-slate-900/30">
                            <p>目前可產生傳票：{canConvert ? '是' : '否'}</p>
                            <p className="mt-2">{convertHint(event, canConvert)}</p>
                        </div>
                    )}
                </section>

                <section className="rounded-2xl border border-default bg-surface p-4">
                    <div className="mb-4 border-b border-default pb-3">
                        <h2 className="text-base font-semibold text-primary">覆核會計事件</h2>
                        <p className="mt-1 text-xs text-secondary">覆核只會把事件標記為 reviewed，不會產生會計傳票、不會過帳、不會認列 revenue / COGS。</p>
                    </div>

                    {canReview ? (
                        <form onSubmit={submitReview} className="space-y-3">
                            <label className="block">
                                <span className="mb-1 block text-xs font-medium text-secondary">Review Note</span>
                                <textarea value={reviewForm.data.review_note} onChange={(inputEvent) => reviewForm.setData('review_note', inputEvent.target.value)} rows={5} maxLength={2000} className="w-full rounded-xl border border-default bg-transparent px-3 py-2 text-sm text-primary focus:outline-none focus:ring-2 focus:ring-accent" />
                                {reviewForm.errors.review_note && <span className="mt-1 block text-xs font-medium text-rose-600">{reviewForm.errors.review_note}</span>}
                            </label>
                            <button type="submit" disabled={reviewForm.processing} className="inline-flex rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50">
                                確認覆核
                            </button>
                        </form>
                    ) : (
                        <div className="rounded-xl border border-default bg-slate-50 p-3 text-sm text-secondary dark:bg-slate-900/30">
                            目前狀態為 {event.status_label ?? statuses[event.status] ?? event.status ?? '—'}，此帳號不可覆核此事件。
                        </div>
                    )}
                </section>

                <section className="rounded-2xl border border-default bg-surface p-4">
                    <div className="mb-4 border-b border-default pb-3">
                        <h2 className="text-base font-semibold text-primary">作廢會計事件</h2>
                        <p className="mt-1 text-xs text-secondary">作廢只會把候選事件標記為 voided，不會刪除事件、不會取消傳票、不會做退款或 reversal。</p>
                    </div>

                    {canVoid ? (
                        <form onSubmit={submitVoid} className="space-y-3">
                            <label className="block">
                                <span className="mb-1 block text-xs font-medium text-secondary">Void Reason</span>
                                <textarea value={voidForm.data.void_reason} onChange={(inputEvent) => voidForm.setData('void_reason', inputEvent.target.value)} rows={5} maxLength={2000} className="w-full rounded-xl border border-default bg-transparent px-3 py-2 text-sm text-primary focus:outline-none focus:ring-2 focus:ring-accent" />
                                {voidForm.errors.void_reason && <span className="mt-1 block text-xs font-medium text-rose-600">{voidForm.errors.void_reason}</span>}
                            </label>
                            <button type="submit" disabled={voidForm.processing} className="inline-flex rounded-md bg-rose-700 px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50">
                                確認作廢
                            </button>
                        </form>
                    ) : (
                        <div className="space-y-2 rounded-xl border border-default bg-slate-50 p-3 text-sm text-secondary dark:bg-slate-900/30">
                            <p>目前狀態為 {event.status_label ?? statuses[event.status] ?? event.status ?? '—'}，此帳號不可作廢此事件。</p>
                            <p>作廢時間：{formatDateTime(event.voided_at)}</p>
                            <p>作廢原因：{event.void_reason || '—'}</p>
                        </div>
                    )}
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
                    Accounting Event 轉傳票目前只會產生 draft journal 與兩條 revenue-side lines；不會自動過帳、不會認列 COGS / tax、不處理 refund / reversal。
                </section>
            </div>
        </DashboardLayout>
    );
}

function convertHint(event, canConvert) {
    if (event.converted_journal_entry?.id || event.status === 'converted') {
        return '此會計事件已產生傳票草稿。';
    }

    if (event.status === 'pending') {
        return '需先覆核，才能產生傳票草稿。';
    }

    if (event.status === 'voided') {
        return '已作廢事件不可產生傳票。';
    }

    if (canConvert) {
        return '可依目前映射設定產生草稿傳票，系統不會自動過帳。';
    }

    return '目前狀態或權限不可產生傳票草稿。';
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
