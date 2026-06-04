import React, { useMemo, useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：獨立新增頁只負責收集成本業務欄位；tenant、actor 與 vehicle 關聯由既有後端 mutation 依 route 參數控制，避免前端覆寫資料邊界。
 */
export default function VehicleCostsCreate({ auth, vehicleOptions = [], costTypes = {}, paymentStatuses = {}, defaults = {} }) {
    const [vehicleError, setVehicleError] = useState('');
    const { data, setData, post, processing, errors } = useForm({
        vehicle_id: defaults.vehicle_id ?? '',
        cost_type: Object.keys(costTypes)[0] ?? '',
        description: '',
        amount: '',
        cost_date: defaults.cost_date ?? '',
        vendor_name: '',
        payment_status: defaults.payment_status ?? 'unpaid',
        paid_at: '',
        internal_notes: '',
    });
    const selectedVehicle = useMemo(() => vehicleOptions.find((vehicle) => Number(vehicle.id) === Number(data.vehicle_id)), [vehicleOptions, data.vehicle_id]);
    const submit = (event) => {
        event.preventDefault();
        if (!data.vehicle_id) {
            setVehicleError('請先選擇車輛後再建立成本。');
            return;
        }
        setVehicleError('');
        post(route('employee-system.vehicles.costs.store', data.vehicle_id));
    };
    const fieldClass = 'rounded-lg border border-default bg-transparent px-3 py-2 text-sm text-primary';
    const labelClass = 'text-xs font-semibold uppercase tracking-[0.16em] text-muted';

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-5 p-4 md:p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">Vehicle Cost Workspace</p>
                        <h1 className="mt-1 text-2xl font-semibold text-primary">新增車輛成本</h1>
                        <p className="mt-1 text-sm text-secondary">建立後仍沿用既有車輛成本寫入流程與稽核事件。</p>
                    </div>
                    <Link href={route('employee-system.vehicle-costs.index')} className="rounded-lg border border-default px-4 py-2 text-sm font-semibold text-secondary">返回成本管理</Link>
                </div>

                <form onSubmit={submit} className="grid grid-cols-1 gap-4 rounded-2xl border border-default bg-surface p-5 md:grid-cols-2">
                    <label className="space-y-2 md:col-span-2"><span className={labelClass}>車輛</span><select value={data.vehicle_id} onChange={(event) => setData('vehicle_id', event.target.value)} className={`${fieldClass} w-full`}><option value="">請選擇車輛</option>{vehicleOptions.map((vehicle) => <option key={vehicle.id} value={vehicle.id}>{vehicle.stock_number}｜{vehicle.brand} {vehicle.model}｜{vehicle.license_plate ?? '未掛牌'}｜{vehicle.lifecycle_status}</option>)}</select>{(vehicleError || errors.vehicle_id) && <p className="text-sm text-red-600">{vehicleError || errors.vehicle_id}</p>}</label>
                    {selectedVehicle && <div className="rounded-xl border border-default bg-slate-50 p-3 text-sm text-secondary md:col-span-2 dark:bg-slate-900/40">已選車輛：{selectedVehicle.stock_number}｜{selectedVehicle.brand} {selectedVehicle.model}｜{selectedVehicle.license_plate ?? '未掛牌'}</div>}
                    <label className="space-y-2"><span className={labelClass}>成本類型</span><select value={data.cost_type} onChange={(event) => setData('cost_type', event.target.value)} className={`${fieldClass} w-full`}>{Object.entries(costTypes).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>{errors.cost_type && <p className="text-sm text-red-600">{errors.cost_type}</p>}</label>
                    <label className="space-y-2"><span className={labelClass}>金額</span><input type="number" min="0" step="1" value={data.amount} onChange={(event) => setData('amount', event.target.value)} className={`${fieldClass} w-full`} />{errors.amount && <p className="text-sm text-red-600">{errors.amount}</p>}</label>
                    <label className="space-y-2"><span className={labelClass}>成本日期</span><input type="date" value={data.cost_date} onChange={(event) => setData('cost_date', event.target.value)} className={`${fieldClass} w-full`} />{errors.cost_date && <p className="text-sm text-red-600">{errors.cost_date}</p>}</label>
                    <label className="space-y-2"><span className={labelClass}>付款狀態</span><select value={data.payment_status} onChange={(event) => setData('payment_status', event.target.value)} className={`${fieldClass} w-full`}>{Object.entries(paymentStatuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>{errors.payment_status && <p className="text-sm text-red-600">{errors.payment_status}</p>}</label>
                    <label className="space-y-2"><span className={labelClass}>廠商</span><input value={data.vendor_name} onChange={(event) => setData('vendor_name', event.target.value)} className={`${fieldClass} w-full`} />{errors.vendor_name && <p className="text-sm text-red-600">{errors.vendor_name}</p>}</label>
                    <label className="space-y-2"><span className={labelClass}>付款日期</span><input type="date" value={data.paid_at} onChange={(event) => setData('paid_at', event.target.value)} className={`${fieldClass} w-full`} />{errors.paid_at && <p className="text-sm text-red-600">{errors.paid_at}</p>}</label>
                    <label className="space-y-2 md:col-span-2"><span className={labelClass}>說明</span><input value={data.description} onChange={(event) => setData('description', event.target.value)} className={`${fieldClass} w-full`} />{errors.description && <p className="text-sm text-red-600">{errors.description}</p>}</label>
                    <label className="space-y-2 md:col-span-2"><span className={labelClass}>內部備註</span><textarea value={data.internal_notes} onChange={(event) => setData('internal_notes', event.target.value)} className={`${fieldClass} min-h-28 w-full`} />{errors.internal_notes && <p className="text-sm text-red-600">{errors.internal_notes}</p>}</label>
                    <div className="flex justify-end md:col-span-2"><button type="submit" disabled={processing} className="rounded-lg bg-primary px-5 py-2 text-sm font-semibold text-white disabled:opacity-50">建立成本</button></div>
                </form>
            </div>
        </DashboardLayout>
    );
}