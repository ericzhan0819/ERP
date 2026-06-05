import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import AccountingAccountForm from './Form';

/**
 * 技術註解：編輯頁沿用建立頁白名單，只允許更新 code、name、type、opening_balance、is_active、notes，不暴露 tenant/actor 欄位。
 */
export default function AccountingAccountsEdit({ auth, account, accountTypes = {} }) {
    const { data, setData, patch, processing, errors } = useForm({
        code: account.code ?? '',
        name: account.name ?? '',
        type: account.type ?? (Object.keys(accountTypes)[0] ?? 'asset'),
        opening_balance: account.opening_balance ?? '0',
        is_active: account.is_active ?? true,
        notes: account.notes ?? '',
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        patch(route('employee-system.accounting.accounts.update', account.id));
    };

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-primary">編輯會計科目</h1>
                        <p className="mt-1 text-sm text-secondary">維護科目表、科目類型與期初餘額</p>
                    </div>
                    <Link href={route('employee-system.accounting.accounts.index')} className="rounded-lg border border-default px-3 py-2 text-sm font-medium text-secondary">返回列表</Link>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4 rounded-2xl border border-default bg-surface p-4">
                    <AccountingAccountForm data={data} setData={setData} errors={errors} accountTypes={accountTypes} />

                    <div className="flex items-center gap-3">
                        <button type="submit" disabled={processing} className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                            {processing ? '儲存中...' : '儲存'}
                        </button>
                    </div>
                </form>
            </div>
        </DashboardLayout>
    );
}