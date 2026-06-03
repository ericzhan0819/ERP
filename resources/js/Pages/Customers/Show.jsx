import React from 'react';
import { Link } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { formatDate, formatDateTime } from '@/utils/formatDateTime';

/**
 * 技術註解：詳情頁只讀顯示客戶主檔，敏感個資區塊完全依後端 can 旗標與 payload 存在性呈現。
 */
export default function CustomersShow({ auth, customer, customerStatuses = {}, can = {} }) {
    const displayValue = (value) => {
        if (value === null || value === undefined || value === '') return '—';
        return value;
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
                    <h1 className="text-xl font-semibold text-primary">客戶詳情</h1>
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

                <section className="rounded-2xl border border-default bg-surface p-4 text-secondary">
                    <h2 className="mb-3 text-sm font-semibold text-primary">備註與系統資訊</h2>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <p className="sm:col-span-2"><span className="text-muted">備註：</span>{displayValue(customer.notes)}</p>
                        <p><span className="text-muted">建立者：</span>{displayValue(customer.creator?.name)}</p>
                        <p><span className="text-muted">更新者：</span>{displayValue(customer.updater?.name)}</p>
                        <p><span className="text-muted">建立時間：</span>{formatDateTime(customer.created_at)}</p>
                        <p><span className="text-muted">更新時間：</span>{formatDateTime(customer.updated_at)}</p>
                    </div>
                </section>
            </div>
        </DashboardLayout>
    );
}

