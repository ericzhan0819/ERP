import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export default function Header({ sidebarCollapsed = false, sidebarPinned = false, onToggleSidebar, onOpenMobileSidebar }) {
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const menuRef = useRef(null);
    const user = usePage().props.auth?.user ?? {};
    const displayName = user.name || user.email || 'User';
    // 技術註解：中文名稱以最後一個漢字作為識別，非中文維持首字母，避免改動既有頭像視覺結構。
    const hanCharacters = displayName.match(/\p{Script=Han}/gu);
    const avatarInitial = hanCharacters?.at(-1) ?? (displayName || 'U').charAt(0).toUpperCase();

    useEffect(() => {
        const closeMenu = (event) => {
            // 技術註解：集中處理外部點擊與 ESC 關閉，避免 dropdown 狀態散落到其他模組。
            if (event.key === 'Escape' || (menuRef.current && !menuRef.current.contains(event.target))) {
                setUserMenuOpen(false);
            }
        };

        document.addEventListener('mousedown', closeMenu);
        document.addEventListener('keydown', closeMenu);

        return () => {
            document.removeEventListener('mousedown', closeMenu);
            document.removeEventListener('keydown', closeMenu);
        };
    }, []);

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

                    <div className="relative" ref={menuRef}>
                        <button
                            type="button"
                            onClick={() => setUserMenuOpen((open) => !open)}
                            className="flex min-h-11 max-w-[46vw] items-center gap-3 rounded-xl border border-white/10 bg-white/[0.02] px-3 py-2 text-left transition-colors active:scale-[0.98] hover:border-cyan-300/40 sm:max-w-none"
                            aria-expanded={userMenuOpen}
                            aria-haspopup="menu"
                        >
                            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-cyan-300/25 bg-cyan-300/10 text-xs font-semibold text-cyan-100">
                                {avatarInitial}
                            </span>
                            <span className="block truncate text-xs font-semibold tracking-wide text-zinc-100">{displayName}</span>
                        </button>

                        {userMenuOpen && (
                            <div className="absolute right-0 top-full z-50 mt-3 w-64 overflow-hidden rounded-2xl border border-white/10 bg-[#0B1120] shadow-2xl shadow-black/30" role="menu">
                                <div className="border-b border-white/10 px-4 py-3">
                                    <p className="truncate text-sm font-semibold text-zinc-100">{displayName}</p>
                                    <p className="mt-1 truncate text-xs text-zinc-400">{user.email}</p>
                                </div>
                                <button
                                    type="button"
                                    className="block w-full cursor-not-allowed px-4 py-3 text-left text-xs font-medium tracking-wide text-zinc-500"
                                    disabled
                                    role="menuitem"
                                >
                                    設定檔案
                                </button>
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    onSuccess={() => window.location.assign('/')}
                                    className="block w-full px-4 py-3 text-left text-xs font-medium tracking-wide text-zinc-300 transition-colors hover:bg-white/[0.04] hover:text-cyan-100"
                                    role="menuitem"
                                >
                                    登出
                                </Link>
                                <Link
                                    href="/"
                                    className="block px-4 py-3 text-xs font-medium tracking-wide text-zinc-300 transition-colors hover:bg-white/[0.04] hover:text-cyan-100"
                                    role="menuitem"
                                >
                                    回首頁
                                </Link>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </header>
    );
}
