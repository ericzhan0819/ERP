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
            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-primary">編輯會計傳票草稿</h1>
                        <p className="mt-1 text-sm text-secondary">傳票編號：{journal.journal_number}</p>
                    </div>
                    <Link href={route('employee-system.accounting.journal-entries.show', journal.id)} className="rounded-lg border border-default px-3 py-2 text-sm font-medium text-secondary">返回明細</Link>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4 rounded-2xl border border-default bg-surface p-4">
                    <JournalEntryForm data={data} setData={setData} errors={errors} accounts={accounts} />

                    <div className="flex items-center gap-3">
                        <button type="submit" disabled={processing} className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                            {processing ? '儲存中...' : '儲存修改'}
                        </button>
                    </div>
                </form>
            </div>
        </DashboardLayout>
    );
}