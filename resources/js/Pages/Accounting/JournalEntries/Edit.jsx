import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import JournalEntryForm from './Form';

/**
 * 技術註解：編輯頁沿用建立頁表單，僅允許 draft 內容更新，不暴露 tenant 與 actor 欄位避免敏感資料外洩。
 */
export default function AccountingJournalEntriesEdit({ auth, journal, accounts = [] }) {
    const { data, setData, patch, processing, errors } = useForm({
        entry_date: journal.entry_date ?? '',
        summary: journal.summary ?? '',
        lines: (journal.lines ?? []).map((line, index) => ({
            account_id: line.account_id ?? '',
            debit: line.debit ?? '0',
            credit: line.credit ?? '0',
            memo: line.memo ?? '',
            sort_order: line.sort_order ?? index,
        })),
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        patch(route('employee-system.accounting.journal-entries.update', journal.id));
    };

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-5 p-4 md:p-6">
                <form onSubmit={handleSubmit} className="space-y-5">
                    <section className="rounded-2xl border border-default bg-surface p-4">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div className="min-w-0">
                                <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">Accounting Journal</p>
                                <h1 className="mt-1 text-2xl font-semibold text-primary">編輯會計傳票草稿</h1>
                                <p className="mt-1 font-mono text-sm text-secondary">{journal.journal_number}</p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <Link href={route('employee-system.accounting.journal-entries.show', journal.id)} className="rounded-md border border-default px-3 py-2 text-sm font-medium text-secondary transition hover:bg-slate-50 dark:hover:bg-slate-900/40">返回明細</Link>
                                <button type="submit" disabled={processing} className="rounded-md bg-accent px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50">
                                    {processing ? '儲存中...' : '儲存修改'}
                                </button>
                            </div>
                        </div>

                        <StatusBar currentStatus={journal.status ?? 'draft'} />
                    </section>

                    <JournalEntryForm data={data} setData={setData} errors={errors} accounts={accounts} />
                </form>
            </div>
        </DashboardLayout>
    );
}

function StatusBar({ currentStatus }) {
    const statuses = [
        ['draft', '草稿'],
        ['posted', '已過帳'],
        ['voided', '已作廢'],
    ];

    return (
        <div className="mt-5 grid grid-cols-1 overflow-hidden rounded-xl border border-default md:grid-cols-3">
            {statuses.map(([value, label]) => {
                const active = value === currentStatus;

                return (
                    <div key={value} className={`px-4 py-3 text-sm font-semibold ${active ? 'bg-primary text-white' : 'bg-slate-50 text-muted dark:bg-slate-900/30'}`}>
                        {label}
                    </div>
                );
            })}
        </div>
    );
}
