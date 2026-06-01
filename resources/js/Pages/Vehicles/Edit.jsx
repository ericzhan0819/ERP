import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：編輯頁與建立頁保持相近結構，降低維護成本並避免過度抽象造成認知負擔。
 */
export default function VehiclesEdit({ auth, vehicle, lifecycleStatuses = {}, can = {} }) {
    const canUpdateVehiclePricing = can.update_vehicle_pricing === true;

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
            </div>
        </DashboardLayout>
    );
}
