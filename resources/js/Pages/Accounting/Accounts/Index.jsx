import React from 'react';
import { Link, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：列表頁僅呈現科目表主檔欄位，不輸出 company_id、branch_id、created_by、updated_by，避免 tenant 與 actor 原始資訊外洩。
 */
export default function AccountingAccountsIndex({ auth, accounts, filters = {}, accountTypes = {}, can = {} }) {
    const rows = accounts?.data ?? [];
    const links = accounts?.links ?? [];
    const statusText = (value) => (value ? '啟用' : '停用');
    const paginationLabel = (label) => label.replace('&laquo;', '‹').replace('&raquo;', '›');
    const updateFilter = (key, value) => {
        const nextFilters = {
            q: filters.q ?? '',
            type: filters.type ?? '',
            is_active: filters.is_active ?? '',
            [key]: value,
        };

        router.get(route('employee-system.accounting.accounts.index'), nextFilters, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-5 p-4 md:p-6">
                <div className="flex flex-col gap-4 rounded-2xl border border-default bg-surface p-5 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">Accounting</p>
                        <h1 className="mt-2 text-2xl font-semibold text-primary">會計科目</h1>
                        <p className="mt-2 max-w-2xl text-sm text-secondary">維護公司層級科目表、科目類型與啟用狀態。</p>
                    </div>
                    <div className="flex flex-wrap gap-2 md:justify-end">
                        <Link href={route('employee-system.accounting.journal-entries.index')} className="inline-flex items-center justify-center rounded-lg border border-default bg-transparent px-4 py-2 text-sm font-semibold text-secondary transition hover:border-primary hover:text-primary">
                            傳票管理
                        </Link>
                        {can.create && (
                            <Link href={route('employee-system.accounting.accounts.create')} className="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90">
                                新增科目
                            </Link>
                        )}
                    </div>
                </div>

                <section className="rounded-2xl border border-default bg-surface p-4">
                    <div className="mb-4 flex flex-col gap-1">
                        <h2 className="text-sm font-semibold text-primary">科目篩選</h2>
                        <p className="text-xs text-muted">依科目編號、名稱、類型與啟用狀態縮小目前科目表範圍。</p>
                    </div>
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <label className="space-y-1">
                            <span className="text-xs font-medium text-secondary">搜尋</span>
                            <input
                                value={filters.q ?? ''}
                                onChange={(event) => updateFilter('q', event.target.value)}
                                placeholder="搜尋科目編號、名稱"
                                className="w-full rounded-lg border border-default bg-transparent px-3 py-2 text-sm text-primary focus:outline-none focus:ring-2 focus:ring-accent"
                            />
                        </label>
                        <label className="space-y-1">
                            <span className="text-xs font-medium text-secondary">科目類型</span>
                            <select
                                value={filters.type ?? ''}
                                onChange={(event) => updateFilter('type', event.target.value)}
                                className="w-full rounded-lg border border-default bg-transparent px-3 py-2 text-sm text-primary focus:outline-none focus:ring-2 focus:ring-accent"
                            >
                                <option value="">全部科目類型</option>
                                {Object.entries(accountTypes).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </select>
                        </label>
                        <label className="space-y-1">
                            <span className="text-xs font-medium text-secondary">啟用狀態</span>
                            <select
                                value={filters.is_active ?? ''}
                                onChange={(event) => updateFilter('is_active', event.target.value)}
                                className="w-full rounded-lg border border-default bg-transparent px-3 py-2 text-sm text-primary focus:outline-none focus:ring-2 focus:ring-accent"
                            >
                                <option value="">全部狀態</option>
                                <option value="1">啟用</option>
                                <option value="0">停用</option>
                            </select>
                        </label>
                    </div>
                </section>

                <section className="overflow-x-auto rounded-2xl border border-default bg-surface">
                    {rows.length === 0 ? (
                        <div className="p-10 text-center">
                            <p className="text-sm font-medium text-primary">目前沒有符合條件的會計科目。</p>
                            {can.create && <p className="mt-2 text-sm text-muted">可新增科目，或執行預設會計科目 Seeder。</p>}
                        </div>
                    ) : (
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-[11px] uppercase tracking-[0.12em] text-muted dark:bg-slate-900/40">
                                <tr>
                                    <th className="px-4 py-3 font-semibold">科目編號</th>
                                    <th className="px-4 py-3 font-semibold">科目名稱</th>
                                    <th className="px-4 py-3 font-semibold">科目類型</th>
                                    <th className="px-4 py-3 font-semibold">狀態</th>
                                    <th className="px-4 py-3 font-semibold">操作人員</th>
                                    <th className="px-4 py-3 text-right font-semibold">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((account) => (
                                    <tr key={account.id} className="border-t border-default transition hover:bg-slate-50/70 dark:hover:bg-slate-900/30">
                                        <td className="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold tracking-[0.08em] text-primary">{account.code}</td>
                                        <td className="whitespace-nowrap px-4 py-3 font-medium text-primary">{account.name}</td>
                                        <td className="whitespace-nowrap px-4 py-3">
                                            <span className="inline-flex rounded-full border border-default bg-slate-50 px-2.5 py-1 text-xs font-medium text-secondary dark:bg-slate-900/40">
                                                {accountTypes[account.type] ?? account.type}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3">
                                            <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${account.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300'}`}>
                                                {statusText(account.is_active)}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-secondary">{account.operator_name ?? '—'}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            {can.update && (
                                                <Link href={route('employee-system.accounting.accounts.edit', account.id)} className="inline-flex rounded-lg border border-default px-3 py-1.5 text-xs font-semibold text-primary transition hover:border-primary">
                                                    編輯
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>

                {links.length > 0 && (
                    <nav className="flex flex-wrap gap-2 rounded-2xl border border-default bg-surface p-3">
                        {links.map((link, index) => (
                            <Link
                                key={`${link.label}-${index}`}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={`rounded-lg border border-default px-3 py-2 text-sm font-medium ${link.active ? 'bg-primary text-white' : 'bg-surface text-secondary hover:border-primary hover:text-primary'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                            >
                                {paginationLabel(link.label)}
                            </Link>
                        ))}
                    </nav>
                )}
            </div>
        </DashboardLayout>
    );
}
