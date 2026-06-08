import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import JournalEntryForm from './Form';

/**
 * 技術註解：建立頁只送出傳票 header 與 lines 白名單，不接受前端傳入 tenant / actor / status，以避免權限提升。
 */
export default function AccountingJournalEntriesCreate({ auth, accounts = [], defaults = {} }) {
    const { data, setData, post, processing, errors } = useForm({
        entry_date: defaults.entry_date ?? '',
        summary: '',
        lines: [
            { account_id: '', debit: '0', credit: '0', memo: '', sort_order: 0 },
            { account_id: '', debit: '0', credit: '0', memo: '', sort_order: 1 },
        ],
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        post(route('employee-system.accounting.journal-entries.store'));
    };

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-5 p-4 md:p-6">
                <form onSubmit={handleSubmit} className="space-y-5">
                    <section className="rounded-2xl border border-default bg-surface p-4">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div className="min-w-0">
                                <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">Journal Entry Workbench</p>
                                <h1 className="mt-1 text-2xl font-semibold text-primary">新增傳票草稿</h1>
                                <p className="mt-1 text-sm text-secondary">先建立可編輯草稿；傳票編號會在儲存後由後端產生。</p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <Link href={route('employee-system.accounting.journal-entries.index')} className="rounded-md border border-default px-3 py-2 text-sm font-medium text-secondary transition hover:bg-slate-50 dark:hover:bg-slate-900/40">返回列表</Link>
                                <button type="submit" disabled={processing} className="rounded-md bg-accent px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50">
                                    {processing ? '儲存中...' : '儲存草稿'}
                                </button>
                            </div>
                        </div>

                        <StatusBar currentStatus="draft" />
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
