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
            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-primary">新增會計傳票草稿</h1>
                        <p className="mt-1 text-sm text-secondary">目前僅支援草稿建立與編輯，過帳 / 作廢下一階段開放。</p>
                    </div>
                    <Link href={route('employee-system.accounting.journal-entries.index')} className="rounded-lg border border-default px-3 py-2 text-sm font-medium text-secondary">返回列表</Link>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4 rounded-2xl border border-default bg-surface p-4">
                    <JournalEntryForm data={data} setData={setData} errors={errors} accounts={accounts} />

                    <div className="flex items-center gap-3">
                        <button type="submit" disabled={processing} className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                            {processing ? '儲存中...' : '儲存草稿'}
                        </button>
                    </div>
                </form>
            </div>
        </DashboardLayout>
    );
}