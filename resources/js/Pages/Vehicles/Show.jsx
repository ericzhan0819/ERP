import React from 'react';
import { Link } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { formatDate, formatDateTime } from '@/utils/formatDateTime';

/**
 * 技術註解：詳情頁僅輸出只讀核心欄位，避免誤導為可編輯流程並降低敏感資料暴露面。
 */
export default function VehiclesShow({
    auth,
    vehicle,
    lifecycleStatuses = {},
    can = {},
    vehicleCosts = [],
    vehicleCostSummary = null,
    vehicleCostTypes = {},
    vehicleCostPaymentStatuses = {},
    vehicleSales = [],
    vehicleSaleSummary = null,
}) {
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
        cancelled: 'bg-zinc-100 text-zinc-700 ring-zinc-200 dark:bg-zinc-900/40 dark:text-zinc-300 dark:ring-zinc-800',
    };

    const lifecycleLabel = lifecycleStatuses[vehicle.lifecycle_status] ?? displayValue(vehicle.lifecycle_status);
    const lifecycleBadgeClass = lifecycleBadgeMap[vehicle.lifecycle_status] ?? lifecycleBadgeMap.draft;

    const statusBadgeClass = (status) => lifecycleBadgeMap[status] ?? lifecycleBadgeMap.draft;

    /**
     * 技術註解：價格資訊顯示權限僅由後端 can 旗標決定，避免前端角色推斷造成敏感資訊誤曝。
     */
    const canViewVehiclePricing = can.view_vehicle_pricing === true;
    const canViewVehicleCosts = can.view_vehicle_costs === true;
    const canViewVehicleSales = can.view_vehicle_sales === true;

    const formatNumber = (value) => {
        if (value === null || value === undefined || value === '') {
            return '—';
        }

        const parsed = Number(value);

        if (Number.isNaN(parsed)) {
            return displayValue(value);
        }

        return parsed.toLocaleString('zh-TW');
    };

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

                {canViewVehiclePricing && (
                    <section className="rounded-2xl border border-default bg-surface p-4 text-secondary">
                        <h2 className="mb-3 text-sm font-semibold text-primary">銷售價格資訊</h2>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <p><span className="text-muted">開價：</span>{displayValue(vehicle.asking_price)}</p>
                            <p><span className="text-muted">底價：</span>{displayValue(vehicle.floor_price)}</p>
                        </div>
                    </section>
                )}

                {canViewVehicleSales && (
                    <section className="space-y-4 rounded-2xl border border-default bg-surface p-4 text-secondary">
                        <h2 className="text-sm font-semibold text-primary">銷售紀錄</h2>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="rounded-xl border border-default p-3">
                                <p className="text-xs text-muted">銷售狀態</p>
                                <p className="mt-1">
                                    <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${statusBadgeClass(vehicleSaleSummary?.latest_status)}`}>
                                        {displayValue(vehicleSaleSummary?.latest_status_label)}
                                    </span>
                                </p>
                            </div>
                            <div className="rounded-xl border border-default p-3">
                                <p className="text-xs text-muted">成交價</p>
                                <p className="mt-1 text-base font-semibold text-primary">{formatNumber(vehicleSaleSummary?.latest_sale_price)}</p>
                            </div>
                            <div className="rounded-xl border border-default p-3">
                                <p className="text-xs text-muted">訂金</p>
                                <p className="mt-1 text-base font-semibold text-primary">{formatNumber(vehicleSaleSummary?.latest_deposit_amount)}</p>
                            </div>
                            <div className="rounded-xl border border-default p-3">
                                <p className="text-xs text-muted">已付款</p>
                                <p className="mt-1 text-base font-semibold text-primary">{formatNumber(vehicleSaleSummary?.latest_paid_amount)}</p>
                            </div>
                            <div className="rounded-xl border border-default p-3">
                                <p className="text-xs text-muted">銷售筆數</p>
                                <p className="mt-1 text-base font-semibold text-primary">{formatNumber(vehicleSaleSummary?.count)}</p>
                            </div>
                            <div className="rounded-xl border border-default p-3">
                                <p className="text-xs text-muted">最近成交日</p>
                                <p className="mt-1 text-base font-semibold text-primary">{formatDate(vehicleSaleSummary?.latest_sold_at)}</p>
                            </div>
                        </div>

                        <div className="overflow-x-auto rounded-xl border border-default">
                            {vehicleSales.length === 0 ? (
                                <div className="p-6 text-center text-sm text-muted">尚無銷售紀錄。</div>
                            ) : (
                                <table className="min-w-full text-sm">
                                    <thead className="bg-slate-50 text-left text-xs text-muted dark:bg-slate-900/40">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">狀態</th>
                                            <th className="px-3 py-2 font-medium">客戶</th>
                                            <th className="px-3 py-2 font-medium">電話</th>
                                            <th className="px-3 py-2 font-medium">成交價</th>
                                            <th className="px-3 py-2 font-medium">訂金</th>
                                            <th className="px-3 py-2 font-medium">已付款</th>
                                            <th className="px-3 py-2 font-medium">成交日</th>
                                            <th className="px-3 py-2 font-medium">業務</th>
                                            <th className="px-3 py-2 font-medium">佣金</th>
                                            <th className="px-3 py-2 font-medium">備註</th>
                                            <th className="px-3 py-2 font-medium">建立者</th>
                                            <th className="px-3 py-2 font-medium">更新者</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {vehicleSales.map((sale) => (
                                            <tr key={sale.id} className="border-t border-default">
                                                <td className="px-3 py-2">
                                                    <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${statusBadgeClass(sale.sale_status)}`}>
                                                        {displayValue(sale.sale_status_label)}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2">{sale.customer ? `${sale.customer.customer_number}｜${displayValue(sale.customer_name)}` : displayValue(sale.customer_name)}</td>
                                                <td className="px-3 py-2">{displayValue(sale.customer_phone)}</td>
                                                <td className="px-3 py-2">{formatNumber(sale.sale_price)}</td>
                                                <td className="px-3 py-2">{formatNumber(sale.deposit_amount)}</td>
                                                <td className="px-3 py-2">{formatNumber(sale.paid_amount)}</td>
                                                <td className="px-3 py-2">{formatDate(sale.sold_at)}</td>
                                                <td className="px-3 py-2">{displayValue(sale.salesperson_name)}</td>
                                                <td className="px-3 py-2">{formatNumber(sale.commission_amount)}</td>
                                                <td className="px-3 py-2">{displayValue(sale.notes)}</td>
                                                <td className="px-3 py-2">{displayValue(sale.creator?.name)}</td>
                                                <td className="px-3 py-2">{displayValue(sale.updater?.name)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </section>
                )}

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
                                        {vehicleCosts.map((cost) => (
                                            <tr key={cost.id} className="border-t border-default">
                                                <td className="px-3 py-2">{displayValue(cost.cost_type_label)}</td>
                                                <td className="px-3 py-2">{displayValue(cost.description)}</td>
                                                <td className="px-3 py-2">{formatNumber(cost.amount)}</td>
                                                <td className="px-3 py-2">{formatDate(cost.cost_date)}</td>
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
