import React from 'react';
import { Link, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { formatDateTime } from '@/utils/formatDateTime';

/**
 * 技術註解：客戶列表只顯示一般欄位，不呈現任何個資敏感欄位，避免列表頁成為批次外洩入口。
 */
export default function CustomersIndex({ auth, customers = { data: [], links: [] }, filters = {}, customerStatuses = {}, can = {} }) {
    const [q, setQ] = React.useState(filters.q ?? '');
    const [status, setStatus] = React.useState(filters.status ?? '');

    const handleSearch = (event) => {
        event.preventDefault();

        router.get(route('employee-system.customers.index'), { q, status }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleClear = () => {
        setQ('');
        setStatus('');
        router.get(route('employee-system.customers.index'), {}, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold text-primary">客戶列表</h1>
                    {can.create_customers && (
                        <Link href={route('employee-system.customers.create')} className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white">
                            新增客戶
                        </Link>
                    )}
                </div>

                <form onSubmit={handleSearch} className="rounded-2xl border border-default bg-surface p-4">
                    <div className="grid gap-3 md:grid-cols-3">
                        <input
                            type="text"
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                            placeholder="搜尋客戶編號 / 姓名 / 電話"
                            className="rounded-lg border border-default bg-surface px-3 py-2 text-sm text-primary placeholder:text-muted focus:border-accent focus:outline-none"
                        />
                        <select value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-lg border border-default bg-surface px-3 py-2 text-sm text-primary focus:border-accent focus:outline-none">
                            <option value="">全部狀態</option>
                            {Object.entries(customerStatuses).map(([value, label]) => (
                                <option key={value} value={value}>{label}</option>
                            ))}
                        </select>
                        <div className="flex items-center gap-2">
                            <button type="submit" className="rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white">查詢</button>
                            <button type="button" onClick={handleClear} className="rounded-lg border border-default px-4 py-2 text-sm font-medium text-secondary">清除篩選</button>
                        </div>
                    </div>
                </form>

                <div className="overflow-x-auto rounded-2xl border border-default bg-surface">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr className="border-b border-default text-left text-secondary">
                                <th className="px-4 py-3">客戶編號</th>
                                <th className="px-4 py-3">姓名</th>
                                <th className="px-4 py-3">電話</th>
                                <th className="px-4 py-3">狀態</th>
                                <th className="px-4 py-3">來源</th>
                                <th className="px-4 py-3">建立 / 更新時間</th>
                                <th className="px-4 py-3">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            {customers.data.map((customer) => (
                                <tr key={customer.id} className="border-b border-default/70 text-primary last:border-b-0">
                                    <td className="px-4 py-3">{customer.customer_number}</td>
                                    <td className="px-4 py-3">{customer.name}</td>
                                    <td className="px-4 py-3 text-secondary">{customer.phone ?? '—'}</td>
                                    <td className="px-4 py-3 text-secondary">{customerStatuses[customer.status] ?? customer.status}</td>
                                    <td className="px-4 py-3 text-secondary">{customer.source ?? '—'}</td>
                                    <td className="px-4 py-3 text-secondary">
                                        <div>{formatDateTime(customer.created_at)}</div>
                                        <div className="text-xs text-muted">{formatDateTime(customer.updated_at)}</div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-3">
                                            <Link href={route('employee-system.customers.show', customer.id)} className="text-accent underline decoration-1 underline-offset-2">查看</Link>
                                            {can.update_customers && (
                                                <Link href={route('employee-system.customers.edit', customer.id)} className="text-accent underline decoration-1 underline-offset-2">編輯</Link>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {customers.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-4 py-8 text-center text-muted">無符合條件的客戶資料</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {customers.links.map((link, index) => (
                        <Link
                            // 技術註解：pagination URL 由後端產生，前端不自行拼接查詢參數以避免狀態漂移。
                            key={`${link.url ?? 'null'}-${index}`}
                            href={link.url ?? '#'}
                            className={`rounded-md border px-3 py-1.5 text-sm ${link.active ? 'border-accent text-accent' : 'border-default text-secondary'} ${link.url === null ? 'pointer-events-none opacity-50' : ''}`}
                        >
                            {link.label.replace('&laquo; Previous', '上一頁').replace('Next &raquo;', '下一頁')}
                        </Link>
                    ))}
                </div>
            </div>
        </DashboardLayout>
    );
}

