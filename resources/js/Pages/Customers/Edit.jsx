import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：編輯頁將 customer_number 固定唯讀，且個資欄位需同時具備 sensitive.view/update 才作為可編輯輸入。
 */
export default function CustomersEdit({ auth, customer, customerStatuses = {}, can = {} }) {
    const canUpdateSensitive = can.update_customer_sensitive === true;
    const canViewSensitive = can.view_customer_sensitive === true;
    const { data, setData, patch, processing, errors } = useForm({
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

        patch(route('employee-system.customers.update', customer.id), { data: payload });
    };

    const inputClass = 'w-full rounded-lg border border-default bg-surface px-3 py-2 text-primary focus:outline-none focus:ring-2 focus:ring-accent';

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-4 p-6">
                <h1 className="text-xl font-semibold text-primary">編輯客戶</h1>
                <form onSubmit={handleSubmit} className="space-y-4 rounded-2xl border border-default bg-surface p-4">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <Field label="客戶編號" value={customer.customer_number} inputClass={inputClass} readOnly onChange={() => {}} />
                        <Field label="姓名" value={data.name} error={errors.name} inputClass={inputClass} onChange={(value) => setData('name', value)} />
                        <Field label="電話" value={data.phone} error={errors.phone} inputClass={inputClass} onChange={(value) => setData('phone', value)} />
                        <Field label="第二電話" value={data.secondary_phone} error={errors.secondary_phone} inputClass={inputClass} onChange={(value) => setData('secondary_phone', value)} />
                        <Field label="Email" type="email" value={data.email} error={errors.email} inputClass={inputClass} onChange={(value) => setData('email', value)} />
                        <Field label="LINE ID" value={data.line_id} error={errors.line_id} inputClass={inputClass} onChange={(value) => setData('line_id', value)} />
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
                    </div>

                    {canViewSensitive && canUpdateSensitive && (
                        <section className="grid grid-cols-1 gap-4 rounded-xl border border-default p-4 md:grid-cols-2">
                            <h2 className="md:col-span-2 text-sm font-semibold text-primary">敏感個資</h2>
                            <Field label="身分證字號" value={data.id_number} error={errors.id_number} inputClass={inputClass} onChange={(value) => setData('id_number', value)} />
                            <Field label="生日" type="date" value={data.birthday} error={errors.birthday} inputClass={inputClass} onChange={(value) => setData('birthday', value)} />
                            <Field label="地址" value={data.address} error={errors.address} inputClass={inputClass} onChange={(value) => setData('address', value)} />
                        </section>
                    )}

                    <div>
                        <label className="mb-1 block text-sm text-secondary">備註</label>
                        <textarea className={inputClass} rows={4} value={data.notes} onChange={(event) => setData('notes', event.target.value)} />
                        {errors.notes && <p className="mt-1 text-sm text-accent">{errors.notes}</p>}
                    </div>

                    <div className="flex items-center gap-3">
                        <button type="submit" disabled={processing} className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white disabled:opacity-50">儲存</button>
                        <Link href={route('employee-system.customers.show', customer.id)} className="text-sm text-secondary underline decoration-1 underline-offset-2">返回詳情</Link>
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

