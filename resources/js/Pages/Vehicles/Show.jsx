import React from 'react';
import { Link } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { formatDateTime } from '@/utils/formatDateTime';

/**
 * 技術註解：詳情頁僅輸出只讀核心欄位，避免誤導為可編輯流程並降低敏感資料暴露面。
 */
export default function VehiclesShow({ auth, vehicle, lifecycleStatuses = {}, can = {} }) {
    /**
     * 技術註解：統一空值佔位，避免不同欄位出現不一致的缺值符號造成判讀成本上升。
     */
    const displayValue = (value) => {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        return value;
    };

    const lifecycleBadgeMap = {
        draft: 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-800/80 dark:text-slate-200 dark:ring-slate-700',
        in_stock: 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800',
        reserved: 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-900/40 dark:text-amber-200 dark:ring-amber-800',
        sold: 'bg-sky-100 text-sky-700 ring-sky-200 dark:bg-sky-900/40 dark:text-sky-200 dark:ring-sky-800',
        archived: 'bg-zinc-100 text-zinc-700 ring-zinc-200 dark:bg-zinc-900/40 dark:text-zinc-300 dark:ring-zinc-800',
    };

    const lifecycleLabel = lifecycleStatuses[vehicle.lifecycle_status] ?? displayValue(vehicle.lifecycle_status);
    const lifecycleBadgeClass = lifecycleBadgeMap[vehicle.lifecycle_status] ?? lifecycleBadgeMap.draft;

    /**
     * 技術註解：集中格式化公司/分店顯示，避免顯示 FK ID 且在缺資料時維持統一佔位符。
     */
    const formatOrg = (entity) => {
        if (!entity || (!entity.name && !entity.code)) {
            return '—';
        }

        const name = entity.name ?? '';
        const code = entity.code ?? '';

        if (name && code) {
            return `${name} / ${code}`;
        }

        return name || code || '—';
    };

    /**
     * 技術註解：詳情頁維持只讀且最小補修，補齊與編輯頁一致的重要欄位，避免使用者誤判資料缺漏。
     */
    const detailRows = [
        { label: '庫存編號', value: vehicle.stock_number },
        { label: '品牌', value: vehicle.brand },
        { label: '車型', value: vehicle.model },
        { label: '車款版本', value: vehicle.variant },
        { label: '年份', value: vehicle.model_year },
        { label: 'VIN', value: vehicle.vin },
        { label: '車牌號碼', value: vehicle.license_plate },
        { label: '外觀顏色', value: vehicle.exterior_color },
        { label: '內裝顏色', value: vehicle.interior_color },
        { label: '里程（公里）', value: vehicle.odometer_km },
    ];

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold text-primary">車輛詳情</h1>
                    <div className="flex items-center gap-2">
                        <Link
                            href={route('employee-system.vehicles.index')}
                            className="rounded-lg border border-default px-3 py-2 text-sm font-medium text-secondary transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
                        >
                            返回列表
                        </Link>
                        {can.update_vehicle && (
                            <Link
                                href={route('employee-system.vehicles.edit', vehicle.id)}
                                className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white"
                            >
                                編輯車輛
                            </Link>
                        )}
                    </div>
                </div>

                <section className="rounded-2xl border border-default bg-surface p-4 text-secondary">
                    <h2 className="mb-3 text-sm font-semibold text-primary">車輛基本資訊</h2>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {detailRows.map((row) => (
                            <p key={row.label}><span className="text-muted">{row.label}：</span>{displayValue(row.value)}</p>
                        ))}
                        <p>
                            <span className="text-muted">生命週期：</span>
                            <span className={`ml-2 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${lifecycleBadgeClass}`}>
                                {lifecycleLabel}
                            </span>
                        </p>
                    </div>
                </section>

                <section className="rounded-2xl border border-default bg-surface p-4 text-secondary">
                    <h2 className="mb-3 text-sm font-semibold text-primary">租戶資訊（唯讀）</h2>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <p><span className="text-muted">公司：</span>{formatOrg(vehicle.company)}</p>
                        <p><span className="text-muted">分店：</span>{formatOrg(vehicle.branch)}</p>
                    </div>
                </section>

                <section className="rounded-2xl border border-default bg-surface p-4 text-secondary">
                    <h2 className="mb-3 text-sm font-semibold text-primary">系統資訊</h2>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <p><span className="text-muted">建立時間：</span>{displayValue(formatDateTime(vehicle.created_at))}</p>
                        <p><span className="text-muted">更新時間：</span>{displayValue(formatDateTime(vehicle.updated_at))}</p>
                        <p><span className="text-muted">建立者：</span>{displayValue(vehicle.creator?.name)}</p>
                        <p><span className="text-muted">更新者：</span>{displayValue(vehicle.updater?.name)}</p>
                    </div>
                </section>

                <div className="rounded-2xl border border-default bg-surface p-4 text-secondary">
                    <p className="text-muted">內部備註</p>
                    <p className="mt-1 whitespace-pre-wrap text-secondary">{displayValue(vehicle.internal_notes)}</p>
                </div>
            </div>
        </DashboardLayout>
    );
}
