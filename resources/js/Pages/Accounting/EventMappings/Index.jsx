import React from 'react';
import { Link, router } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * 技術註解：映射列表只呈現安全 allowlist，不顯示 company_id、branch_id、created_by、updated_by，避免 tenant 與 actor 原始欄位外洩。
 */
export default function AccountingEventMappingsIndex({ auth, mappings, filters = {}, supportedEventTypes = {}, mappingKeyOptions = {}, can = {} }) {
    const rows = mappings?.data ?? [];
    const links = mappings?.links ?? [];
    const paginationLabel = (label) => label.replace('&laquo;', '‹').replace('&raquo;', '›');
    const updateFilter = (key, value) => {
        router.get(route('employee-system.accounting.event-mappings.index'), {
            event_type: filters.event_type ?? 'vehicle_sale_completed',
            mapping_key: filters.mapping_key ?? '',
            is_active: filters.is_active ?? '',
            [key]: value,
        }, { preserveState: true, replace: true });
    };

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-5 p-4 md:p-6">
                <div className="flex flex-col gap-4 rounded-2xl border border-default bg-surface p-5 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">Accounting</p>
                        <h1 className="mt-2 text-2xl font-semibold text-primary">會計事件映射</h1>
                        <p className="mt-2 max-w-2xl text-sm text-secondary">設定會計事件轉傳票前置檢查使用的科目對應。</p>
                    </div>
                    <div className="flex flex-wrap gap-2 md:justify-end">
                        <Link href={route('employee-system.accounting.events.index')} className="inline-flex items-center justify-center rounded-lg border border-default bg-transparent px-4 py-2 text-sm font-semibold text-secondary transition hover:border-primary hover:text-primary">回會計事件</Link>
                        <Link href={route('employee-system.accounting.accounts.index')} className="inline-flex items-center justify-center rounded-lg border border-default bg-transparent px-4 py-2 text-sm font-semibold text-secondary transition hover:border-primary hover:text-primary">回會計科目</Link>
                        {can.create && (
                            <Link href={route('employee-system.accounting.event-mappings.create')} className="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90">新增映射</Link>
                        )}
                    </div>
                </div>

                <section className="rounded-2xl border border-default bg-surface p-4">
                    <div className="mb-4 flex flex-col gap-1">
                        <h2 className="text-sm font-semibold text-primary">映射篩選</h2>
                        <p className="text-xs text-muted">目前僅支援車輛交易完成事件的應收與銷貨收入映射。</p>
                    </div>
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <Select label="事件類型" value={filters.event_type ?? 'vehicle_sale_completed'} onChange={(value) => updateFilter('event_type', value)}>
                            {Object.entries(supportedEventTypes).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </Select>
                        <Select label="映射鍵" value={filters.mapping_key ?? ''} onChange={(value) => updateFilter('mapping_key', value)}>
                            <option value="">全部映射鍵</option>
                            {Object.entries(mappingKeyOptions).map(([value, option]) => <option key={value} value={value}>{option.label}</option>)}
                        </Select>
                        <Select label="啟用狀態" value={filters.is_active ?? ''} onChange={(value) => updateFilter('is_active', value)}>
                            <option value="">全部狀態</option>
                            <option value="1">啟用</option>
                            <option value="0">停用</option>
                        </Select>
                    </div>
                </section>

                <section className="overflow-x-auto rounded-2xl border border-default bg-surface">
                    {rows.length === 0 ? (
                        <div className="p-10 text-center">
                            <p className="text-sm font-medium text-primary">目前沒有符合條件的會計事件映射。</p>
                            {can.create && <p className="mt-2 text-sm text-muted">可新增公司預設或目前分店覆寫映射。</p>}
                        </div>
                    ) : (
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-[11px] uppercase tracking-[0.12em] text-muted dark:bg-slate-900/40">
                                <tr>
                                    <th className="px-4 py-3 font-semibold">事件類型</th>
                                    <th className="px-4 py-3 font-semibold">映射鍵</th>
                                    <th className="px-4 py-3 font-semibold">科目</th>
                                    <th className="px-4 py-3 font-semibold">科目類型</th>
                                    <th className="px-4 py-3 font-semibold">層級</th>
                                    <th className="px-4 py-3 font-semibold">狀態</th>
                                    <th className="px-4 py-3 font-semibold">操作人員</th>
                                    <th className="px-4 py-3 text-right font-semibold">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((mapping) => (
                                    <tr key={mapping.id} className="border-t border-default transition hover:bg-slate-50/70 dark:hover:bg-slate-900/30">
                                        <td className="whitespace-nowrap px-4 py-3 font-medium text-primary">{mapping.event_type_label}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-secondary">{mapping.mapping_key_label}</td>
                                        <td className="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold tracking-[0.08em] text-primary">{mapping.account ? `${mapping.account.code} - ${mapping.account.name}` : '—'}</td>
                                        <td className="whitespace-nowrap px-4 py-3"><Badge>{mapping.account?.type_label ?? '—'}</Badge></td>
                                        <td className="whitespace-nowrap px-4 py-3 text-secondary">{mapping.branch_scope_label}</td>
                                        <td className="whitespace-nowrap px-4 py-3"><span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${mapping.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300'}`}>{mapping.is_active ? '啟用' : '停用'}</span></td>
                                        <td className="whitespace-nowrap px-4 py-3 text-secondary">{mapping.operator_name ?? '—'}</td>
                                        <td className="whitespace-nowrap px-4 py-3 text-right">
                                            {can.update && <Link href={route('employee-system.accounting.event-mappings.edit', mapping.id)} className="inline-flex rounded-lg border border-default px-3 py-1.5 text-xs font-semibold text-primary transition hover:border-primary">編輯</Link>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </section>

                {links.length > 0 && (
                    <nav className="flex flex-wrap gap-2 rounded-2xl border border-default bg-surface p-3">
                        {links.map((link, index) => (
                            <Link key={`${link.label}-${index}`} href={link.url ?? '#'} preserveScroll className={`rounded-lg border border-default px-3 py-2 text-sm font-medium ${link.active ? 'bg-primary text-white' : 'bg-surface text-secondary hover:border-primary hover:text-primary'} ${!link.url ? 'pointer-events-none opacity-40' : ''}`}>{paginationLabel(link.label)}</Link>
                        ))}
                    </nav>
                )}
            </div>
        </DashboardLayout>
    );
}

function Select({ label, value, onChange, children }) {
    return <label className="space-y-1"><span className="text-xs font-medium text-secondary">{label}</span><select value={value} onChange={(event) => onChange(event.target.value)} className="w-full rounded-lg border border-default bg-transparent px-3 py-2 text-sm text-primary focus:outline-none focus:ring-2 focus:ring-accent">{children}</select></label>;
}

function Badge({ children }) {
    return <span className="inline-flex rounded-full border border-default bg-slate-50 px-2.5 py-1 text-xs font-medium text-secondary dark:bg-slate-900/40">{children}</span>;
}
