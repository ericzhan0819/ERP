import React from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：維持極簡列表頁，僅呈現 Read Foundation Slice 必要欄位，避免提前引入大型 UI 或進階互動。
 */
export default function VehiclesIndex({ auth, vehicles = [] }) {
    return (
        <DashboardLayout user={auth.user}>
            <div className="p-6 space-y-4">
                <h1 className="text-xl font-semibold">Vehicles</h1>

                <ul className="space-y-2">
                    {vehicles.map((vehicle) => (
                        <li key={vehicle.id} className="border rounded p-3">
                            <a
                                href={route('employee-system.vehicles.show', vehicle.id)}
                                className="underline"
                            >
                                {vehicle.stock_number} - {vehicle.brand} {vehicle.model}
                            </a>
                        </li>
                    ))}
                </ul>
            </div>
        </DashboardLayout>
    );
}
