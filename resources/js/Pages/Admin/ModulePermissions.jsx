import DashboardLayout from '@/Layouts/DashboardLayout';
import { sidebarItems } from '@/config/sidebar';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { useMemo, useState } from 'react';

const ROLE_OPTIONS = ['Admin', 'Manager', 'Staff'];

const panelClass = 'overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03] backdrop-blur-xl';
const headerLabelClass = 'text-[11px] font-semibold uppercase tracking-[0.24em] text-zinc-500';

function normalizeModule(item) {
    return {
        module_key: String(item.module_key ?? ''),
        module_name: String(item.module_name ?? item.module_key ?? ''),
        allowed_roles: Array.isArray(item.allowed_roles) ? item.allowed_roles : [],
        allowed_user_ids: Array.isArray(item.allowed_user_ids) ? item.allowed_user_ids.map((id) => Number(id)).filter(Number.isInteger) : [],
        enabled: Boolean(item.enabled),
    };
}

function flattenSidebarModules(items, collector = []) {
    items.forEach((item) => {
        // 技術註解：僅做前端 fallback 初始值，不修改 sidebar.ts 原始設定。
        collector.push({
            module_key: item.id,
            module_name: item.title,
            allowed_roles: Array.isArray(item.roles) ? item.roles : ['Admin', 'Manager', 'Staff'],
            allowed_user_ids: Array.isArray(item.users) ? item.users : [],
            enabled: true,
        });

        if (Array.isArray(item.children) && item.children.length > 0) {
            flattenSidebarModules(item.children, collector);
        }
    });

    return collector;
}

export default function ModulePermissions() {
    const { modulePermissions = [] } = usePage().props;
    const [status, setStatus] = useState({ type: '', message: '' });
    const initialRows = useMemo(() => {
        if (Array.isArray(modulePermissions) && modulePermissions.length > 0) {
            return modulePermissions.map(normalizeModule);
        }

        const fallbackRows = flattenSidebarModules(sidebarItems);
        return fallbackRows.map(normalizeModule);
    }, [modulePermissions]);

    const [rows, setRows] = useState(initialRows);
    const [isSaving, setIsSaving] = useState(false);

    const updateRow = (index, updater) => {
        setRows((prev) => prev.map((row, i) => (i === index ? updater(row) : row)));
    };

    const saveAll = async () => {
        setIsSaving(true);
        setStatus({ type: '', message: '' });

        try {
            // 技術註解：以 batch endpoint 一次送出所有模組設定，降低請求次數與狀態不一致風險。
            await axios.put(route('admin.module-permissions.batch-update'), {
                items: rows.map((row) => ({
                    module_key: row.module_key,
                    module_name: row.module_name,
                    allowed_roles: row.allowed_roles,
                    allowed_user_ids: row.allowed_user_ids,
                    enabled: row.enabled,
                })),
            });

            setStatus({ type: 'success', message: '模組權限已儲存。' });
        } catch (error) {
            setStatus({ type: 'error', message: error?.response?.data?.message ?? '儲存失敗，請稍後再試。' });
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <div className="space-y-4 md:space-y-6">
            <section className={panelClass}>
                <div className="border-b border-white/10 p-4 md:p-6">
                    <p className={headerLabelClass}>Admin Module Permission</p>
                    <h1 className="mt-2 text-lg font-semibold tracking-tight text-zinc-50">模組權限設定</h1>
                    <p className="mt-2 text-sm text-zinc-400">可設定角色白名單、指定使用者與啟用狀態。</p>
                </div>

                {status.message && (
                    <div className={`border-b px-4 py-3 text-sm md:px-6 ${status.type === 'success' ? 'border-emerald-300/30 bg-emerald-300/10 text-emerald-200' : 'border-rose-300/30 bg-rose-300/10 text-rose-200'}`}>
                        {status.message}
                    </div>
                )}

                <div className="overflow-x-auto px-4 pb-4 md:px-6 md:pb-6">
                    <table className="min-w-full">
                        <thead>
                            <tr className="border-b border-white/10 text-left">
                                <th className="py-3 pr-4 text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500">模組</th>
                                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500">允許角色</th>
                                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500">允許 user id</th>
                                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500">啟用</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/10">
                            {rows.map((row, index) => (
                                <tr key={row.module_key} className="transition-colors hover:bg-white/[0.02]">
                                    <td className="py-4 pr-4">
                                        <p className="text-sm font-semibold text-zinc-100">{row.module_name}</p>
                                        <p className="mt-1 text-xs text-zinc-500">{row.module_key}</p>
                                    </td>
                                    <td className="px-4 py-4">
                                        <div className="flex flex-wrap gap-3">
                                            {ROLE_OPTIONS.map((role) => {
                                                const checked = row.allowed_roles.includes(role);
                                                return (
                                                    <label key={`${row.module_key}-${role}`} className="flex items-center gap-2 text-xs text-zinc-300">
                                                        <input
                                                            type="checkbox"
                                                            checked={checked}
                                                            onChange={(event) => {
                                                                updateRow(index, (prevRow) => {
                                                                    const nextRoles = new Set(prevRow.allowed_roles);
                                                                    if (event.target.checked) nextRoles.add(role);
                                                                    else nextRoles.delete(role);
                                                                    return { ...prevRow, allowed_roles: Array.from(nextRoles) };
                                                                });
                                                            }}
                                                        />
                                                        <span>{role}</span>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    </td>
                                    <td className="px-4 py-4">
                                        <input
                                            type="text"
                                            value={row.allowed_user_ids.join(',')}
                                            onChange={(event) => {
                                                const parsed = event.target.value
                                                    .split(',')
                                                    .map((chunk) => Number(chunk.trim()))
                                                    .filter((value) => Number.isInteger(value) && value > 0);
                                                updateRow(index, (prevRow) => ({ ...prevRow, allowed_user_ids: parsed }));
                                            }}
                                            placeholder="例如：1,3,8"
                                            className="w-full rounded-xl border border-white/10 bg-[#0B1120] px-3 py-2 text-sm text-zinc-100"
                                        />
                                    </td>
                                    <td className="px-4 py-4">
                                        <label className="inline-flex items-center gap-2 text-xs text-zinc-300">
                                            <input
                                                type="checkbox"
                                                checked={row.enabled}
                                                onChange={(event) => {
                                                    updateRow(index, (prevRow) => ({ ...prevRow, enabled: event.target.checked }));
                                                }}
                                            />
                                            <span>{row.enabled ? '啟用' : '停用'}</span>
                                        </label>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="border-t border-white/10 p-4 md:p-6">
                    <button
                        type="button"
                        onClick={saveAll}
                        disabled={isSaving}
                        className="min-h-10 rounded-xl border border-cyan-300/30 bg-cyan-300/10 px-4 text-sm font-medium text-cyan-100 transition-colors hover:bg-cyan-300/20 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {isSaving ? '儲存中…' : '儲存設定'}
                    </button>
                </div>
            </section>
        </div>
    );
}

ModulePermissions.layout = (page) => <DashboardLayout title="Module Permissions">{page}</DashboardLayout>;

