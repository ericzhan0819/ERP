import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：詳情頁僅輸出只讀核心欄位，避免誤導為可編輯流程並降低敏感資料暴露面。
 */
export default function VehiclesShow({ auth, vehicle }) {
    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-3 p-6">
                <h1 className="text-xl font-semibold text-primary">Vehicle Detail</h1>
                <div className="rounded-2xl border border-default bg-surface p-4 text-secondary">
                    <p><span className="text-muted">ID:</span> {vehicle.id}</p>
                    <p><span className="text-muted">Stock:</span> {vehicle.stock_number}</p>
                    <p><span className="text-muted">VIN:</span> {vehicle.vin}</p>
                    <p><span className="text-muted">Brand/Model:</span> {vehicle.brand} {vehicle.model}</p>
                    <p><span className="text-muted">Year:</span> {vehicle.model_year}</p>
                    <p><span className="text-muted">Status:</span> {vehicle.lifecycle_status}</p>
                </div>
            </div>
        </DashboardLayout>
    );
}
