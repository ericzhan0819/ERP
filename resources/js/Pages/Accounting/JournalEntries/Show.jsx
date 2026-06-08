import React from 'react';
import { Link, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：明細頁僅呈現 header 與分錄 allowlist，不回傳 company_id / branch_id / actor id，避免 tenant 邊界與敏感欄位外洩。
 */
export default function AccountingJournalEntriesShow({ auth, journal, journalStatuses = {}, can = {} }) {
    const statusLabel = journalStatuses[journal.status] ?? journal.status;
    const totalDebit = Number(journal.total_debit || 0);
    const totalCredit = Number(journal.total_credit || 0);
    const difference = Number((totalDebit - totalCredit).toFixed(2));

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
            <div className="space-y-5 p-4 md:p-6">
                <section className="rounded-2xl border border-default bg-surface p-4">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div className="min-w-0">
                            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">Journal Entry Workbench</p>
                            <h1 className="mt-1 font-mono text-2xl font-semibold text-primary">{journal.journal_number}</h1>
                            <p className="mt-1 text-sm text-secondary">唯讀傳票工作台；草稿可編輯或過帳，已過帳僅可作廢，已作廢只能查看。</p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Link href={route('employee-system.accounting.journal-entries.index')} className="rounded-md border border-default px-3 py-2 text-sm font-medium text-secondary transition hover:bg-slate-50 dark:hover:bg-slate-900/40">返回列表</Link>
                            {can.update && journal.status === 'draft' && (
                                <Link href={route('employee-system.accounting.journal-entries.edit', journal.id)} className="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white transition hover:opacity-90">
                                    編輯草稿
                                </Link>
                            )}
                            {can.post && journal.status === 'draft' && (
                                <button type="button" onClick={postJournal} className="rounded-md bg-accent px-3 py-2 text-sm font-semibold text-white transition hover:opacity-90">
                                    過帳
                                </button>
                            )}
                            {can.void && journal.status === 'posted' && (
                                <button type="button" onClick={voidJournal} className="rounded-md border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-950/30">
                                    作廢
                                </button>
                            )}
                        </div>
                    </div>

                    <StatusBar currentStatus={journal.status} />
                </section>

                <section className="rounded-2xl border border-default bg-surface p-4">
                    <div className="mb-4 flex items-center justify-between gap-3 border-b border-default pb-3">
                        <div>
                            <h2 className="text-base font-semibold text-primary">傳票資訊</h2>
                            <p className="mt-1 text-xs text-secondary">目前狀態與稽核時間僅依既有 payload 顯示。</p>
                        </div>
                        <StatusBadge status={journal.status} label={statusLabel} />
                    </div>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <InfoCard label="傳票編號" value={journal.journal_number ?? '—'} mono />
                        <InfoCard label="傳票日期" value={journal.entry_date ?? '—'} />
                        <InfoCard label="狀態" value={statusLabel} />
                        <InfoCard label="建立者" value={journal.operator_name ?? '—'} />
                        <InfoCard label="過帳時間" value={formatDateTime(journal.posted_at)} />
                        <InfoCard label="作廢時間" value={formatDateTime(journal.voided_at)} />
                    </div>
                    <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <p className="mb-1 text-sm text-secondary">摘要</p>
                            <div className="min-h-[46px] rounded-lg border border-default bg-slate-50 px-3 py-3 text-sm text-primary dark:bg-slate-900/30">{journal.summary || '—'}</div>
                        </div>
                        <div>
                            <p className="mb-1 text-sm text-secondary">作廢原因</p>
                            <div className="min-h-[46px] rounded-lg border border-default bg-slate-50 px-3 py-3 text-sm text-primary dark:bg-slate-900/30">{journal.void_reason || '—'}</div>
                        </div>
                    </div>
                </section>

                <section className="space-y-4 rounded-2xl border border-default bg-surface p-4">
                    <div>
                        <h2 className="text-base font-semibold text-primary">分錄明細</h2>
                        <p className="mt-1 text-xs text-secondary">此頁為唯讀檢視，草稿需進入編輯頁才能修改分錄。</p>
                    </div>
                    <div className="overflow-x-auto rounded-xl border border-default">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-100 text-left text-xs uppercase tracking-[0.14em] text-muted dark:bg-slate-900/50">
                                <tr>
                                    <th className="px-4 py-3 font-semibold">科目</th>
                                    <th className="px-4 py-3 text-right font-semibold">借方</th>
                                    <th className="px-4 py-3 text-right font-semibold">貸方</th>
                                    <th className="px-4 py-3 font-semibold">說明</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(journal.lines ?? []).map((line) => (
                                    <tr key={line.id} className="border-t border-default hover:bg-slate-50/70 dark:hover:bg-slate-900/20">
                                        <td className="whitespace-nowrap px-4 py-3 font-medium text-primary">{line.account?.code} - {line.account?.name}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right font-mono">{Number(line.debit || 0).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right font-mono">{Number(line.credit || 0).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                        <td className="px-4 py-3 text-secondary">{line.memo || '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="grid grid-cols-1 gap-3 rounded-xl border border-default bg-slate-50 p-4 md:grid-cols-3 dark:bg-slate-900/30">
                        <TotalCard label="借方合計" value={totalDebit} />
                        <TotalCard label="貸方合計" value={totalCredit} />
                        <div className={`rounded-lg border p-3 ${difference === 0 ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900 dark:bg-emerald-950/20' : 'border-rose-200 bg-rose-50/60 dark:border-rose-900 dark:bg-rose-950/20'}`}>
                            <p className="text-xs uppercase tracking-[0.18em] text-muted">差額</p>
                            <p className={`mt-2 font-mono text-xl font-semibold ${difference === 0 ? 'text-emerald-600' : 'text-rose-600'}`}>{difference.toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
                            <span className={`mt-2 inline-flex rounded-full px-2 py-1 text-xs font-semibold ${difference === 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/30 dark:text-rose-300'}`}>
                                {difference === 0 ? '已平衡' : '未平衡'}
                            </span>
                            {difference !== 0 && <p className="mt-2 text-xs text-rose-700 dark:text-rose-300">借貸不平衡時後端會拒絕儲存 / 過帳。</p>}
                        </div>
                    </div>
                </section>
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

function InfoCard({ label, value, mono = false }) {
    return (
        <div className="rounded-lg border border-default bg-slate-50 p-3 dark:bg-slate-900/30">
            <p className="text-xs uppercase tracking-[0.18em] text-muted">{label}</p>
            <p className={`mt-2 text-base font-semibold text-primary ${mono ? 'font-mono' : ''}`}>{value}</p>
        </div>
    );
}

function StatusBadge({ status, label }) {
    const classes = {
        draft: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300',
        posted: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300',
        voided: 'border-slate-300 bg-slate-100 text-slate-600 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-300',
    };

    return <span className={`rounded-full border px-3 py-1 text-xs font-semibold ${classes[status] ?? 'border-default text-secondary'}`}>{label}</span>;
}

function TotalCard({ label, value }) {
    return (
        <div className="rounded-lg border border-default bg-surface p-3">
            <p className="text-xs uppercase tracking-[0.18em] text-muted">{label}</p>
            <p className="mt-2 font-mono text-xl font-semibold text-primary">{Number(value || 0).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
        </div>
    );
}

function formatDateTime(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('zh-TW', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}
