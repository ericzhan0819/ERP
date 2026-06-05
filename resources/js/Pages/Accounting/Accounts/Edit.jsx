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
            <div className="space-y-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3 rounded-2xl border border-default bg-surface p-5">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">會計科目設定</p>
                        <div className="mt-2 flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-semibold text-primary">編輯科目</h1>
                            <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${account.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300'}`}>
                                {account.is_active ? '啟用' : '停用'}
                            </span>
                        </div>
                        <p className="mt-2 text-sm text-secondary">維護公司層級科目表主檔、科目類型、啟用狀態與期初餘額。</p>
                    </div>
                    <Link href={route('employee-system.accounting.accounts.index')} className="rounded-lg border border-default px-4 py-2 text-sm font-semibold text-secondary transition hover:border-primary hover:text-primary">返回列表</Link>
                </div>

                <form onSubmit={handleSubmit} className="space-y-5 rounded-2xl border border-default bg-surface p-4 md:p-5">
                    <AccountingAccountForm data={data} setData={setData} errors={errors} accountTypes={accountTypes} />

                    <div className="flex items-center justify-end gap-3 border-t border-default pt-4">
                        <Link href={route('employee-system.accounting.accounts.index')} className="rounded-lg border border-default px-4 py-2 text-sm font-semibold text-secondary transition hover:border-primary hover:text-primary">返回</Link>
                        <button type="submit" disabled={processing} className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50">
                            {processing ? '儲存中...' : '儲存'}
                        </button>
                    </div>
                </form>
            </div>
        </DashboardLayout>
    );
}
