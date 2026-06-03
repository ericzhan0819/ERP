import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：編輯頁將 customer_number 固定唯讀，且個資欄位需同時具備 sensitive.view/update 才作為可編輯輸入。
 */
export default function CustomersEdit({ auth, customer, customerStatuses = {}, can = {} }) {
    const canUpdateSensitive = can.update_customer_sensitive === true;
    const canViewSensitive = can.view_customer_sensitive === true;
    const { data, setData, patch, processing, errors, transform } = useForm({
        name: customer.name ?? '',
        phone: customer.phone ?? '',
        secondary_phone: customer.secondary_phone ?? '',
        email: customer.email ?? '',
        line_id: customer.line_id ?? '',
        status: customer.status ?? 'lead',
        source: customer.source ?? '',
        notes: customer.notes ?? '',
        id_number: customer.id_number ?? '',
        birthday: customer.birthday ?? '',
        address: customer.address ?? '',
    });

    const handleSubmit = (event) => {
        event.preventDefault();
        const canSubmitSensitive = canViewSensitive && canUpdateSensitive;
        const payload = {
            name: data.name,
            phone: data.phone,
            secondary_phone: data.secondary_phone,
            email: data.email,
            line_id: data.line_id,
            status: data.status,
            source: data.source,
            notes: data.notes,
            ...(canSubmitSensitive ? {
                id_number: data.id_number,
                birthday: data.birthday,
                address: data.address,
            } : {}),
        };

        // 技術註解：敏感個資必須同時具備檢視與更新權限才可進入 payload，降低越權更新與資料覆寫風險。
        transform(() => payload);
        patch(route('employee-system.customers.update', customer.id));
    };

    const inputClass = 'w-full rounded-lg border border-default bg-surface px-3 py-2 text-primary focus:outline-none focus:ring-2 focus:ring-accent';

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-4 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold text-primary">編輯客戶</h1>
                        <p className="mt-1 text-sm text-secondary">{customer.customer_number}</p>
                    </div>
                    <Link href={route('employee-system.customers.show', customer.id)} className="rounded-lg border border-default px-3 py-2 text-sm font-medium text-secondary">返回詳情</Link>
                </div>
                <form onSubmit={handleSubmit} className="space-y-4 rounded-2xl border border-default bg-surface p-4">
                    <section className="grid grid-cols-1 gap-4 rounded-xl border border-default p-4 md:grid-cols-2">
                        <h2 className="text-sm font-semibold text-primary md:col-span-2">基本資料</h2>
                        <Field label="客戶編號" value={customer.customer_number} inputClass={inputClass} readOnly onChange={() => {}} />
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

                    {canViewSensitive && canUpdateSensitive && (
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
