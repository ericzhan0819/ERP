import React from 'react';
import { Link, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { formatDate } from '@/utils/formatDateTime';

/**
 * 技術註解：車輛成本管理 Phase 1 僅集中查詢既有 vehicle_costs，不提供新增/編輯成本與任何利潤或會計推導。
 */
export default function VehicleCostsIndex({ auth, costs, filters = {}, periodOptions = {}, costTypes = {}, paymentStatuses = {}, summary = {}, can = {} }) {
    const rows = costs?.data ?? [];
    const links = costs?.links ?? [];
    const displayValue = (value) => (value === null || value === undefined || value === '' ? '—' : value);
    const formatMoney = (value) => {
        const parsed = Number(value ?? 0);
        return Number.isNaN(parsed) ? '—' : parsed.toLocaleString('zh-TW', { maximumFractionDigits: 0 });
    };
    const paginationLabel = (label) => label.replace('&laquo;', '‹').replace('&raquo;', '›');
    const periodLabel = periodOptions[filters.period] ?? '本月';
    const periodRangeLabel = filters.period === 'all' ? '全期間' : `${filters.date_from || '未設定'} ～ ${filters.date_to || '未設定'}`;
    const sanitizeQuery = (query) => Object.fromEntries(Object.entries(query).filter(([, value]) => value !== '' && value !== null && value !== undefined && value !== 'all'));
    const updateFilter = (key, value) => {
        const nextFilters = { ...filters, [key]: value };

        if (key === 'date_from' || key === 'date_to') {
            nextFilters.period = 'custom';
        }

        router.get(route('employee-system.vehicle-costs.index'), sanitizeQuery(nextFilters), { preserveState: true, replace: true });
    };
    const updatePeriod = (period) => {
        const nextFilters = { ...filters, period };

        if (period !== 'custom') {
            delete nextFilters.date_from;
            delete nextFilters.date_to;
        }

        router.get(route('employee-system.vehicle-costs.index'), sanitizeQuery(nextFilters), { preserveState: true, replace: true });
    };

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-5 p-4 md:p-6">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">Vehicle Costs</p>
                    <h1 className="mt-1 text-2xl font-semibold text-primary">車輛成本管理</h1>
                    <p className="mt-1 text-sm text-secondary">集中查看車輛整備、維修、採購等成本紀錄。</p>
                </div>

                <section className="rounded-2xl border border-default bg-surface p-4">
                    <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted">查詢期間</p>
                            <p className="mt-1 text-lg font-semibold text-primary">{periodLabel}｜{periodRangeLabel}</p>
                            <p className="mt-1 text-sm text-secondary">摘要依目前期間與篩選條件計算；如需查歷史資料，請切換為全部或自訂日期。</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {Object.entries(periodOptions).map(([value, label]) => (
                                <button key={value} type="button" onClick={() => updatePeriod(value)} className={`rounded-full border px-3 py-1.5 text-sm transition ${filters.period === value ? 'border-primary bg-primary text-white' : 'border-default bg-transparent text-secondary hover:border-primary hover:text-primary'}`}>
                                    {label}
                                </button>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="grid grid-cols-1 gap-3 md:grid-cols-4">
                    {[
                        ['查詢期間成本總額', summary.total_amount],
                        ['查詢期間已付款', summary.paid_amount],
                        ['查詢期間未付款', summary.unpaid_amount],
                        ['查詢期間筆數', summary.count, false],
                    ].map(([label, value, money = true]) => (
                        <div key={label} className="rounded-2xl border border-default bg-surface p-4">
                            <p className="text-xs font-medium text-muted">{label}</p>
                            <p className="mt-2 text-2xl font-semibold text-primary">{money ? `$ ${formatMoney(value)}` : formatMoney(value)}</p>
                        </div>
                    ))}
                </section>

                <section className="grid grid-cols-1 gap-3 rounded-2xl border border-default bg-surface p-4 md:grid-cols-5">
                    <input value={filters.q ?? ''} onChange={(event) => updateFilter('q', event.target.value)} placeholder="搜尋車號 / 車牌 / 品牌 / 說明 / 廠商" className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm md:col-span-2" />
                    <select value={filters.cost_type ?? 'all'} onChange={(event) => updateFilter('cost_type', event.target.value === 'all' ? '' : event.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm">
                        <option value="all">全部成本類型</option>
                        {Object.entries(costTypes).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                    <select value={filters.payment_status ?? 'all'} onChange={(event) => updateFilter('payment_status', event.target.value === 'all' ? '' : event.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm">
                        <option value="all">全部付款狀態</option>
                        {Object.entries(paymentStatuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                    <div className="grid grid-cols-2 gap-2">
                        <input type="date" value={filters.date_from ?? ''} onChange={(event) => updateFilter('date_from', event.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm" />
                        <input type="date" value={filters.date_to ?? ''} onChange={(event) => updateFilter('date_to', event.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm" />
                    </div>
                </section>

                <section className="overflow-x-auto rounded-2xl border border-default bg-surface">
                    {rows.length === 0 ? <div className="p-8 text-center text-sm text-muted">目前沒有符合條件的車輛成本紀錄。</div> : (
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs text-muted dark:bg-slate-900/40">
                                <tr>
                                    <th className="px-3 py-3 font-medium">日期</th><th className="px-3 py-3 font-medium">車輛</th><th className="px-3 py-3 font-medium">成本類型</th><th className="px-3 py-3 font-medium">說明</th><th className="px-3 py-3 font-medium">金額</th><th className="px-3 py-3 font-medium">廠商</th><th className="px-3 py-3 font-medium">付款狀態</th><th className="px-3 py-3 font-medium">建立者</th><th className="px-3 py-3 font-medium">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((cost) => (
                                    <tr key={cost.id} className="border-t border-default">
                                        <td className="px-3 py-3 whitespace-nowrap">{formatDate(cost.cost_date)}</td>
                                        <td className="px-3 py-3 min-w-60">{displayValue(cost.vehicle?.stock_number)}｜{displayValue(cost.vehicle?.brand)} {displayValue(cost.vehicle?.model)}｜{displayValue(cost.vehicle?.license_plate)}</td>
                                        <td className="px-3 py-3 whitespace-nowrap">{costTypes[cost.cost_type] ?? displayValue(cost.cost_type)}</td>
                                        <td className="px-3 py-3 min-w-52">{displayValue(cost.description)}</td>
                                        <td className="px-3 py-3 whitespace-nowrap font-semibold text-primary">$ {formatMoney(cost.amount)}</td>
                                        <td className="px-3 py-3 whitespace-nowrap">{displayValue(cost.vendor)}</td>
                                        <td className="px-3 py-3 whitespace-nowrap">{paymentStatuses[cost.payment_status] ?? displayValue(cost.payment_status)}</td>
                                        <td className="px-3 py-3 whitespace-nowrap">{displayValue(cost.creator_name)}</td>
                                        <td className="px-3 py-3 whitespace-nowrap">
                                            {cost.vehicle?.id && <Link href={route('employee-system.vehicles.show', cost.vehicle.id)} className="text-accent underline underline-offset-2">查看車輛</Link>}
                                            {can.edit_vehicle && cost.vehicle?.id && <Link href={route('employee-system.vehicles.edit', cost.vehicle.id)} className="ml-3 text-secondary underline underline-offset-2">編輯車輛</Link>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>

                {links.length > 0 && (
                    <nav className="flex flex-wrap gap-2">
                        {links.map((link, index) => <Link key={`${link.label}-${index}`} href={link.url ?? '#'} preserveScroll className={`rounded-lg border border-default px-3 py-2 text-sm ${link.active ? 'bg-primary text-white' : 'bg-surface text-secondary'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}>{paginationLabel(link.label)}</Link>)}
                    </nav>
                )}
            </div>
        </DashboardLayout>
    );
}