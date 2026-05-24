import React from 'react';
import { Link } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：詳情頁僅輸出只讀核心欄位，避免誤導為可編輯流程並降低敏感資料暴露面。
 */
export default function VehiclesShow({ auth, vehicle, can = {} }) {
    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold text-primary">Vehicle Detail</h1>
                    {can.update_vehicle && (
                        <Link
                            href={route('employee-system.vehicles.edit', vehicle.id)}
                            className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white"
                        >
                            編輯車輛
                        </Link>
                    )}
                </div>
                <div className="rounded-2xl border border-default bg-surface p-4 text-secondary">
                    <div className="grid grid-cols-1 gap-2 md:grid-cols-2">
                        <p><span className="text-muted">ID:</span> {vehicle.id}</p>
                        <p><span className="text-muted">Stock:</span> {vehicle.stock_number}</p>
                        <p><span className="text-muted">VIN:</span> {vehicle.vin || '-'}</p>
                        <p><span className="text-muted">License Plate:</span> {vehicle.license_plate || '-'}</p>
                        <p><span className="text-muted">Brand:</span> {vehicle.brand}</p>
                        <p><span className="text-muted">Model:</span> {vehicle.model}</p>
                        <p><span className="text-muted">Variant:</span> {vehicle.variant || '-'}</p>
                        <p><span className="text-muted">Year:</span> {vehicle.model_year ?? '-'}</p>
                        <p><span className="text-muted">Exterior Color:</span> {vehicle.exterior_color || '-'}</p>
                        <p><span className="text-muted">Interior Color:</span> {vehicle.interior_color || '-'}</p>
                        <p><span className="text-muted">Odometer km:</span> {vehicle.odometer_km ?? '-'}</p>
                        <p><span className="text-muted">Status:</span> {vehicle.lifecycle_status}</p>
                    </div>
                    <div className="mt-4 border-t border-default pt-3">
                        <p className="text-muted">Internal Notes</p>
                        <p className="mt-1 whitespace-pre-wrap text-secondary">{vehicle.internal_notes || '-'}</p>
                    </div>
                </div>
                <div>
                    <Link href={route('employee-system.vehicles.index')} className="text-sm text-secondary underline decoration-1 underline-offset-2">
                        返回列表
                    </Link>
                </div>
            </div>
        </DashboardLayout>
    );
}
