import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：詳情頁僅輸出只讀核心欄位，避免誤導為可編輯流程並降低敏感資料暴露面。
 */
export default function VehiclesShow({ auth, vehicle }) {
    return (
        <DashboardLayout user={auth.user}>
            <div className="p-6 space-y-2">
                <h1 className="text-xl font-semibold">Vehicle Detail</h1>
                <p>ID: {vehicle.id}</p>
                <p>Stock: {vehicle.stock_number}</p>
                <p>VIN: {vehicle.vin}</p>
                <p>Brand/Model: {vehicle.brand} {vehicle.model}</p>
                <p>Year: {vehicle.model_year}</p>
                <p>Status: {vehicle.lifecycle_status}</p>
            </div>
        </DashboardLayout>
    );
}
