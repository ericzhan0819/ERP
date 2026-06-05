import React from 'react';
import { Link, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：明細頁僅呈現 header 與分錄 allowlist，不回傳 company_id / branch_id / actor id，避免 tenant 邊界與敏感欄位外洩。
 */
export default function AccountingJournalEntriesShow({ auth, journal, journalStatuses = {}, can = {} }) {
    const statusLabel = journalStatuses[journal.status] ?? journal.status;

    const postJournal = () => {
        if (!window.confirm('已過帳傳票不可修改。')) {
            return;
        }

        router.patch(route('employee-system.accounting.journal-entries.post', journal.id));
    };

    const voidJournal = () => {
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

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">Journal Draft</p>
                        <h1 className="mt-1 text-2xl font-semibold text-primary">{journal.journal_number}</h1>
                        <p className="mt-1 text-sm text-secondary">已過帳或已作廢傳票不可修改。作廢後不可恢復。</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href={route('employee-system.accounting.journal-entries.index')} className="rounded-lg border border-default px-3 py-2 text-sm font-medium text-secondary">返回列表</Link>
                        {can.update && journal.status === 'draft' && (
                            <Link href={route('employee-system.accounting.journal-entries.edit', journal.id)} className="rounded-lg bg-primary px-3 py-2 text-sm font-medium text-white">
                                編輯草稿
                            </Link>
                        )}
                        {can.post && journal.status === 'draft' && (
                            <button type="button" onClick={postJournal} className="rounded-lg bg-primary px-3 py-2 text-sm font-medium text-white">
                                過帳
                            </button>
                        )}
                        {can.void && journal.status === 'posted' && (
                            <button type="button" onClick={voidJournal} className="rounded-lg border border-default px-3 py-2 text-sm font-medium text-secondary">
                                作廢
                            </button>
                        )}
                    </div>
                </div>

                <section className="grid grid-cols-1 gap-4 rounded-2xl border border-default bg-surface p-4 md:grid-cols-4">
                    <InfoCard label="傳票日期" value={journal.entry_date ?? '—'} />
                    <InfoCard label="狀態" value={statusLabel} />
                    <InfoCard label="借方總額" value={Number(journal.total_debit || 0).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} />
                    <InfoCard label="貸方總額" value={Number(journal.total_credit || 0).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} />
                    <InfoCard label="作廢原因" value={journal.void_reason || '—'} />
                    <div className="md:col-span-4">
                        <p className="mb-1 text-sm text-secondary">摘要</p>
                        <div className="rounded-lg border border-default px-3 py-3 text-sm text-primary">{journal.summary || '—'}</div>
                    </div>
                </section>

                <section className="overflow-x-auto rounded-2xl border border-default bg-surface">
                    <table className="min-w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs text-muted dark:bg-slate-900/40">
                            <tr>
                                <th className="px-3 py-3 font-medium">科目</th>
                                <th className="px-3 py-3 font-medium">借方</th>
                                <th className="px-3 py-3 font-medium">貸方</th>
                                <th className="px-3 py-3 font-medium">摘要</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(journal.lines ?? []).map((line) => (
                                <tr key={line.id} className="border-t border-default">
                                    <td className="px-3 py-3 whitespace-nowrap">{line.account?.code} - {line.account?.name}</td>
                                    <td className="px-3 py-3 whitespace-nowrap">{Number(line.debit || 0).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td className="px-3 py-3 whitespace-nowrap">{Number(line.credit || 0).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                    <td className="px-3 py-3">{line.memo || '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            </div>
        </DashboardLayout>
    );
}

function InfoCard({ label, value }) {
    return (
        <div className="rounded-lg border border-default p-3">
            <p className="text-xs uppercase tracking-[0.18em] text-muted">{label}</p>
            <p className="mt-2 text-base font-semibold text-primary">{value}</p>
        </div>
    );
}