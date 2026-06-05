import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import AccountingAccountForm from './Form';

/**
 * 技術註解：建立頁只送出科目業務欄位，tenant 與 actor 欄位由後端依登入者決定，避免 privilege escalation。
 */
export default function AccountingAccountsCreate({ auth, accountTypes = {} }) {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        name: '',
        type: Object.keys(accountTypes)[0] ?? 'asset',
        opening_balance: '0',
        is_active: true,
        notes: '',
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        post(route('employee-system.accounting.accounts.store'));
    };

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3 rounded-2xl border border-default bg-surface p-5">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">會計科目設定</p>
                        <h1 className="mt-2 text-2xl font-semibold text-primary">新增科目</h1>
                        <p className="mt-2 text-sm text-secondary">建立公司層級科目表主檔，供會計傳票分錄選用。</p>
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
