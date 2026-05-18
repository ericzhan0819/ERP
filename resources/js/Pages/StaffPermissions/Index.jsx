import DashboardLayout from '@/Layouts/DashboardLayout';
import Modal from '@/Components/Modal';
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
                className="w-full rounded-xl border border-default bg-surface px-3 py-2 text-left text-xs text-primary disabled:cursor-not-allowed disabled:opacity-40"
            >
                {value.length > 0 ? `已選 ${value.length} 個權限` : '選擇直接權限'}
            </button>

            {open && (
                <div className="absolute z-50 mt-2 max-h-56 w-full overflow-y-auto rounded-xl border border-default bg-surface p-3 shadow-xl">
                    {options.map((permission) => (
                        <label key={permission.name} className="flex items-center gap-2 py-1 text-xs text-secondary">
                            <input
                                type="checkbox"
                                checked={value.includes(permission.name)}
                                disabled={disabled}
                                onChange={() => toggle(permission.name)}
                            />
                            <div className="flex flex-col">
                                <span>{permission.label ?? permission.name}</span>

                                <span className="text-[10px] text-muted">
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
            className="w-full rounded-xl border border-default bg-surface px-3 py-2 text-xs text-primary outline-none transition focus:border-active focus:ring-2 focus:ring-focus disabled:cursor-not-allowed disabled:opacity-40"
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

export default function StaffPermissionIndex({ roles = [], permissionMatrix = {}, actionLabels = {}, capabilities = {}, rolePermissionMap = {} }) {
    const roleOptions = useMemo(() => roles, [roles]);
    const [selectedRoleId, setSelectedRoleId] = useState(roleOptions[0]?.id ? String(roleOptions[0].id) : '');
    const [selectedPermissions, setSelectedPermissions] = useState(rolePermissionMap[selectedRoleId] ?? []);
    const [initialPermissions, setInitialPermissions] = useState(rolePermissionMap[selectedRoleId] ?? []);
    const [submitting, setSubmitting] = useState(false);
    const [flash, setFlash] = useState(null);
    const [editingRoleId, setEditingRoleId] = useState('');
    const [editingRoleName, setEditingRoleName] = useState('');
    const [editingRoleLabel, setEditingRoleLabel] = useState('');
    const [initialEditingRoleName, setInitialEditingRoleName] = useState('');
    const [initialEditingRoleLabel, setInitialEditingRoleLabel] = useState('');
    const [newRoleName, setNewRoleName] = useState('');
    const [newRoleLabel, setNewRoleLabel] = useState('');
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [deleteRoleTarget, setDeleteRoleTarget] = useState(null);

    const matrixEntries = Object.entries(permissionMatrix);
    const selectedPermissionSet = useMemo(() => new Set(selectedPermissions), [selectedPermissions]);
    const dirty = JSON.stringify([...selectedPermissions].sort()) !== JSON.stringify([...initialPermissions].sort());
    const roleInfoDirty = editingRoleName.trim() !== initialEditingRoleName || editingRoleLabel.trim() !== initialEditingRoleLabel;

    const switchRole = (roleId) => {
        setSelectedRoleId(roleId);
        const next = rolePermissionMap[roleId] ?? [];
        setSelectedPermissions(next);
        setInitialPermissions(next);
        setFlash(null);
    };

    const togglePermission = (permission, exists) => {
        if (!exists || !capabilities.canUpdatePermissions || editingRoleId !== selectedRoleId) return;
        setSelectedPermissions((current) => current.includes(permission)
            ? current.filter((item) => item !== permission)
            : [...current, permission]);
    };

    const toggleModuleAll = (moduleKey, checked) => {
        if (editingRoleId !== selectedRoleId || !capabilities.canUpdatePermissions) return;
        const moduleActions = permissionMatrix[moduleKey]?.actions ?? {};
        const existingPermissions = Object.values(moduleActions)
            .filter((item) => item.exists)
            .map((item) => item.permission);

        setSelectedPermissions((current) => checked
            ? Array.from(new Set([...current, ...existingPermissions]))
            : current.filter((name) => !existingPermissions.includes(name)));
    };

    const submit = () => {
        if (!selectedRoleId || !dirty || submitting || !capabilities.canUpdatePermissions || editingRoleId !== selectedRoleId) return;

        setSubmitting(true);
        setFlash(null);
        const url = typeof route === 'function'
            ? route('employee-system.staff-permissions.roles.permissions.update', selectedRoleId)
            : `/employee-system/staff-permissions/roles/${selectedRoleId}/permissions`;

        router.patch(url, { permissions: selectedPermissions }, {
            preserveScroll: true,
            onSuccess: () => {
                setInitialPermissions(selectedPermissions);
                setEditingRoleId('');
                setFlash({ type: 'success', message: '角色權限更新成功' });
            },
            onError: () => {
                setFlash({ type: 'error', message: '角色權限更新失敗' });
            },
            onFinish: () => setSubmitting(false),
        });
    };

    const submitUpdateRoleInfo = () => {
        if (!editingRoleId || submitting || !capabilities.canUpdatePermissions || !roleInfoDirty) return;

        setSubmitting(true);
        setFlash(null);

        router.patch(route('employee-system.staff-permissions.roles.update.meta', editingRoleId), {
            name: editingRoleName.trim(),
            label: editingRoleLabel.trim() || null,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setInitialEditingRoleName(editingRoleName.trim());
                setInitialEditingRoleLabel(editingRoleLabel.trim());
                setFlash({ type: 'success', message: '角色資料更新成功' });
            },
            onError: () => setFlash({ type: 'error', message: '角色資料更新失敗' }),
            onFinish: () => setSubmitting(false),
        });
    };

    const submitCreateRole = () => {
        if (!newRoleName.trim() || submitting) return;
        setSubmitting(true);
        router.post(route('employee-system.staff-permissions.roles.store'), {
            name: newRoleName.trim(),
            label: newRoleLabel.trim() || null,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setNewRoleName('');
                setNewRoleLabel('');
                setCreateModalOpen(false);
                setFlash({ type: 'success', message: '角色已新增' });
            },
            onError: () => setFlash({ type: 'error', message: '角色新增失敗' }),
            onFinish: () => setSubmitting(false),
        });
    };

    const submitDeleteRole = (role) => {
        if (submitting || role.is_system_role) return;
        setSubmitting(true);
        router.delete(route('employee-system.staff-permissions.roles.destroy', role.id), {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteRoleTarget(null);
                setFlash({ type: 'success', message: '角色已刪除' });
            },
            onError: () => setFlash({ type: 'error', message: '角色刪除失敗' }),
            onFinish: () => setSubmitting(false),
        });
    };

    const openEditModal = (roleId) => {
        // 技術註解：開啟彈窗時同步目標角色權限快照，避免沿用上一個角色的未儲存狀態造成誤改。
        switchRole(roleId);
        const role = roleOptions.find((item) => String(item.id) === roleId);
        setEditingRoleName(role?.name ?? '');
        setEditingRoleLabel(role?.label ?? '');
        setInitialEditingRoleName(role?.name ?? '');
        setInitialEditingRoleLabel(role?.label ?? '');
        setEditingRoleId(roleId);
        setEditModalOpen(true);
    };

    const closeEditModal = () => {
        setEditModalOpen(false);
        setEditingRoleId('');
        setEditingRoleName('');
        setEditingRoleLabel('');
        setInitialEditingRoleName('');
        setInitialEditingRoleLabel('');
        const next = rolePermissionMap[selectedRoleId] ?? [];
        setSelectedPermissions(next);
        setInitialPermissions(next);
    };

    return (
        <DashboardLayout title="員工權限管理">
            <section className="space-y-5">
                <div className="rounded-2xl border border-default bg-surface/80 p-6 backdrop-blur-xl">
                    <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-accent/80">Role Permission Matrix</p>
                    <h1 className="mt-3 text-2xl font-semibold tracking-tight text-primary">角色權限管理</h1>
                    <p className="mt-3 max-w-3xl text-sm leading-6 text-secondary">
                        角色權限矩陣採 module 分組，僅作為後端授權管理 UI，實際安全檢查仍由後端執行。
                    </p>
                </div>

                <div className="grid grid-cols-12 gap-5">
                    <div className="col-span-12 rounded-2xl border border-default bg-surface p-4">
                        <h2 className="mb-3 text-sm font-semibold text-primary">角色列表</h2>
                        <div className="mb-3 rounded-xl border border-default p-3">
                            <button type="button" onClick={() => setCreateModalOpen(true)} disabled={submitting || !capabilities.canUpdatePermissions} className="w-full rounded-lg border border-active bg-accent-subtle px-3 py-2 text-xs text-accent disabled:opacity-40">新增角色</button>
                        </div>
                        <div className="space-y-2">
                            {roleOptions.map((role) => (
                                <div key={role.id} className={`w-full rounded-xl border p-3 ${selectedRoleId === String(role.id) ? 'border-active bg-active' : 'border-default bg-surface'}`}>
                                 <button type="button" onClick={() => switchRole(String(role.id))} className="w-full text-left">
                                     <p className="text-sm font-semibold text-primary">{role.label ?? role.name}</p>
                                     <p className="text-xs text-secondary">{role.name}</p>
                                     <p className="mt-1 text-[11px] text-muted">系統角色：{role.is_system_role ? '是' : '否'}｜使用者：{role.users_count}｜權限：{role.permissions_count}</p>
                                 </button>
                                <div className="mt-2 flex gap-2">
                                     <button type="button" onClick={() => openEditModal(String(role.id))} disabled={!capabilities.canUpdatePermissions} className="rounded border border-default px-2 py-1 text-[11px] text-secondary disabled:opacity-40">編輯</button>
                                     <button type="button" onClick={() => setDeleteRoleTarget(role)} disabled={!capabilities.canUpdatePermissions || role.is_system_role} className="rounded border border-rose-300/40 px-2 py-1 text-[11px] text-rose-500 disabled:opacity-40">刪除</button>
                                </div>
                                </div>
                            ))}
                        </div>
                    </div>

                </div>

                <Modal show={createModalOpen} maxWidth="md" onClose={() => setCreateModalOpen(false)}>
                    <div className="bg-elevated p-5">
                        <h3 className="text-sm font-semibold text-primary">新增角色</h3>
                        <div className="mt-3 space-y-2">
                            <input value={newRoleLabel} onChange={(e) => setNewRoleLabel(e.target.value)} placeholder="角色名稱" className="w-full rounded-lg border border-default bg-surface px-2 py-2 text-xs text-primary" />
                            <input value={newRoleName} onChange={(e) => setNewRoleName(e.target.value)} placeholder="角色代碼" className="w-full rounded-lg border border-default bg-surface px-2 py-2 text-xs text-primary" />
                        </div>
                        <div className="mt-4 flex justify-end gap-2">
                            <button type="button" onClick={() => setCreateModalOpen(false)} className="rounded border border-default px-3 py-2 text-xs text-secondary">取消</button>
                            <button type="button" onClick={submitCreateRole} disabled={submitting || !capabilities.canUpdatePermissions || !newRoleName.trim()} className="rounded border border-active bg-accent-subtle px-3 py-2 text-xs text-accent disabled:opacity-40">建立</button>
                        </div>
                    </div>
                </Modal>

                <Modal show={editModalOpen} maxWidth="2xl" onClose={closeEditModal}>
                    <div className="bg-elevated p-5">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-sm font-semibold text-primary">編輯角色權限</h3>
                            <button type="button" onClick={submit} disabled={!dirty || submitting || !capabilities.canUpdatePermissions || editingRoleId !== selectedRoleId} className="rounded-full border border-active bg-accent-subtle px-4 py-2 text-xs font-semibold text-accent disabled:cursor-not-allowed disabled:opacity-40">
                                {submitting ? '儲存中...' : '儲存角色權限'}
                            </button>
                        </div>
                        {flash && <p className={`mb-3 text-xs ${flash.type === 'success' ? 'text-emerald-300' : 'text-rose-300'}`}>{flash.message}</p>}
                        <div className="mb-4 space-y-2 rounded-xl border border-default p-3">
                            <input
                                value={editingRoleLabel}
                                onChange={(e) => setEditingRoleLabel(e.target.value)}
                                placeholder="角色名稱"
                                className="w-full rounded-lg border border-default bg-surface px-2 py-2 text-xs text-primary"
                            />
                            <input
                                value={editingRoleName}
                                onChange={(e) => setEditingRoleName(e.target.value)}
                                placeholder="角色代碼"
                                className="w-full rounded-lg border border-default bg-surface px-2 py-2 text-xs text-primary"
                            />
                            <div className="flex justify-end">
                                <button type="button" onClick={submitUpdateRoleInfo} disabled={!roleInfoDirty || submitting || !capabilities.canUpdatePermissions} className="rounded border border-active bg-accent-subtle px-3 py-2 text-xs text-accent disabled:opacity-40">儲存角色資料</button>
                            </div>
                        </div>
                        <div className="space-y-3">
                            {matrixEntries.map(([moduleKey, moduleData]) => {
                                const modulePermissions = Object.values(moduleData.actions)
                                    .filter((item) => item.exists)
                                    .map((item) => item.permission);
                                const allChecked = modulePermissions.length > 0
                                    && modulePermissions.every((permissionName) => selectedPermissionSet.has(permissionName));

                                return (
                                    <div key={`modal-${moduleKey}`} className="rounded-xl border border-default p-3">
                                        <div className="mb-2 flex items-center justify-between">
                                            <p className="text-sm font-semibold text-primary">{moduleData.label}</p>
                                            <label className="text-xs text-secondary">
                                                <input type="checkbox" checked={allChecked} onChange={(e) => toggleModuleAll(moduleKey, e.target.checked)} disabled={!capabilities.canUpdatePermissions || editingRoleId !== selectedRoleId} className="mr-2" />
                                                全選/取消
                                            </label>
                                        </div>
                                        <div className="grid grid-cols-4 gap-2">
                                            {Object.entries(moduleData.actions).map(([actionKey, actionData]) => (
                                                <label key={`modal-${moduleKey}-${actionKey}`} className="rounded-lg border border-default px-2 py-2 text-xs text-secondary">
                                                    <input
                                                        type="checkbox"
                                                        className="mr-2"
                                                        checked={selectedPermissionSet.has(actionData.permission)}
                                                        disabled={!capabilities.canUpdatePermissions || editingRoleId !== selectedRoleId}
                                                        onChange={() => togglePermission(actionData.permission, true)}
                                                    />
                                                    {actionLabels[actionKey] ?? actionKey}
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        <div className="mt-4 flex justify-end">
                            <button type="button" onClick={closeEditModal} className="rounded border border-default px-3 py-2 text-xs text-secondary">關閉</button>
                        </div>
                    </div>
                </Modal>

                <Modal show={Boolean(deleteRoleTarget)} maxWidth="md" onClose={() => setDeleteRoleTarget(null)}>
                    <div className="bg-elevated p-5">
                        <h3 className="text-sm font-semibold text-primary">確認刪除角色</h3>
                        <p className="mt-2 text-xs text-muted">你即將刪除「{deleteRoleTarget?.label ?? deleteRoleTarget?.name}」，此操作無法復原。</p>
                        <div className="mt-4 flex justify-end gap-2">
                            <button type="button" onClick={() => setDeleteRoleTarget(null)} className="rounded border border-default px-3 py-2 text-xs text-secondary">取消</button>
                            <button type="button" onClick={() => submitDeleteRole(deleteRoleTarget)} disabled={submitting || !deleteRoleTarget} className="rounded border border-rose-300/30 px-3 py-2 text-xs text-rose-200 disabled:opacity-40">確認刪除</button>
                        </div>
                    </div>
                </Modal>
            </section>
        </DashboardLayout>
    );
}
