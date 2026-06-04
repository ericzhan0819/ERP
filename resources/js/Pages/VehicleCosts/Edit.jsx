import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：編輯頁不提供 vehicle_id 欄位，更新仍透過既有 vehicle/cost route 參數讓後端 scoped query 驗證關聯與 tenant。
 */
export default function VehicleCostsEdit({ auth, cost, costTypes = {}, paymentStatuses = {} }) {
    const { data, setData, patch, processing, errors } = useForm({
        cost_type: cost.cost_type ?? '',
        description: cost.description ?? '',
        amount: cost.amount ?? '',
        cost_date: cost.cost_date ?? '',
        vendor_name: cost.vendor_name ?? '',
        payment_status: cost.payment_status ?? 'unpaid',
        paid_at: cost.paid_at ?? '',
        internal_notes: cost.internal_notes ?? '',
    });
    const submit = (event) => {
        event.preventDefault();
        patch(route('employee-system.vehicles.costs.update', [cost.vehicle_id, cost.id]));
    };
    const fieldClass = 'rounded-lg border border-default bg-transparent px-3 py-2 text-sm text-primary';
    const labelClass = 'text-xs font-semibold uppercase tracking-[0.16em] text-muted';

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-5 p-4 md:p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">Vehicle Cost Workspace</p>
                        <h1 className="mt-1 text-2xl font-semibold text-primary">編輯車輛成本</h1>
                        <p className="mt-1 text-sm text-secondary">更新仍沿用既有車輛成本流程與稽核事件。</p>
                    </div>
                    <div className="flex gap-2"><Link href={route('employee-system.vehicle-costs.index')} className="rounded-lg border border-default px-4 py-2 text-sm font-semibold text-secondary">返回成本管理</Link>{cost.vehicle?.id && <Link href={route('employee-system.vehicles.show', cost.vehicle.id)} className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white">查看車輛</Link>}</div>
                </div>

                <section className="rounded-2xl border border-default bg-surface p-4">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted">關聯車輛</p>
                    <p className="mt-2 text-lg font-semibold text-primary">{cost.vehicle?.stock_number}｜{cost.vehicle?.brand} {cost.vehicle?.model}</p>
                    <p className="mt-1 text-sm text-secondary">車牌：{cost.vehicle?.license_plate ?? '未掛牌'}｜狀態：{cost.vehicle?.lifecycle_status ?? '—'}</p>
                </section>

                <form onSubmit={submit} className="grid grid-cols-1 gap-4 rounded-2xl border border-default bg-surface p-5 md:grid-cols-2">
                    <label className="space-y-2"><span className={labelClass}>成本類型</span><select value={data.cost_type} onChange={(event) => setData('cost_type', event.target.value)} className={`${fieldClass} w-full`}>{Object.entries(costTypes).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>{errors.cost_type && <p className="text-sm text-red-600">{errors.cost_type}</p>}</label>
                    <label className="space-y-2"><span className={labelClass}>金額</span><input type="number" min="0" step="1" value={data.amount} onChange={(event) => setData('amount', event.target.value)} className={`${fieldClass} w-full`} />{errors.amount && <p className="text-sm text-red-600">{errors.amount}</p>}</label>
                    <label className="space-y-2"><span className={labelClass}>成本日期</span><input type="date" value={data.cost_date} onChange={(event) => setData('cost_date', event.target.value)} className={`${fieldClass} w-full`} />{errors.cost_date && <p className="text-sm text-red-600">{errors.cost_date}</p>}</label>
                    <label className="space-y-2"><span className={labelClass}>付款狀態</span><select value={data.payment_status} onChange={(event) => setData('payment_status', event.target.value)} className={`${fieldClass} w-full`}>{Object.entries(paymentStatuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>{errors.payment_status && <p className="text-sm text-red-600">{errors.payment_status}</p>}</label>
                    <label className="space-y-2"><span className={labelClass}>廠商</span><input value={data.vendor_name} onChange={(event) => setData('vendor_name', event.target.value)} className={`${fieldClass} w-full`} />{errors.vendor_name && <p className="text-sm text-red-600">{errors.vendor_name}</p>}</label>
                    <label className="space-y-2"><span className={labelClass}>付款日期</span><input type="date" value={data.paid_at} onChange={(event) => setData('paid_at', event.target.value)} className={`${fieldClass} w-full`} />{errors.paid_at && <p className="text-sm text-red-600">{errors.paid_at}</p>}</label>
                    <label className="space-y-2 md:col-span-2"><span className={labelClass}>說明</span><input value={data.description} onChange={(event) => setData('description', event.target.value)} className={`${fieldClass} w-full`} />{errors.description && <p className="text-sm text-red-600">{errors.description}</p>}</label>
                    <label className="space-y-2 md:col-span-2"><span className={labelClass}>內部備註</span><textarea value={data.internal_notes} onChange={(event) => setData('internal_notes', event.target.value)} className={`${fieldClass} min-h-28 w-full`} />{errors.internal_notes && <p className="text-sm text-red-600">{errors.internal_notes}</p>}</label>
                    <div className="flex justify-end md:col-span-2"><button type="submit" disabled={processing} className="rounded-lg bg-primary px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">更新成本</button></div>
                </form>
            </div>
        </DashboardLayout>
    );
}