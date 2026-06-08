import React from 'react';
import { Link, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { formatDate } from '@/utils/formatDateTime';

/**
 * 技術註解：收款管理列表只呈現 vehicle_sales 與 vehicle_sale_payments 的操作入口，不建立新的應收資料模型。
 */
export default function ReceivablesIndex({ auth, sales, filters = {}, receivableStatuses = {}, saleStatuses = {} }) {
    const rows = sales?.data ?? [];
    const displayValue = (value) => (value === null || value === undefined || value === '' ? '—' : value);
    const formatNumber = (value) => {
        if (value === null || value === undefined || value === '') return '未設定成交價';
        const parsed = Number(value);
        return Number.isNaN(parsed) ? displayValue(value) : parsed.toLocaleString('zh-TW');
    };
    const completionBadgeClass = (status) => status === 'completed'
        ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800'
        : 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-800/80 dark:text-slate-200 dark:ring-slate-700';
    const updateFilter = (key, value) => router.get(route('employee-system.receivables.index'), { ...filters, [key]: value }, { preserveState: true, replace: true });

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-4 p-4 md:p-6">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">Receivables</p>
                    <h1 className="mt-1 text-2xl font-semibold text-primary">收款管理</h1>
                    <p className="mt-1 text-sm text-secondary">以銷售交易為來源，集中查看應收、已收與收款紀錄。</p>
                </div>

                <section className="rounded-2xl border border-default bg-surface p-4 text-sm text-secondary">
                    <p className="font-semibold text-primary">收款語意提示</p>
                    <p className="mt-2 text-xs leading-6 text-muted">本頁處理應收、已收、未收與收款紀錄；paid / overpaid 只代表收款面已完成或超收，不等於收入認列，也不等於交車完成。mark sold 只銜接銷售與車輛售出狀態，不會自動入帳。</p>
                </section>

                <section className="grid grid-cols-1 gap-3 rounded-2xl border border-default bg-surface p-4 md:grid-cols-3">
                    <input value={filters.q ?? ''} onChange={(event) => updateFilter('q', event.target.value)} placeholder="搜尋車輛 / VIN / 客戶 / 電話" className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm" />
                    <select value={filters.receivable_status ?? 'all'} onChange={(event) => updateFilter('receivable_status', event.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm">
                        <option value="all">全部收款狀態</option>
                        {Object.entries(receivableStatuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                    <select value={filters.sale_status ?? 'all'} onChange={(event) => updateFilter('sale_status', event.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm">
                        <option value="all">全部銷售狀態</option>
                        {['reserved', 'sold'].map((value) => <option key={value} value={value}>{saleStatuses[value] ?? value}</option>)}
                    </select>
                </section>

                <section className="overflow-x-auto rounded-2xl border border-default bg-surface">
                    {rows.length === 0 ? <div className="p-8 text-center text-sm text-muted">目前沒有可收款銷售。</div> : (
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs text-muted dark:bg-slate-900/40">
                                <tr>
                                    <th className="px-3 py-3 font-medium">收款狀態</th><th className="px-3 py-3 font-medium">車輛</th><th className="px-3 py-3 font-medium">客戶</th><th className="px-3 py-3 font-medium">銷售狀態</th><th className="px-3 py-3 font-medium">交易完成</th><th className="px-3 py-3 font-medium">成交價</th><th className="px-3 py-3 font-medium">已收</th><th className="px-3 py-3 font-medium">未收</th><th className="px-3 py-3 font-medium">最近收款</th><th className="px-3 py-3 font-medium">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((sale) => (
                                    <tr key={sale.id} className="border-t border-default">
                                        <td className="px-3 py-3 font-medium text-primary">{displayValue(sale.payment_summary?.receivable_status_label)}</td>
                                        <td className="px-3 py-3">{displayValue(sale.vehicle?.stock_number)}｜{displayValue(sale.vehicle?.brand)} {displayValue(sale.vehicle?.model)}｜{displayValue(sale.vehicle?.license_plate)}</td>
                                        <td className="px-3 py-3">{sale.customer?.customer_number ? `${sale.customer.customer_number}｜` : ''}{displayValue(sale.customer_name)}｜{displayValue(sale.customer_phone)}</td>
                                        <td className="px-3 py-3">{displayValue(sale.sale_status_label)}</td>
                                        <td className="px-3 py-3">
                                            <div className="space-y-1 text-xs text-muted">
                                                <span className={`inline-flex items-center rounded-full px-2.5 py-1 font-medium ring-1 ring-inset ${completionBadgeClass(sale.completion?.status)}`}>
                                                    {sale.completion?.status === 'completed' ? '已完成交易' : displayValue(sale.completion?.status_label)}
                                                </span>
                                                {sale.completion?.status === 'completed' && <p>完成時間：{displayValue(sale.completion?.completed_at)}</p>}
                                                {sale.completion?.status === 'completed' && <p>完成人員：{displayValue(sale.completion?.completed_by_name)}</p>}
                                            </div>
                                        </td>
                                        <td className="px-3 py-3">{formatNumber(sale.payment_summary?.receivable_amount)}</td>
                                        <td className="px-3 py-3">{formatNumber(sale.payment_summary?.received_amount)}</td>
                                        <td className="px-3 py-3">{formatNumber(sale.payment_summary?.receivable_balance)}</td>
                                        <td className="px-3 py-3">{formatDate(sale.payment_summary?.latest_payment_paid_at)}</td>
                                        <td className="px-3 py-3"><Link href={route('employee-system.receivables.show', sale.id)} className="text-accent underline underline-offset-2">查看 / 收款</Link></td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>
            </div>
        </DashboardLayout>
    );
}
