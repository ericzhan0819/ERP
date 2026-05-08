import { Link } from '@inertiajs/react';

export default function Header({ user, onOpenMobileSidebar }) {
    return (
        <header className="sticky top-0 z-40 border-b border-white/10 bg-[#0B1120]/80 backdrop-blur-xl">
            <div className="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                <div className="flex items-center gap-3">
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

                    <div className="hidden text-right sm:block">
                        <p className="text-xs font-medium text-zinc-100">{user?.name ?? 'User'}</p>
                        <p className="text-[11px] text-zinc-400">{user?.email ?? '-'}</p>
                    </div>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="rounded-md border border-white/15 px-3 py-2 text-xs tracking-wide text-zinc-300 transition-colors active:scale-[0.98] hover:border-cyan-300/40 hover:text-cyan-200"
                    >
                        登出
                    </Link>
                </div>
            </div>
        </header>
    );
}
