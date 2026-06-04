import React from 'react';
import { Link } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { formatDate, formatDateTime } from '@/utils/formatDateTime';

/**
 * 技術註解：詳情頁只讀顯示客戶主檔，敏感個資區塊完全依後端 can 旗標與 payload 存在性呈現。
 */
export default function CustomersShow({ auth, customer, customerTransactions = [], customerStatuses = {}, can = {} }) {
    const displayValue = (value) => {
        if (value === null || value === undefined || value === '') return '—';
        return value;
    };

    const displayDateTime = (value) => {
        const formatted = formatDateTime(value);
        return formatted === '-' ? '—' : formatted;
    };

    const displayMoney = (value) => {
        if (value === null || value === undefined || value === '') return '—';
        const amount = Number(value);
        if (Number.isNaN(amount)) return value;
        return amount.toLocaleString('zh-TW', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    };

    const statusBadgeClass = (value) => {
        const classes = {
            lead: 'border-amber-200 bg-amber-50 text-amber-700',
            active: 'border-emerald-200 bg-emerald-50 text-emerald-700',
            archived: 'border-slate-200 bg-slate-50 text-slate-600',
        };

        return classes[value] ?? 'border-default bg-surface-muted text-secondary';
    };

    const rows = [
        { label: '客戶編號', value: customer.customer_number },
        { label: '姓名', value: customer.name },
        { label: '電話', value: customer.phone },
        { label: '第二電話', value: customer.secondary_phone },
        { label: 'Email', value: customer.email },
        { label: 'LINE ID', value: customer.line_id },
        { label: '狀態', value: customerStatuses[customer.status] ?? customer.status },
        { label: '來源', value: customer.source },
    ];

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-xl font-semibold text-primary">客戶詳情</h1>
                            <span className="text-sm font-medium text-secondary">{displayValue(customer.customer_number)}</span>
                            <span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-medium ${statusBadgeClass(customer.status)}`}>
                                {customerStatuses[customer.status] ?? customer.status}
                            </span>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href={route('employee-system.customers.index')} className="rounded-lg border border-default px-3 py-2 text-sm font-medium text-secondary">返回列表</Link>
                        {can.update_customers && (
                            <Link href={route('employee-system.customers.edit', customer.id)} className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white">編輯客戶</Link>
                        )}
                    </div>
                </div>

                <section className="rounded-2xl border border-default bg-surface p-4 text-secondary">
                    <h2 className="mb-3 text-sm font-semibold text-primary">客戶基本資訊</h2>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {rows.map((row) => (
                            <p key={row.label}><span className="text-muted">{row.label}：</span>{displayValue(row.value)}</p>
                        ))}
                    </div>
                </section>

                {can.view_customer_sensitive && (
                    <section className="rounded-2xl border border-default bg-surface p-4 text-secondary">
                        <h2 className="mb-3 text-sm font-semibold text-primary">敏感個資</h2>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <p><span className="text-muted">身分證字號：</span>{displayValue(customer.id_number)}</p>
                            <p><span className="text-muted">生日：</span>{formatDate(customer.birthday)}</p>
                            <p><span className="text-muted">地址：</span>{displayValue(customer.address)}</p>
                        </div>
                    </section>
                )}

                {can.view_customer_transactions && (
                    <section className="rounded-2xl border border-default bg-surface p-4 text-secondary">
                        <div className="mb-3 flex items-center justify-between gap-2">
                            <h2 className="text-sm font-semibold text-primary">客戶交易紀錄</h2>
                            <span className="text-xs text-muted">最近 20 筆</span>
                        </div>

                        {customerTransactions.length === 0 ? (
                            <p className="rounded-xl border border-dashed border-default bg-surface-muted px-4 py-5 text-sm text-muted">目前沒有關聯交易紀錄。</p>
                        ) : (
                            <div className="grid grid-cols-1 gap-3 xl:grid-cols-2">
                                {customerTransactions.map((transaction) => (
                                    <article key={transaction.id} className="rounded-xl border border-default bg-white p-4 shadow-sm">
                                        <div className="flex flex-wrap items-start justify-between gap-3 border-b border-default pb-3">
                                            <div>
                                                <p className="text-xs uppercase tracking-[0.18em] text-muted">購車紀錄</p>
                                                <h3 className="mt-1 text-base font-semibold text-primary">
                                                    {displayValue(transaction.vehicle?.stock_number)} · {displayValue(transaction.vehicle?.brand)} {displayValue(transaction.vehicle?.model)}
                                                </h3>
                                                <p className="mt-1 text-sm text-muted">車牌：{displayValue(transaction.vehicle?.license_plate)}</p>
                                            </div>
                                            <span className="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700">
                                                {displayValue(transaction.sale_status_label)}
                                            </span>
                                        </div>

                                        <div className="mt-3 grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                                            <p><span className="block text-xs text-muted">成交價</span><span className="font-medium text-primary">{displayMoney(transaction.sale_price)}</span></p>
                                            <p><span className="block text-xs text-muted">成交日</span><span className="font-medium text-primary">{formatDate(transaction.sold_at)}</span></p>
                                            <p className="md:col-span-2"><span className="block text-xs text-muted">業務</span><span className="font-medium text-primary">{displayValue(transaction.salesperson_name)}</span></p>
                                        </div>

                                        {transaction.receivable_summary && (
                                            <div className="mt-4 rounded-xl bg-surface-muted p-3">
                                                <div className="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                                                    <p><span className="block text-xs text-muted">應收</span><span className="font-medium text-primary">{displayMoney(transaction.receivable_summary.receivable_amount)}</span></p>
                                                    <p><span className="block text-xs text-muted">已收</span><span className="font-medium text-primary">{displayMoney(transaction.receivable_summary.received_amount)}</span></p>
                                                    <p><span className="block text-xs text-muted">未收</span><span className="font-medium text-primary">{displayMoney(transaction.receivable_summary.receivable_balance)}</span></p>
                                                    <p><span className="block text-xs text-muted">收款狀態</span><span className="font-medium text-primary">{displayValue(transaction.receivable_summary.receivable_status_label)}</span></p>
                                                    <p><span className="block text-xs text-muted">有效收款筆數</span><span className="font-medium text-primary">{displayValue(transaction.receivable_summary.received_payment_count)}</span></p>
                                                    <p><span className="block text-xs text-muted">收款紀錄筆數</span><span className="font-medium text-primary">{displayValue(transaction.receivable_summary.payment_record_count)}</span></p>
                                                </div>
                                            </div>
                                        )}

                                        <div className="mt-4 flex flex-wrap gap-2">
                                            {transaction.links?.vehicle_show_url && (
                                                <Link href={transaction.links.vehicle_show_url} className="rounded-lg border border-default px-3 py-2 text-sm font-medium text-secondary">前往車輛</Link>
                                            )}
                                            {transaction.links?.receivable_show_url && can.view_customer_transaction_receivables && (
                                                <Link href={transaction.links.receivable_show_url} className="rounded-lg bg-accent px-3 py-2 text-sm font-medium text-white">前往收款管理</Link>
                                            )}
                                        </div>
                                    </article>
                                ))}
                            </div>
                        )}
                    </section>
                )}

                <section className="rounded-2xl border border-default bg-surface p-4 text-secondary">
                    <h2 className="mb-3 text-sm font-semibold text-primary">備註與系統資訊</h2>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <p className="sm:col-span-2"><span className="text-muted">備註：</span>{displayValue(customer.notes)}</p>
                        <p><span className="text-muted">建立者：</span>{displayValue(customer.creator?.name)}</p>
                        <p><span className="text-muted">更新者：</span>{displayValue(customer.updater?.name)}</p>
                        <p><span className="text-muted">建立時間：</span>{displayDateTime(customer.created_at)}</p>
                        <p><span className="text-muted">更新時間：</span>{displayDateTime(customer.updated_at)}</p>
                    </div>
                </section>
            </div>
        </DashboardLayout>
    );
}
