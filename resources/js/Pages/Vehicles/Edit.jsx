import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：編輯頁與建立頁保持相近結構，降低維護成本並避免過度抽象造成認知負擔。
 */
export default function VehiclesEdit({
    auth,
    vehicle,
    lifecycleStatuses = {},
    can = {},
    vehicleCosts = [],
    vehicleCostSummary = null,
    vehicleCostTypes = {},
    vehicleCostPaymentStatuses = {},
}) {
    const canUpdateVehiclePricing = can.update_vehicle_pricing === true;
    const canViewVehicleCosts = can.view_vehicle_costs === true;
    const canCreateVehicleCosts = can.create_vehicle_costs === true;

    const { data, setData, patch, processing, errors } = useForm({
        vin: vehicle.vin ?? '',
        license_plate: vehicle.license_plate ?? '',
        brand: vehicle.brand ?? '',
        model: vehicle.model ?? '',
        variant: vehicle.variant ?? '',
        model_year: vehicle.model_year ?? '',
        exterior_color: vehicle.exterior_color ?? '',
        interior_color: vehicle.interior_color ?? '',
        odometer_km: vehicle.odometer_km ?? '',
        asking_price: vehicle.asking_price ?? '',
        floor_price: vehicle.floor_price ?? '',
        lifecycle_status: vehicle.lifecycle_status ?? 'draft',
        internal_notes: vehicle.internal_notes ?? '',
    });

    const costForm = useForm({
        cost_type: '',
        description: '',
        amount: '',
        cost_date: '',
        vendor_name: '',
        payment_status: '',
        paid_at: '',
        internal_notes: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        // 技術註解：即使畫面隱藏欄位，仍以送出白名單排除未授權價格欄位，降低權限提升與參數注入風險。
        const payload = {
            vin: data.vin,
            license_plate: data.license_plate,
            brand: data.brand,
            model: data.model,
            variant: data.variant,
            model_year: data.model_year,
            exterior_color: data.exterior_color,
            interior_color: data.interior_color,
            odometer_km: data.odometer_km,
            lifecycle_status: data.lifecycle_status,
            internal_notes: data.internal_notes,
            ...(canUpdateVehiclePricing
                ? {
                    asking_price: data.asking_price,
                    floor_price: data.floor_price,
                }
                : {}),
        };

        patch(route('employee-system.vehicles.update', vehicle.id), { data: payload });
    };

    const inputClass = 'w-full rounded-lg border border-default bg-surface px-3 py-2 text-primary focus:outline-none focus:ring-2 focus:ring-accent';

    /**
     * 技術註解：成本摘要顯示只做格式化，不在前端推導權限或敏感欄位，避免邏輯分散造成資料外洩風險。
     */
    const formatNumber = (value) => {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        const parsed = Number(value);

        if (Number.isNaN(parsed)) {
            return value;
        }

        return parsed.toLocaleString('zh-TW');
    };

    const displayValue = (value) => {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        return value;
    };

    const submitCostForm = (event) => {
        event.preventDefault();

        // 技術註解：成本建立請求僅送出 allowlist 欄位，明確避免 company_id/branch_id/vehicle_id/created_by/updated_by 注入。
        const payload = {
            cost_type: costForm.data.cost_type,
            description: costForm.data.description,
            amount: costForm.data.amount,
            cost_date: costForm.data.cost_date,
            vendor_name: costForm.data.vendor_name,
            payment_status: costForm.data.payment_status,
            paid_at: costForm.data.paid_at,
            internal_notes: costForm.data.internal_notes,
        };

        costForm.post(route('employee-system.vehicles.costs.store', vehicle.id), {
            data: payload,
            preserveScroll: true,
            onSuccess: () => {
                costForm.reset();
            },
        });
    };

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-4 p-6">
                <h1 className="text-xl font-semibold text-primary">編輯車輛</h1>

                <form onSubmit={handleSubmit} className="space-y-4 rounded-2xl border border-default bg-surface p-4">
                    <div>
                        <label className="mb-1 block text-sm text-secondary">庫存編號</label>
                        <input className={inputClass} value={vehicle.stock_number ?? ''} readOnly />
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div><label className="mb-1 block text-sm text-secondary">車身號碼（VIN）</label><input className={inputClass} value={data.vin} onChange={(e) => setData('vin', e.target.value)} />{errors.vin && <p className="mt-1 text-sm text-accent">{errors.vin}</p>}</div>
                        <div><label className="mb-1 block text-sm text-secondary">車牌號碼</label><input className={inputClass} value={data.license_plate} onChange={(e) => setData('license_plate', e.target.value)} />{errors.license_plate && <p className="mt-1 text-sm text-accent">{errors.license_plate}</p>}</div>
                        <div><label className="mb-1 block text-sm text-secondary">品牌</label><input className={inputClass} value={data.brand} onChange={(e) => setData('brand', e.target.value)} />{errors.brand && <p className="mt-1 text-sm text-accent">{errors.brand}</p>}</div>
                        <div><label className="mb-1 block text-sm text-secondary">車型</label><input className={inputClass} value={data.model} onChange={(e) => setData('model', e.target.value)} />{errors.model && <p className="mt-1 text-sm text-accent">{errors.model}</p>}</div>
                        <div><label className="mb-1 block text-sm text-secondary">車款版本</label><input className={inputClass} value={data.variant} onChange={(e) => setData('variant', e.target.value)} />{errors.variant && <p className="mt-1 text-sm text-accent">{errors.variant}</p>}</div>
                        <div><label className="mb-1 block text-sm text-secondary">年份</label><input type="number" className={inputClass} value={data.model_year} onChange={(e) => setData('model_year', e.target.value)} />{errors.model_year && <p className="mt-1 text-sm text-accent">{errors.model_year}</p>}</div>
                        <div><label className="mb-1 block text-sm text-secondary">生命週期狀態</label><select className={inputClass} value={data.lifecycle_status} onChange={(e) => setData('lifecycle_status', e.target.value)}>{Object.entries(lifecycleStatuses).map(([value, label]) => (<option key={value} value={value}>{label}</option>))}</select>{errors.lifecycle_status && <p className="mt-1 text-sm text-accent">{errors.lifecycle_status}</p>}</div>
                        <div><label className="mb-1 block text-sm text-secondary">外觀顏色</label><input className={inputClass} value={data.exterior_color} onChange={(e) => setData('exterior_color', e.target.value)} />{errors.exterior_color && <p className="mt-1 text-sm text-accent">{errors.exterior_color}</p>}</div>
                        <div><label className="mb-1 block text-sm text-secondary">內裝顏色</label><input className={inputClass} value={data.interior_color} onChange={(e) => setData('interior_color', e.target.value)} />{errors.interior_color && <p className="mt-1 text-sm text-accent">{errors.interior_color}</p>}</div>
                        <div><label className="mb-1 block text-sm text-secondary">里程（公里）</label><input type="number" className={inputClass} value={data.odometer_km} onChange={(e) => setData('odometer_km', e.target.value)} />{errors.odometer_km && <p className="mt-1 text-sm text-accent">{errors.odometer_km}</p>}</div>
                        {canUpdateVehiclePricing && (
                            <>
                                <div><label className="mb-1 block text-sm text-secondary">開價</label><input type="number" className={inputClass} value={data.asking_price} onChange={(e) => setData('asking_price', e.target.value)} />{errors.asking_price && <p className="mt-1 text-sm text-accent">{errors.asking_price}</p>}</div>
                                <div><label className="mb-1 block text-sm text-secondary">底價</label><input type="number" className={inputClass} value={data.floor_price} onChange={(e) => setData('floor_price', e.target.value)} />{errors.floor_price && <p className="mt-1 text-sm text-accent">{errors.floor_price}</p>}</div>
                            </>
                        )}
                    </div>

                    <div>
                        <label className="mb-1 block text-sm text-secondary">內部備註</label>
                        <textarea className={inputClass} rows={4} value={data.internal_notes} onChange={(e) => setData('internal_notes', e.target.value)} />
                        {errors.internal_notes && <p className="mt-1 text-sm text-accent">{errors.internal_notes}</p>}
                    </div>

                    <div className="flex items-center gap-3">
                        <button type="submit" disabled={processing} className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white disabled:opacity-50">儲存</button>
                        <Link href={route('employee-system.vehicles.show', vehicle.id)} className="text-sm text-secondary underline decoration-1 underline-offset-2">返回詳情</Link>
                    </div>
                </form>

                {canViewVehicleCosts && (
                    <section className="space-y-4 rounded-2xl border border-default bg-surface p-4 text-secondary">
                        <h2 className="text-sm font-semibold text-primary">成本管理</h2>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="rounded-xl border border-default p-3">
                                <p className="text-xs text-muted">總成本</p>
                                <p className="mt-1 text-base font-semibold text-primary">{formatNumber(vehicleCostSummary?.total_amount)}</p>
                            </div>
                            <div className="rounded-xl border border-default p-3">
                                <p className="text-xs text-muted">已付款</p>
                                <p className="mt-1 text-base font-semibold text-primary">{formatNumber(vehicleCostSummary?.paid_amount)}</p>
                            </div>
                            <div className="rounded-xl border border-default p-3">
                                <p className="text-xs text-muted">未付款</p>
                                <p className="mt-1 text-base font-semibold text-primary">{formatNumber(vehicleCostSummary?.unpaid_amount)}</p>
                            </div>
                            <div className="rounded-xl border border-default p-3">
                                <p className="text-xs text-muted">成本筆數</p>
                                <p className="mt-1 text-base font-semibold text-primary">{formatNumber(vehicleCostSummary?.count)}</p>
                            </div>
                        </div>

                        {canCreateVehicleCosts && (
                            <form onSubmit={submitCostForm} className="space-y-3 rounded-xl border border-default p-4">
                                <h3 className="text-sm font-semibold text-primary">新增成本</h3>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    <label className="text-sm">
                                        <span className="mb-1 block text-muted">成本類型</span>
                                        <select
                                            value={costForm.data.cost_type}
                                            onChange={(event) => costForm.setData('cost_type', event.target.value)}
                                            className="w-full rounded-lg border border-default bg-transparent px-3 py-2 text-sm"
                                        >
                                            <option value="">請選擇</option>
                                            {Object.entries(vehicleCostTypes || {}).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </select>
                                    </label>
                                    <label className="text-sm">
                                        <span className="mb-1 block text-muted">金額</span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={costForm.data.amount}
                                            onChange={(event) => costForm.setData('amount', event.target.value)}
                                            className="w-full rounded-lg border border-default bg-transparent px-3 py-2 text-sm"
                                        />
                                    </label>
                                    <label className="text-sm">
                                        <span className="mb-1 block text-muted">日期</span>
                                        <input
                                            type="date"
                                            value={costForm.data.cost_date}
                                            onChange={(event) => costForm.setData('cost_date', event.target.value)}
                                            className="w-full rounded-lg border border-default bg-transparent px-3 py-2 text-sm"
                                        />
                                    </label>
                                    <label className="text-sm">
                                        <span className="mb-1 block text-muted">廠商</span>
                                        <input
                                            type="text"
                                            value={costForm.data.vendor_name}
                                            onChange={(event) => costForm.setData('vendor_name', event.target.value)}
                                            className="w-full rounded-lg border border-default bg-transparent px-3 py-2 text-sm"
                                        />
                                    </label>
                                    <label className="text-sm">
                                        <span className="mb-1 block text-muted">付款狀態</span>
                                        <select
                                            value={costForm.data.payment_status}
                                            onChange={(event) => costForm.setData('payment_status', event.target.value)}
                                            className="w-full rounded-lg border border-default bg-transparent px-3 py-2 text-sm"
                                        >
                                            <option value="">請選擇</option>
                                            {Object.entries(vehicleCostPaymentStatuses || {}).map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </select>
                                    </label>
                                    <label className="text-sm">
                                        <span className="mb-1 block text-muted">付款日期</span>
                                        <input
                                            type="date"
                                            value={costForm.data.paid_at}
                                            onChange={(event) => costForm.setData('paid_at', event.target.value)}
                                            className="w-full rounded-lg border border-default bg-transparent px-3 py-2 text-sm"
                                        />
                                    </label>
                                </div>

                                <label className="block text-sm">
                                    <span className="mb-1 block text-muted">說明</span>
                                    <input
                                        type="text"
                                        value={costForm.data.description}
                                        onChange={(event) => costForm.setData('description', event.target.value)}
                                        className="w-full rounded-lg border border-default bg-transparent px-3 py-2 text-sm"
                                    />
                                </label>

                                <label className="block text-sm">
                                    <span className="mb-1 block text-muted">內部備註</span>
                                    <textarea
                                        value={costForm.data.internal_notes}
                                        onChange={(event) => costForm.setData('internal_notes', event.target.value)}
                                        className="min-h-20 w-full rounded-lg border border-default bg-transparent px-3 py-2 text-sm"
                                    />
                                </label>

                                {Object.keys(costForm.errors).length > 0 && (
                                    <ul className="space-y-1 text-xs text-red-600">
                                        {Object.values(costForm.errors).map((error) => (
                                            <li key={error}>{error}</li>
                                        ))}
                                    </ul>
                                )}

                                <div>
                                    <button
                                        type="submit"
                                        disabled={costForm.processing}
                                        className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        新增成本
                                    </button>
                                </div>
                            </form>
                        )}

                        <div className="overflow-x-auto rounded-xl border border-default">
                            {vehicleCosts.length === 0 ? (
                                <div className="p-6 text-center text-sm text-muted">目前沒有成本資料。</div>
                            ) : (
                                <table className="min-w-full text-sm">
                                    <thead className="bg-slate-50 text-left text-xs text-muted dark:bg-slate-900/40">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">成本類型</th>
                                            <th className="px-3 py-2 font-medium">說明</th>
                                            <th className="px-3 py-2 font-medium">金額</th>
                                            <th className="px-3 py-2 font-medium">日期</th>
                                            <th className="px-3 py-2 font-medium">廠商</th>
                                            <th className="px-3 py-2 font-medium">付款狀態</th>
                                            <th className="px-3 py-2 font-medium">建立者</th>
                                            <th className="px-3 py-2 font-medium">更新者</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(vehicleCosts || []).map((cost) => (
                                            <tr key={cost.id} className="border-t border-default">
                                                <td className="px-3 py-2">{displayValue(cost.cost_type_label)}</td>
                                                <td className="px-3 py-2">{displayValue(cost.description)}</td>
                                                <td className="px-3 py-2">{formatNumber(cost.amount)}</td>
                                                <td className="px-3 py-2">{displayValue(cost.cost_date)}</td>
                                                <td className="px-3 py-2">{displayValue(cost.vendor_name)}</td>
                                                <td className="px-3 py-2">{displayValue(cost.payment_status_label)}</td>
                                                <td className="px-3 py-2">{displayValue(cost.creator?.name)}</td>
                                                <td className="px-3 py-2">{displayValue(cost.updater?.name)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </section>
                )}
            </div>
        </DashboardLayout>
    );
}
