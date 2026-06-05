import React from 'react';

/**
 * 技術註解：表單欄位抽出單一元件，避免建立與編輯頁散落重複邏輯，並保持欄位白名單一致。
 */
export default function AccountingAccountForm({ data, setData, errors = {}, accountTypes = {} }) {
    const inputClass = 'w-full rounded-lg border border-default bg-surface px-3 py-2 text-primary focus:outline-none focus:ring-2 focus:ring-accent';

    return (
        <>
            <section className="grid grid-cols-1 gap-4 rounded-xl border border-default p-4 md:grid-cols-2">
                <h2 className="text-sm font-semibold text-primary md:col-span-2">基本資料</h2>
                <Field label="科目編號" value={data.code} error={errors.code} inputClass={inputClass} onChange={(value) => setData('code', value)} />
                <Field label="科目名稱" value={data.name} error={errors.name} inputClass={inputClass} onChange={(value) => setData('name', value)} />
                <div>
                    <label className="mb-1 block text-sm text-secondary">科目類型</label>
                    <select className={inputClass} value={data.type} onChange={(event) => setData('type', event.target.value)}>
                        {Object.entries(accountTypes).map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                        ))}
                    </select>
                    {errors.type && <p className="mt-1 text-sm text-accent">{errors.type}</p>}
                </div>
                <Field label="期初餘額" type="number" value={data.opening_balance} error={errors.opening_balance} inputClass={inputClass} onChange={(value) => setData('opening_balance', value)} />
            </section>

            <section className="grid grid-cols-1 gap-4 rounded-xl border border-default p-4">
                <h2 className="text-sm font-semibold text-primary">其他設定</h2>
                <label className="inline-flex items-center gap-2 text-sm text-secondary">
                    <input type="checkbox" checked={data.is_active} onChange={(event) => setData('is_active', event.target.checked)} />
                    啟用
                </label>
                {errors.is_active && <p className="text-sm text-accent">{errors.is_active}</p>}
                <div>
                    <label className="mb-1 block text-sm text-secondary">備註</label>
                    <textarea className={inputClass} rows={4} value={data.notes} onChange={(event) => setData('notes', event.target.value)} />
                    {errors.notes && <p className="mt-1 text-sm text-accent">{errors.notes}</p>}
                </div>
            </section>
        </>
    );
}

function Field({ label, value, error, inputClass, onChange, type = 'text' }) {
    return (
        <div>
            <label className="mb-1 block text-sm text-secondary">{label}</label>
            <input type={type} className={inputClass} value={value ?? ''} onChange={(event) => onChange(event.target.value)} />
            {error && <p className="mt-1 text-sm text-accent">{error}</p>}
        </div>
    );
}