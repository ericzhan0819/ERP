import React from 'react';
import { Link, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：列表頁只呈現草稿傳票必要欄位，不輸出 tenant/actor 原始欄位與非本階段獲利 payload。
 */
export default function AccountingJournalEntriesIndex({ auth, journals, filters = {}, journalStatuses = {}, can = {} }) {
    const rows = journals?.data ?? [];
    const links = journals?.links ?? [];

    const postJournal = (journal) => {
        if (!window.confirm('已過帳傳票不可修改。')) {
            return;
        }

        router.patch(route('employee-system.accounting.journal-entries.post', journal.id));
    };

    const voidJournal = (journal) => {
        const voidReason = window.prompt('作廢後不可恢復。\n請輸入作廢原因：');

        if (voidReason === null) {
            return;
        }

        if (!voidReason.trim()) {
            window.alert('作廢原因為必填。');
            return;
        }

        router.patch(route('employee-system.accounting.journal-entries.void', journal.id), {
            void_reason: voidReason.trim(),
        });
    };

    const updateFilter = (key, value) => {
        router.get(route('employee-system.accounting.journal-entries.index'), {
            q: filters.q ?? '',
            date_from: filters.date_from ?? '',
            date_to: filters.date_to ?? '',
            status: filters.status ?? '',
            [key]: value,
        }, {
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
                        <h1 className="mt-1 text-2xl font-semibold text-primary">傳票管理</h1>
                        <p className="mt-1 text-sm text-secondary">建立、過帳與作廢會計傳票，借貸必平衡。已過帳或已作廢傳票不可修改。</p>
                    </div>
                    {can.create && (
                        <Link href={route('employee-system.accounting.journal-entries.create')} className="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90">
                            新增傳票草稿
                        </Link>
                    )}
                </div>

                <section className="grid grid-cols-1 gap-3 rounded-2xl border border-default bg-surface p-4 md:grid-cols-4">
                    <input value={filters.q ?? ''} onChange={(event) => updateFilter('q', event.target.value)} placeholder="搜尋傳票編號 / 摘要" className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm md:col-span-1" />
                    <input type="date" value={filters.date_from ?? ''} onChange={(event) => updateFilter('date_from', event.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm" />
                    <input type="date" value={filters.date_to ?? ''} onChange={(event) => updateFilter('date_to', event.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm" />
                    <select value={filters.status ?? ''} onChange={(event) => updateFilter('status', event.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm">
                        <option value="">全部狀態</option>
                        {Object.entries(journalStatuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                </section>

                <section className="overflow-x-auto rounded-2xl border border-default bg-surface">
                    {rows.length === 0 ? (
                        <div className="p-8 text-center text-sm text-muted">目前沒有符合條件的會計傳票。</div>
                    ) : (
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs text-muted dark:bg-slate-900/40">
                                <tr>
                                    <th className="px-3 py-3 font-medium">傳票編號</th>
                                    <th className="px-3 py-3 font-medium">日期</th>
                                    <th className="px-3 py-3 font-medium">摘要</th>
                                    <th className="px-3 py-3 font-medium">借方總額</th>
                                    <th className="px-3 py-3 font-medium">貸方總額</th>
                                    <th className="px-3 py-3 font-medium">狀態</th>
                                    <th className="px-3 py-3 font-medium">操作人員</th>
                                    <th className="px-3 py-3 font-medium">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((journal) => (
                                    <tr key={journal.id} className="border-t border-default">
                                        <td className="px-3 py-3 font-mono text-xs whitespace-nowrap">{journal.journal_number}</td>
                                        <td className="px-3 py-3 whitespace-nowrap">{journal.entry_date}</td>
                                        <td className="px-3 py-3">{journal.summary || '—'}</td>
                                        <td className="px-3 py-3 whitespace-nowrap">$ {Number(journal.total_debit || 0).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td className="px-3 py-3 whitespace-nowrap">$ {Number(journal.total_credit || 0).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td className="px-3 py-3 whitespace-nowrap">
                                            <span className="inline-flex rounded-full border border-default px-2 py-1 text-xs text-secondary">{journalStatuses[journal.status] ?? journal.status}</span>
                                        </td>
                                        <td className="px-3 py-3 whitespace-nowrap">{journal.operator_name ?? '—'}</td>
                                        <td className="px-3 py-3 whitespace-nowrap">
                                            <div className="flex items-center gap-3">
                                                <Link href={route('employee-system.accounting.journal-entries.show', journal.id)} className="text-primary underline underline-offset-2">查看</Link>
                                                {can.update && journal.status === 'draft' && (
                                                    <Link href={route('employee-system.accounting.journal-entries.edit', journal.id)} className="text-primary underline underline-offset-2">編輯</Link>
                                                )}
                                                {can.post && journal.status === 'draft' && (
                                                    <button type="button" onClick={() => postJournal(journal)} className="text-primary underline underline-offset-2">過帳</button>
                                                )}
                                                {can.void && journal.status === 'posted' && (
                                                    <button type="button" onClick={() => voidJournal(journal)} className="text-primary underline underline-offset-2">作廢</button>
                                                )}
                                            </div>
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
                            <Link key={`${link.label}-${index}`} href={link.url ?? '#'} preserveScroll className={`rounded-lg border border-default px-3 py-2 text-sm ${link.active ? 'bg-primary text-white' : 'bg-surface text-secondary'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}>
                                {link.label.replace('&laquo;', '‹').replace('&raquo;', '›')}
                            </Link>
                        ))}
                    </nav>
                )}
            </div>
        </DashboardLayout>
    );
}