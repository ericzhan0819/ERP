import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Header({ user, sidebarCollapsed = false, sidebarPinned = false, onToggleSidebar, onOpenMobileSidebar }) {
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const { post } = useForm();
    const displayName = user?.username || user?.name || user?.email || '';

    const handleLogout = () => {
        // 維持既有 Laravel/Inertia 登出流程，僅由新下拉選單觸發 POST logout。
        post(route('logout'));
    };

    return (
        <header className="sticky top-0 z-40 border-b border-white/10 bg-[#0B1120]/80 backdrop-blur-xl">
            {/* 桌面版高度與側欄品牌區一致，確保上下分線落在同一水平基準。 */}
            <div className="flex h-[88px] items-center justify-between px-4 sm:px-6 lg:px-8">
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        onClick={onToggleSidebar}
                        className="hidden rounded-md border border-white/15 p-2.5 text-zinc-300 transition-colors duration-150 active:scale-[0.98] hover:border-cyan-300/40 hover:text-cyan-100 lg:inline-flex"
                        aria-label={sidebarPinned ? 'Unpin sidebar' : 'Pin sidebar'}
                        aria-pressed={sidebarPinned}
                    >
                        <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                            <path d={sidebarCollapsed ? 'M9 6l6 6-6 6' : 'M15 6l-6 6 6 6'} strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                    </button>

                    <button
                        type="button"
                        onClick={onOpenMobileSidebar}
                        className="rounded-md border border-white/15 p-2.5 text-zinc-300 active:scale-[0.98] lg:hidden"
                        aria-label="Open sidebar"
                    >
                        <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                            <path d="M4 7h16M4 12h16M4 17h16" strokeLinecap="round" />
                        </svg>
                    </button>

                    <div>
                        <p className="text-[10px] font-semibold uppercase tracking-[0.32em] text-cyan-300/70">Operations Center</p>
                        <h1 className="text-sm font-semibold tracking-wide text-zinc-100">Dashboard</h1>
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    {/* 通知區預留：後續可直接接通知列表元件 */}
                    <button
                        type="button"
                        className="rounded-md border border-white/15 px-3 py-2 text-xs tracking-wide text-zinc-300 active:scale-[0.98]"
                    >
                        通知
                    </button>

                    <div className="relative hidden lg:block">
                        <button
                            type="button"
                            onClick={() => setUserMenuOpen((open) => !open)}
                            className="flex min-h-11 items-center gap-3 rounded-xl border border-white/10 bg-white/[0.02] px-3 py-2 text-left transition-colors active:scale-[0.98] hover:border-cyan-300/40"
                            aria-expanded={userMenuOpen}
                            aria-haspopup="menu"
                        >
                            <span className="flex h-8 w-8 items-center justify-center rounded-lg border border-cyan-300/25 bg-cyan-300/10 text-xs font-semibold text-cyan-100">
                                {(displayName || 'U').charAt(0).toUpperCase()}
                            </span>
                            <span className="block text-xs font-semibold tracking-wide text-zinc-100">{displayName}</span>
                        </button>

                        {userMenuOpen && (
                            <div className="absolute right-0 top-full mt-3 w-64 overflow-hidden rounded-2xl border border-white/10 bg-[#0B1120] shadow-2xl shadow-black/30" role="menu">
                                <div className="border-b border-white/10 px-4 py-3">
                                    <p className="text-sm font-semibold text-zinc-100">{displayName}</p>
                                    <p className="mt-1 text-xs text-zinc-400">{user?.email ?? '-'}</p>
                                </div>
                                <Link
                                    href={route('profile.edit')}
                                    className="block px-4 py-3 text-xs font-medium tracking-wide text-zinc-300 transition-colors hover:bg-white/[0.04] hover:text-cyan-100"
                                    role="menuitem"
                                >
                                    設定檔案
                                </Link>
                                <Link
                                    href="/"
                                    className="block px-4 py-3 text-xs font-medium tracking-wide text-zinc-300 transition-colors hover:bg-white/[0.04] hover:text-cyan-100"
                                    role="menuitem"
                                >
                                    回到官網
                                </Link>
                                <button
                                    type="button"
                                    onClick={handleLogout}
                                    className="block w-full px-4 py-3 text-left text-xs font-medium tracking-wide text-zinc-300 transition-colors hover:bg-white/[0.04] hover:text-cyan-100"
                                    role="menuitem"
                                >
                                    登出
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </header>
    );
}
