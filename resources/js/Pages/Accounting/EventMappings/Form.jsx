import React from 'react';

/**
 * 技術註解：表單只送出後端允許欄位；source_type、company_id 與 actor 欄位由後端產生以避免越權覆寫。
 */
export default function AccountingEventMappingForm({ data, setData, errors = {}, supportedEventTypes = {}, mappingKeyOptions = {}, accountOptions = [], branchOptions = [] }) {
    const inputClass = 'w-full rounded-lg border border-default bg-surface px-3 py-2 text-sm text-primary focus:outline-none focus:ring-2 focus:ring-accent';
    const branchScope = data.branch_id === null || data.branch_id === '' ? 'company_default' : 'current_branch';
    const setBranchScope = (scope) => {
        const selected = branchOptions.find((option) => option.key === scope);
        setData('branch_id', selected?.value ?? null);
    };

    return (
        <div className="space-y-4">
            <section className="grid grid-cols-1 gap-4 rounded-xl border border-default bg-slate-50/60 p-4 md:grid-cols-2 dark:bg-slate-900/20">
                <div className="md:col-span-2">
                    <h2 className="text-sm font-semibold text-primary">會計事件映射設定</h2>
                    <p className="mt-1 text-xs text-muted">目前只設定車輛交易完成事件的應收帳款與銷貨收入科目。</p>
                </div>
                <Select label="事件類型" value={data.event_type} error={errors.event_type} inputClass={inputClass} onChange={(value) => setData('event_type', value)}>
                    {Object.entries(supportedEventTypes).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </Select>
                <Select label="映射鍵" value={data.mapping_key} error={errors.mapping_key} inputClass={inputClass} onChange={(value) => setData('mapping_key', value)}>
                    {Object.entries(mappingKeyOptions).map(([value, option]) => <option key={value} value={value}>{option.label}</option>)}
                </Select>
                <Select label="科目" value={data.account_id} error={errors.account_id} inputClass={inputClass} onChange={(value) => setData('account_id', value)}>
                    <option value="">請選擇科目</option>
                    {accountOptions.map((account) => <option key={account.id} value={account.id}>{account.code} - {account.name}（{account.type_label}）</option>)}
                </Select>
                <Select label="層級" value={branchScope} error={errors.branch_id} inputClass={inputClass} onChange={setBranchScope}>
                    {branchOptions.map((option) => <option key={option.key} value={option.key}>{option.label}</option>)}
                </Select>
                <div className="md:col-span-2">
                    <label className="inline-flex items-center gap-3 rounded-lg border border-default bg-surface px-3 py-2 text-sm text-secondary">
                        <input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} />
                        <span>
                            <span className="block font-medium text-primary">啟用狀態</span>
                            <span className="block text-xs text-muted">同一公司 / 分店 / 事件 / 映射鍵只能有一筆啟用映射。</span>
                        </span>
                    </label>
                    {errors.is_active && <p className="mt-1 text-sm text-accent">{errors.is_active}</p>}
                </div>
                <div className="md:col-span-2">
                    <label className="mb-1 block text-sm font-medium text-secondary">備註</label>
                    <textarea className={inputClass} rows={4} value={data.notes ?? ''} onChange={(event) => setData('notes', event.target.value)} />
                    {errors.notes && <p className="mt-1 text-sm text-accent">{errors.notes}</p>}
                </div>
            </section>

            <section className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
                <h2 className="text-sm font-semibold">映射邊界提醒</h2>
                <p className="mt-2 text-sm leading-6">此設定只影響 Accounting Event preflight 與未來 draft generation 的科目解析，不會自動過帳，不會立即認列 revenue / COGS，也不會建立傳票或分錄。</p>
            </section>
        </div>
    );
}

function Select({ label, value, error, inputClass, onChange, children }) {
    return <div><label className="mb-1 block text-sm font-medium text-secondary">{label}</label><select className={inputClass} value={value ?? ''} onChange={(event) => onChange(event.target.value)}>{children}</select>{error && <p className="mt-1 text-sm text-accent">{error}</p>}</div>;
}
