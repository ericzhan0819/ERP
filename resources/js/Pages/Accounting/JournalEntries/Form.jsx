import React from 'react';

/**
 * 技術註解：傳票表單集中於單一元件，避免建立與編輯頁分錄邏輯散落，並確保借貸差額提示一致。
 */
export default function JournalEntryForm({ data, setData, errors = {}, accounts = [] }) {
    const inputClass = 'w-full rounded-lg border border-default bg-surface px-3 py-2 text-primary focus:outline-none focus:ring-2 focus:ring-accent';
    const lines = Array.isArray(data.lines) ? data.lines : [];
    const totalDebit = lines.reduce((sum, line) => sum + Number(line.debit || 0), 0);
    const totalCredit = lines.reduce((sum, line) => sum + Number(line.credit || 0), 0);
    const difference = Number((totalDebit - totalCredit).toFixed(2));

    const updateLine = (index, field, value) => {
        const nextLines = [...lines];
        const nextLine = { ...nextLines[index], [field]: value };

        // 技術註解：前端只作 UX 輔助清空對向欄位；最終借貸互斥規則仍必須由後端再驗證。
        if (field === 'debit' && Number(value || 0) > 0) {
            nextLine.credit = '0';
        }

        if (field === 'credit' && Number(value || 0) > 0) {
            nextLine.debit = '0';
        }

        nextLines[index] = nextLine;
        setData('lines', nextLines);
    };

    const addLine = () => {
        setData('lines', [
            ...lines,
            { account_id: '', debit: '0', credit: '0', memo: '', sort_order: lines.length },
        ]);
    };

    const removeLine = (index) => {
        setData('lines', lines.filter((_, currentIndex) => currentIndex !== index).map((line, sortOrder) => ({ ...line, sort_order: sortOrder })));
    };

    return (
        <div className="space-y-4">
            <section className="grid grid-cols-1 gap-4 rounded-xl border border-default p-4 md:grid-cols-2">
                <div>
                    <label className="mb-1 block text-sm text-secondary">傳票日期</label>
                    <input type="date" className={inputClass} value={data.entry_date ?? ''} onChange={(event) => setData('entry_date', event.target.value)} />
                    {errors.entry_date && <p className="mt-1 text-sm text-accent">{errors.entry_date}</p>}
                </div>
                <div className="md:col-span-2">
                    <label className="mb-1 block text-sm text-secondary">摘要</label>
                    <input type="text" className={inputClass} value={data.summary ?? ''} onChange={(event) => setData('summary', event.target.value)} placeholder="請輸入傳票摘要" />
                    {errors.summary && <p className="mt-1 text-sm text-accent">{errors.summary}</p>}
                </div>
            </section>

            <section className="space-y-4 rounded-xl border border-default p-4">
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-sm font-semibold text-primary">分錄明細</h2>
                    <button type="button" onClick={addLine} className="rounded-lg border border-default px-3 py-2 text-sm font-medium text-secondary">
                        新增分錄列
                    </button>
                </div>

                <div className="overflow-x-auto rounded-xl border border-default">
                    <table className="min-w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs text-muted dark:bg-slate-900/40">
                            <tr>
                                <th className="px-3 py-3 font-medium">科目</th>
                                <th className="px-3 py-3 font-medium">借方</th>
                                <th className="px-3 py-3 font-medium">貸方</th>
                                <th className="px-3 py-3 font-medium">摘要</th>
                                <th className="px-3 py-3 font-medium">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            {lines.map((line, index) => (
                                <tr key={`journal-line-${index}`} className="border-t border-default align-top">
                                    <td className="px-3 py-3 min-w-[220px]">
                                        <select className={inputClass} value={line.account_id ?? ''} onChange={(event) => updateLine(index, 'account_id', event.target.value)}>
                                            <option value="">請選擇科目</option>
                                            {accounts.map((account) => (
                                                <option key={account.id} value={account.id}>{account.code} - {account.name}</option>
                                            ))}
                                        </select>
                                        {errors[`lines.${index}.account_id`] && <p className="mt-1 text-sm text-accent">{errors[`lines.${index}.account_id`]}</p>}
                                    </td>
                                    <td className="px-3 py-3 min-w-[140px]">
                                        <input type="number" step="0.01" min="0" className={inputClass} value={line.debit ?? '0'} onChange={(event) => updateLine(index, 'debit', event.target.value)} />
                                        {errors[`lines.${index}.debit`] && <p className="mt-1 text-sm text-accent">{errors[`lines.${index}.debit`]}</p>}
                                    </td>
                                    <td className="px-3 py-3 min-w-[140px]">
                                        <input type="number" step="0.01" min="0" className={inputClass} value={line.credit ?? '0'} onChange={(event) => updateLine(index, 'credit', event.target.value)} />
                                        {errors[`lines.${index}.credit`] && <p className="mt-1 text-sm text-accent">{errors[`lines.${index}.credit`]}</p>}
                                    </td>
                                    <td className="px-3 py-3 min-w-[220px]">
                                        <input type="text" className={inputClass} value={line.memo ?? ''} onChange={(event) => updateLine(index, 'memo', event.target.value)} placeholder="分錄摘要" />
                                        {errors[`lines.${index}.memo`] && <p className="mt-1 text-sm text-accent">{errors[`lines.${index}.memo`]}</p>}
                                    </td>
                                    <td className="px-3 py-3">
                                        <button type="button" onClick={() => removeLine(index)} className="rounded-lg border border-default px-3 py-2 text-sm font-medium text-secondary disabled:opacity-50" disabled={lines.length <= 2}>
                                            移除
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {errors.lines && <p className="text-sm text-accent">{errors.lines}</p>}

                <div className="grid grid-cols-1 gap-3 rounded-xl border border-default bg-slate-50 p-4 md:grid-cols-3 dark:bg-slate-900/30">
                    <SummaryCard label="借方總額" value={totalDebit} />
                    <SummaryCard label="貸方總額" value={totalCredit} />
                    <div className="rounded-lg border border-default bg-surface p-3">
                        <p className="text-xs uppercase tracking-[0.18em] text-muted">Difference</p>
                        <p className={`mt-2 text-xl font-semibold ${difference === 0 ? 'text-emerald-600' : 'text-rose-600'}`}>{difference.toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
                        <p className="mt-2 text-xs text-secondary">前端僅提供差額提示，最終借貸平衡仍由後端驗證。</p>
                    </div>
                </div>
            </section>
        </div>
    );
}

function SummaryCard({ label, value }) {
    return (
        <div className="rounded-lg border border-default bg-surface p-3">
            <p className="text-xs uppercase tracking-[0.18em] text-muted">{label}</p>
            <p className="mt-2 text-xl font-semibold text-primary">{Number(value || 0).toLocaleString('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p>
        </div>
    );
}