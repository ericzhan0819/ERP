import DashboardLayout from '@/Layouts/DashboardLayout';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';

const panelClass = 'overflow-hidden rounded-2xl border border-white/10 bg-white/[0.03] backdrop-blur-xl';
const headerLabelClass = 'text-[11px] font-semibold uppercase tracking-[0.24em] text-zinc-500';

function getInitials(name = '') {
    return name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase() || 'U';
}

function RoleBadge({ children }) {
    return <span className="rounded-full bg-cyan-300/10 px-2.5 py-1 text-xs font-medium text-cyan-100">{children}</span>;
}

export default function StaffManagementIndex({ staff = [], permissionMatrix = [], roles = [], assignablePermissions = [] }) {
    const [savingUserId, setSavingUserId] = useState(null);
    const [flashMessage, setFlashMessage] = useState('');

    const saveMember = async (memberId, payload) => {
        setSavingUserId(memberId);
        try {
            await axios.patch(route('staff-management.update', memberId), payload);
            setFlashMessage('員工資料已更新。');
            router.reload({ only: ['staff'], preserveScroll: true });
        } finally {
            setSavingUserId(null);
        }
    };
    const staffCount = staff.length;
    const roleCount = new Set(staff.flatMap((member) => member.roles ?? [])).size;
    const permissionCount = permissionMatrix.reduce((total, moduleData) => total + (moduleData.permissions ?? []).length, 0);

    return (
        <div className="space-y-4 md:space-y-6">
            <section className="grid grid-cols-1 gap-4 md:grid-cols-3 md:gap-6">
                {[
                    ['員工總數', staffCount, '可管理帳號'],
                    ['角色數', roleCount, '目前已指派角色'],
                    ['權限節點', permissionCount, '後端同步矩陣'],
                ].map(([label, value, hint]) => (
                    <article key={label} className="rounded-2xl border border-white/10 bg-white/[0.03] p-5 backdrop-blur-xl">
                        <p className={headerLabelClass}>{label}</p>
                        <p className="mt-3 text-3xl font-semibold tracking-tight text-zinc-50">{value}</p>
                        <p className="mt-2 text-sm text-zinc-500">{hint}</p>
                    </article>
                ))}
            </section>

            <section className={panelClass}>
                {flashMessage && <div className="border-b border-emerald-300/30 bg-emerald-300/10 px-4 py-3 text-sm text-emerald-200 md:px-6">{flashMessage}</div>}
                <div className="flex flex-col gap-3 border-b border-white/10 p-4 sm:flex-row sm:items-center sm:justify-between md:p-6">
                    <div>
                        <p className={headerLabelClass}>Staff Directory</p>
                        <h2 className="mt-2 text-lg font-semibold tracking-tight text-zinc-50">員工列表</h2>
                    </div>
                    <button type="button" className="min-h-10 rounded-xl border border-white/10 bg-white/[0.02] px-4 text-sm font-medium text-zinc-300 transition-colors hover:border-cyan-300/30 hover:text-cyan-100">
                        篩選
                    </button>
                </div>

                <div className="overflow-x-auto px-4 pb-4 md:px-6 md:pb-5">
                    <table className="min-w-full">
                        <thead>
                            <tr className="border-b border-white/10 text-left">
                                <th className="py-3 pr-4 text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500">成員</th>
                                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500">階級</th>
                                <th className="px-4 py-3 text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500">功能開關</th>
                                <th className="py-3 pl-4 text-right text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500">操作</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/10">
                            {staff.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-4 py-10 text-center text-sm text-zinc-400">
                                        目前沒有員工資料。
                                    </td>
                                </tr>
                            ) : (
                                staff.map((member) => (
                                    <tr key={member.id} className="transition-colors hover:bg-white/[0.02]">
                                        <td className="py-4 pr-4">
                                            <div className="flex items-center gap-3">
                                                <div className="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-cyan-300/20 bg-cyan-300/10 text-sm font-semibold text-cyan-100">
                                                    {getInitials(member.name)}
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-semibold text-zinc-100">{member.name}</p>
                                                    <p className="truncate text-xs text-zinc-500">{member.email}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-4">
                                            <select
                                                defaultValue={(member.roles ?? [])[0] ?? ''}
                                                className="w-full rounded-xl border border-white/10 bg-[#0B1120] px-3 py-2 text-sm text-zinc-100"
                                                onChange={(event) => {
                                                    saveMember(member.id, {
                                                        role: event.target.value,
                                                        permissions: member.permissions ?? [],
                                                    });
                                                }}
                                            >
                                                {roles.map((role) => (
                                                    <option key={role} value={role}>{role}</option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="px-4 py-4">
                                            <div className="grid grid-cols-1 gap-2">
                                                {assignablePermissions.map((permission) => {
                                                    const checked = (member.permissions ?? []).includes(permission);
                                                    return (
                                                        <label key={permission} className="flex items-center gap-2 text-xs text-zinc-300">
                                                            <input
                                                                type="checkbox"
                                                                defaultChecked={checked}
                                                                onChange={(event) => {
                                                                    const current = new Set(member.permissions ?? []);
                                                                    if (event.target.checked) current.add(permission);
                                                                    else current.delete(permission);
                                                                    saveMember(member.id, {
                                                                        role: (member.roles ?? [])[0] ?? 'Staff',
                                                                        permissions: Array.from(current),
                                                                    });
                                                                }}
                                                            />
                                                            <span>{permission}</span>
                                                        </label>
                                                    );
                                                })}
                                            </div>
                                        </td>
                                        <td className="py-4 pl-4 text-right text-xs text-zinc-400">
                                            {savingUserId === member.id ? '儲存中…' : '—'}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className={panelClass}>
                <div className="border-b border-white/10 p-4 md:p-6">
                    <p className={headerLabelClass}>Permission Matrix</p>
                    <h2 className="mt-2 text-lg font-semibold tracking-tight text-zinc-50">權限矩陣</h2>
                </div>

                {permissionMatrix.length === 0 ? (
                    <div className="p-4 text-sm text-zinc-400 md:p-6">目前尚無模塊/權限資料，待系統建立權限後將自動顯示於此矩陣。</div>
                ) : (
                    <div className="grid grid-cols-1 gap-4 p-4 md:grid-cols-2 md:p-6 xl:grid-cols-3">
                        {permissionMatrix.map((moduleData) => (
                            <article key={moduleData.module} className="rounded-2xl border border-white/10 bg-white/[0.02] p-4">
                                <div className="mb-4 flex items-center justify-between gap-3">
                                    <h3 className="text-xs font-semibold uppercase tracking-[0.22em] text-zinc-300">{moduleData.module}</h3>
                                    <span className="rounded-full bg-white/[0.04] px-2.5 py-1 text-xs text-zinc-500">{(moduleData.permissions ?? []).length}</span>
                                </div>
                                <div className="space-y-2">
                                    {(moduleData.permissions ?? []).map((permissionName) => (
                                        <label key={permissionName} className="flex min-h-10 items-center gap-3 rounded-xl border border-white/10 bg-[#050816]/25 px-3 text-sm text-zinc-300">
                                            <input type="checkbox" disabled className="rounded border-white/20 bg-transparent text-cyan-300 focus:ring-cyan-300/20" />
                                            <span className="truncate">{permissionName}</span>
                                        </label>
                                    ))}
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </section>
        </div>
    );
}

StaffManagementIndex.layout = (page) => <DashboardLayout title="Staff Management">{page}</DashboardLayout>;
