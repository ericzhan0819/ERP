import React from 'react';
import { Link, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：列表頁僅呈現科目表主檔欄位，不輸出 company_id、branch_id、created_by、updated_by，避免 tenant 與 actor 原始資訊外洩。
 */
export default function AccountingAccountsIndex({ auth, accounts, filters = {}, accountTypes = {}, can = {} }) {
    const rows = accounts?.data ?? [];
    const links = accounts?.links ?? [];
    const formatMoney = (value) => {
        const parsed = Number(value ?? 0);
        return Number.isNaN(parsed) ? '—' : parsed.toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
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
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">Accounting</p>
                        <h1 className="mt-1 text-2xl font-semibold text-primary">會計科目</h1>
                        <p className="mt-1 text-sm text-secondary">維護科目表、科目類型與期初餘額</p>
                    </div>
                    {can.create && (
                        <div className="flex flex-wrap gap-2">
                            <Link href={route('employee-system.accounting.journal-entries.index')} className="inline-flex items-center justify-center rounded-lg border border-default px-4 py-2 text-sm font-semibold text-secondary transition hover:opacity-90">
                                傳票管理
                            </Link>
                            <Link href={route('employee-system.accounting.accounts.create')} className="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90">
                                新增科目
                            </Link>
                        </div>
                    )}
                </div>

                <section className="grid grid-cols-1 gap-3 rounded-2xl border border-default bg-surface p-4 md:grid-cols-3">
                    <input
                        value={filters.q ?? ''}
                        onChange={(event) => updateFilter('q', event.target.value)}
                        placeholder="搜尋科目編號 / 名稱"
                        className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm md:col-span-1"
                    />
                    <select
                        value={filters.type ?? ''}
                        onChange={(event) => updateFilter('type', event.target.value)}
                        className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm"
                    >
                        <option value="">全部科目類型</option>
                        {Object.entries(accountTypes).map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                        ))}
                    </select>
                    <select
                        value={filters.is_active ?? ''}
                        onChange={(event) => updateFilter('is_active', event.target.value)}
                        className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm"
                    >
                        <option value="">全部狀態</option>
                        <option value="1">啟用</option>
                        <option value="0">停用</option>
                    </select>
                </section>

                <section className="overflow-x-auto rounded-2xl border border-default bg-surface">
                    {rows.length === 0 ? (
                        <div className="p-8 text-center text-sm text-muted">目前沒有符合條件的會計科目。</div>
                    ) : (
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs text-muted dark:bg-slate-900/40">
                                <tr>
                                    <th className="px-3 py-3 font-medium">科目編號</th>
                                    <th className="px-3 py-3 font-medium">科目名稱</th>
                                    <th className="px-3 py-3 font-medium">科目類型</th>
                                    <th className="px-3 py-3 font-medium">期初餘額</th>
                                    <th className="px-3 py-3 font-medium">狀態</th>
                                    <th className="px-3 py-3 font-medium">操作人員</th>
                                    <th className="px-3 py-3 font-medium">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((account) => (
                                    <tr key={account.id} className="border-t border-default">
                                        <td className="px-3 py-3 font-mono text-xs whitespace-nowrap">{account.code}</td>
                                        <td className="px-3 py-3 whitespace-nowrap">{account.name}</td>
                                        <td className="px-3 py-3 whitespace-nowrap">
                                            <span className="inline-flex rounded-full border border-default px-2 py-1 text-xs text-secondary">
                                                {accountTypes[account.type] ?? account.type}
                                            </span>
                                        </td>
                                        <td className="px-3 py-3 whitespace-nowrap">$ {formatMoney(account.opening_balance)}</td>
                                        <td className="px-3 py-3 whitespace-nowrap">
                                            <span className={`inline-flex rounded-full px-2 py-1 text-xs ${account.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200' : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-200'}`}>
                                                {statusText(account.is_active)}
                                            </span>
                                        </td>
                                        <td className="px-3 py-3 whitespace-nowrap">{account.operator_name ?? '—'}</td>
                                        <td className="px-3 py-3 whitespace-nowrap">
                                            {can.update && (
                                                <Link href={route('employee-system.accounting.accounts.edit', account.id)} className="text-primary underline underline-offset-2">
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
                    <nav className="flex flex-wrap gap-2">
                        {links.map((link, index) => (
                            <Link
                                key={`${link.label}-${index}`}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={`rounded-lg border border-default px-3 py-2 text-sm ${link.active ? 'bg-primary text-white' : 'bg-surface text-secondary'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
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