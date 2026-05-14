import DashboardLayout from '@/Layouts/DashboardLayout';
import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

function PermissionDropdown({ value = [], options = [], disabled, onChange }) {
    const [open, setOpen] = useState(false);

    const toggle = (permission) => {
        if (value.includes(permission)) {
            onChange(value.filter((item) => item !== permission));
            return;
        }

        onChange([...value, permission]);
    };

    return (
        <div className="relative">
            <button
                type="button"
                disabled={disabled}
                onClick={() => setOpen((current) => !current)}
                className="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-left text-xs text-zinc-100 disabled:cursor-not-allowed disabled:opacity-40"
            >
                {value.length > 0 ? `已選 ${value.length} 個權限` : '選擇直接權限'}
            </button>

            {open && (
                <div className="absolute z-50 mt-2 max-h-56 w-full overflow-y-auto rounded-xl border border-white/10 bg-slate-950 p-3 shadow-xl">
                    {options.map((permission) => (
                        <label key={permission.name} className="flex items-center gap-2 py-1 text-xs text-zinc-200">
                            <input
                                type="checkbox"
                                checked={value.includes(permission.name)}
                                disabled={disabled}
                                onChange={() => toggle(permission.name)}
                            />
                            <div className="flex flex-col">
                                <span>{permission.label ?? permission.name}</span>

                                <span className="text-[10px] text-zinc-500">            
                                    {permission.name}
                                </span>
                            </div>
                        </label>
                    ))}
                </div>
            )}
        </div>
    );
}
function RoleSelect({ value, options, disabled, onChange }) {
    return (
        <select
            value={value}
            disabled={disabled}
            onChange={(event) => onChange(event.target.value)}
            className="w-full rounded-xl border border-white/10 bg-slate-950/70 px-3 py-2 text-xs text-zinc-100 outline-none transition focus:border-cyan-300/60 disabled:cursor-not-allowed disabled:opacity-40"
        >
            <option value="" disabled>
                請選擇角色
            </option>

            {options.map((role) => (
                <option key={role.name} value={role.name}>
                    {role.label ?? role.name}
                </option>
            ))}
        </select>
    );
}

function formatDateTime(value) {
    // 技術註解：後端提供 UTC 時間字串，前端交由瀏覽器以使用者本機時區呈現，不寫死任何地區時區。
    if (!value) {
        return '尚未登入';
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).format(new Date(value));
}

export default function StaffPermissionIndex({ users = [], roles = [], permissions = [], can = {} }) {
    const initialRows = useMemo(
        () => Object.fromEntries(users.map((user) => [user.id, {
            role: user.roles?.[0] ?? '',
            permissions: user.direct_permissions ?? [],
        }])),
        [users]
    );
    const [rows, setRows] = useState(initialRows);

    const updateRow = (userId, key, value) => {
        setRows((current) => ({ ...current, [userId]: { ...current[userId], [key]: value } }));
    };

    const patch = (userId, type) => {
        // 技術註解：儲存動作分流到角色與直接權限端點，後端仍以 middleware 與自我修改防護作最終授權。
        const isRoles = type === 'roles';
        const url = typeof route === 'function'
            ? route(isRoles ? 'employee-system.staff-permissions.roles.update' : 'employee-system.staff-permissions.permissions.update', userId)
            : `/employee-system/staff-permissions/${userId}/${isRoles ? 'roles' : 'permissions'}`;

        router.patch(
            url,
            isRoles
                ? { roles: rows[userId].role ? [rows[userId].role] : [] }
                : { permissions: rows[userId].permissions },
            { preserveScroll: true }
        );
    };

    return (
        <DashboardLayout title="員工權限管理">
            <section className="space-y-5">
                <div className="rounded-2xl border border-white/10 bg-white/[0.03] p-6 backdrop-blur-xl">
                    <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-cyan-300/70">Staff Permission Matrix</p>
                    <h1 className="mt-3 text-2xl font-semibold tracking-tight text-zinc-50">員工權限管理</h1>
                    <p className="mt-3 max-w-3xl text-sm leading-6 text-zinc-400">
                        以既有 Spatie RBAC 同步角色與直接權限；此頁不提供員工新增、基本資料編輯或刪除。
                    </p>
                </div>

                <div className="rounded-2xl border border-white/10 bg-slate-950/40 backdrop-blur-xl">
                    <div className="grid grid-cols-[1fr_1.1fr_0.7fr_0.8fr_1fr_1.2fr_1.4fr] border-b border-white/10 bg-white/[0.04] px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-400">
                        <div>姓名</div>
                        <div>Email</div>
                        <div>電話</div>
                        <div>狀態</div>
                        <div>最後登入</div>
                        <div>目前角色</div>
                        <div>直接權限</div>
                    </div>

                    {users.map((user) => (
                        <div key={user.id} className="relative grid grid-cols-[1fr_1.1fr_0.7fr_0.8fr_1fr_1.2fr_1.4fr] gap-4 border-b border-white/10 px-4 py-4 text-sm text-zinc-200 last:border-b-0">
                            <div className="font-semibold text-zinc-50">{user.name}</div>
                            <div className="text-zinc-400">{user.email}</div>
                            <div className="text-zinc-400">{user.phone ?? '—'}</div>
                            <div className={user.is_active ? 'font-semibold text-emerald-300' : 'font-semibold text-rose-300'}>{user.is_active ? 'Active' : 'Inactive'}</div>
                            <div className="text-zinc-400">{formatDateTime(user.last_login_at)}</div>
                            <div className="space-y-3">
                                <RoleSelect
                                    value={rows[user.id]?.role ?? ''}
                                    options={roles}
                                    disabled={!can.updateRole}
                                    onChange={(value) => updateRow(user.id, 'role', value)}
                                />
                                <button type="button" disabled={!can.updateRole} onClick={() => patch(user.id, 'roles')} className="rounded-full border border-cyan-300/30 px-4 py-2 text-xs font-semibold text-cyan-200 transition hover:bg-cyan-300/10 disabled:cursor-not-allowed disabled:opacity-40">
                                    儲存角色
                                </button>
                            </div>
                            <div className="space-y-3">
                                <PermissionDropdown
                                    value={rows[user.id]?.permissions ?? []}
                                    options={permissions}
                                    disabled={!can.updatePermission}
                                    onChange={(value) => updateRow(user.id, 'permissions', value)}
                                />
                                <button type="button" disabled={!can.updatePermission} onClick={() => patch(user.id, 'permissions')} className="rounded-full border border-violet-300/30 px-4 py-2 text-xs font-semibold text-violet-200 transition hover:bg-violet-300/10 disabled:cursor-not-allowed disabled:opacity-40">
                                    儲存直接權限
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            </section>
        </DashboardLayout>
    );
}
