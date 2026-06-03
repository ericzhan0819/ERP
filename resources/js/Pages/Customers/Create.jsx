import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：建立表單依後端 can 旗標決定是否顯示個資欄位，實際安全仍由 FormRequest 與 Policy 擋下。
 */
export default function CustomersCreate({ auth, customerStatuses = {}, can = {} }) {
    const canUpdateSensitive = can.update_customer_sensitive === true;
    const { data, setData, post, processing, errors, transform } = useForm({
        name: '',
        phone: '',
        secondary_phone: '',
        email: '',
        line_id: '',
        status: 'lead',
        source: '',
        notes: '',
        id_number: '',
        birthday: '',
        address: '',
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        const payload = {
            name: data.name,
            phone: data.phone,
            secondary_phone: data.secondary_phone,
            email: data.email,
            line_id: data.line_id,
            status: data.status,
            source: data.source,
            notes: data.notes,
            ...(canUpdateSensitive ? {
                id_number: data.id_number,
                birthday: data.birthday,
                address: data.address,
            } : {}),
        };

        // 技術註解：使用 transform 明確送出白名單 payload，避免未授權敏感欄位因表單狀態殘留而被送往後端。
        transform(() => payload);
        post(route('employee-system.customers.store'));
    };

    const inputClass = 'w-full rounded-lg border border-default bg-surface px-3 py-2 text-primary focus:outline-none focus:ring-2 focus:ring-accent';

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-primary">建立客戶</h1>
                        <p className="mt-1 text-sm text-secondary">客戶編號將由系統自動產生</p>
                    </div>
                    <Link href={route('employee-system.customers.index')} className="rounded-lg border border-default px-3 py-2 text-sm font-medium text-secondary">返回列表</Link>
                </div>
                <form onSubmit={handleSubmit} className="space-y-4 rounded-2xl border border-default bg-surface p-4">
                    <p className="rounded-lg border border-default bg-surface-muted px-3 py-2 text-sm text-secondary">客戶編號將在建立後自動產生</p>

                    <section className="grid grid-cols-1 gap-4 rounded-xl border border-default p-4 md:grid-cols-2">
                        <h2 className="text-sm font-semibold text-primary md:col-span-2">基本資料</h2>
                        <Field label="姓名" value={data.name} error={errors.name} inputClass={inputClass} onChange={(value) => setData('name', value)} />
                    </section>

                    <section className="grid grid-cols-1 gap-4 rounded-xl border border-default p-4 md:grid-cols-2">
                        <h2 className="text-sm font-semibold text-primary md:col-span-2">聯絡方式</h2>
                        <Field label="電話" value={data.phone} error={errors.phone} inputClass={inputClass} onChange={(value) => setData('phone', value)} />
                        <Field label="第二電話" value={data.secondary_phone} error={errors.secondary_phone} inputClass={inputClass} onChange={(value) => setData('secondary_phone', value)} />
                        <Field label="Email" type="email" value={data.email} error={errors.email} inputClass={inputClass} onChange={(value) => setData('email', value)} />
                        <Field label="LINE ID" value={data.line_id} error={errors.line_id} inputClass={inputClass} onChange={(value) => setData('line_id', value)} />
                    </section>

                    <section className="grid grid-cols-1 gap-4 rounded-xl border border-default p-4 md:grid-cols-2">
                        <h2 className="text-sm font-semibold text-primary md:col-span-2">狀態與來源</h2>
                        <div>
                            <label className="mb-1 block text-sm text-secondary">狀態</label>
                            <select className={inputClass} value={data.status} onChange={(event) => setData('status', event.target.value)}>
                                {Object.entries(customerStatuses).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </select>
                            {errors.status && <p className="mt-1 text-sm text-accent">{errors.status}</p>}
                        </div>
                        <Field label="來源" value={data.source} error={errors.source} inputClass={inputClass} onChange={(value) => setData('source', value)} />
                    </section>

                    {canUpdateSensitive && (
                        <section className="grid grid-cols-1 gap-4 rounded-xl border border-default p-4 md:grid-cols-2">
                            <h2 className="text-sm font-semibold text-primary md:col-span-2">敏感個資</h2>
                            <Field label="身分證字號" value={data.id_number} error={errors.id_number} inputClass={inputClass} onChange={(value) => setData('id_number', value)} />
                            <Field label="生日" type="date" value={data.birthday} error={errors.birthday} inputClass={inputClass} onChange={(value) => setData('birthday', value)} />
                            <Field label="地址" value={data.address} error={errors.address} inputClass={inputClass} onChange={(value) => setData('address', value)} />
                        </section>
                    )}

                    <section className="rounded-xl border border-default p-4">
                        <h2 className="mb-4 text-sm font-semibold text-primary">備註</h2>
                        <div>
                            <label className="mb-1 block text-sm text-secondary">備註</label>
                            <textarea className={inputClass} rows={4} value={data.notes} onChange={(event) => setData('notes', event.target.value)} />
                            {errors.notes && <p className="mt-1 text-sm text-accent">{errors.notes}</p>}
                        </div>
                    </section>

                    <div className="flex items-center gap-3">
                        <button type="submit" disabled={processing} className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                            {processing ? '儲存中...' : '儲存'}
                        </button>
                    </div>
                </form>
            </div>
        </DashboardLayout>
    );
}

function Field({ label, value, error, inputClass, onChange, type = 'text', readOnly = false }) {
    return (
        <div>
            <label className="mb-1 block text-sm text-secondary">{label}</label>
            <input type={type} readOnly={readOnly} className={`${inputClass} ${readOnly ? 'bg-surface-muted text-secondary' : ''}`} value={value ?? ''} onChange={(event) => onChange(event.target.value)} />
            {error && <p className="mt-1 text-sm text-accent">{error}</p>}
        </div>
    );
}
