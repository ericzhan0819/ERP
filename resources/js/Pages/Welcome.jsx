import { Head, Link } from '@inertiajs/react';

const kpiCards = [
    { label: '在庫總量', value: '128', unit: '輛', trend: '+6.2%' },
    { label: '本月成交', value: '34', unit: '筆', trend: '+3.8%' },
    { label: '平均毛利', value: '12.4', unit: '%', trend: '+1.1%' },
    { label: '待處理款項', value: '5', unit: '項', trend: '-2.0%' },
];

const processSteps = [
    '收車建檔與成本歸集',
    '整備進度與費用透明化',
    '銷售、佣金與帳務即時對帳',
    '單車獲利追蹤與風險預警',
];

export default function Welcome({ auth }) {
    const employeeEntryHref = auth?.user
        ? route('employee-system.overview')
        : route('login');

    return (
        <>
            <Head>
                <title>OO國際車業</title>
                <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
            </Head>

            {/* 背景層 */}
            <div 
                className="min-h-screen w-full text-zinc-100 overflow-x-hidden m-0 p-0 selection:bg-cyan-300/20"
                style={{ 
                    background: `
                        radial-gradient(circle at top left, rgba(34,211,238,0.08), transparent 30%),
                        radial-gradient(circle at bottom right, rgba(168,85,247,0.08), transparent 35%),
                        linear-gradient(135deg, #050816 0%, #0B1120 45%, #111827 100%)
                    `
                }} 
            >
                {/* 內容容器 */}
                <div className="flex min-h-screen w-full flex-col px-6 py-12 lg:px-20 max-w-[1600px] mx-auto">
                    
                    {/* Header */}
                    <header className="grid grid-cols-1 gap-10 border-b border-white/10 pb-10 lg:grid-cols-12 lg:items-end">
                        <div className="relative lg:col-span-8">
                            {/* 小標 */}
                            <p className="mb-4 whitespace-nowrap ps-1 text-[11px] font-medium uppercase tracking-[0.4em] text-cyan-300/60">
                                EST. 2026 / OO INTERNATIONAL
                            </p>

                            {/* 品牌名稱 */}
                            <h1 className="text-4xl whitespace-nowrap font-extralight uppercase tracking-[0.2em] text-white sm:text-6xl lg:text-7xl">
                                OO國際車業
                            </h1>

                            {/* 副標 */}
                            <p className="mt-6 ps-0.5 max-w-2xl text-sm font-normal leading-8 tracking-wider text-zinc-400 whitespace-normal lg:whitespace-nowrap">
                                以「絕對透明、系統秩序、專業可靠」為核心，<br className="sm:hidden" />
                                建立擇車如擇友的中古車管理中樞。
                            </p>
    
                            {/* slogan */}
                            <p className="mt-3 whitespace-nowrap ps-0.5 text-xs font-medium tracking-[0.5em] text-cyan-300">
                                擇車如擇友，敘白如敘舊
                            </p>
                        </div>
                    </header>

                    {/* 主內容區 */}
                    <main className="grid flex-1 grid-cols-1 gap-8 py-12 lg:grid-cols-12">
                        
                        {/* 左側：營運總覽 */}
                        <section className="rounded-[28px] border border-white/10 bg-white/[0.03] backdrop-blur-xl p-8 shadow-[0_0_60px_rgba(0,0,0,0.35)] lg:col-span-8">
                            <div className="mb-8 flex items-end justify-between border-b border-white/5 pb-5">
                                <h2 className="text-xl font-bold uppercase tracking-[0.2em] text-white">
                                    營運總覽
                                </h2>
                                <span className="text-[10px] uppercase tracking-[0.3em] text-zinc-500 font-medium">
                                    Real-time Snapshot
                                </span>
                            </div>

                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                {kpiCards.map((card) => (
                                    <article
                                        key={card.label}
                                        className="group rounded-2xl border border-white/6 bg-gradient-to-br from-white/[0.06] to-white/[0.02] p-6 transition-all duration-300 hover:border-cyan-300/30 hover:bg-white/[0.08] hover:shadow-[0_0_35px_rgba(34,211,238,0.08)]"
                                    >
                                        <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-500 group-hover:text-cyan-200/80 transition-colors">
                                            {card.label}
                                        </p>
                                        <div className="mt-4 flex items-end justify-between">
                                            <p className="text-4xl font-light text-white">
                                                {card.value}
                                                <span className="ml-2 text-sm font-normal text-zinc-500">
                                                    {card.unit}
                                                </span>
                                            </p>
                                            <p className={`text-xs font-bold tracking-widest ${
                                                card.trend.startsWith('+')
                                                    ? 'text-emerald-400'
                                                    : 'text-rose-400'
                                            }`}>
                                                {card.trend}
                                            </p>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        </section>

                        {/* 右側：作業流程 */}
                        <section className="rounded-[28px] border border-white/10 bg-white/[0.03] backdrop-blur-xl p-8 shadow-[0_0_60px_rgba(0,0,0,0.35)] lg:col-span-4">
                            <h2 className="text-xl font-bold uppercase tracking-[0.2em] text-white">
                                作業序列
                            </h2>

                            <p className="mt-4 text-xs leading-7 tracking-widest text-zinc-400 font-normal">
                                極簡但嚴謹的流程設計，確保每一筆收支、每一台車況、每一次佣金計算，都可在系統中閉環管理。
                            </p>

                            <ol className="mt-8 space-y-5">
                                {processSteps.map((step, index) => (
                                    <li
                                        key={step}
                                        className="flex items-start gap-4 border-l-2 border-white/10 pl-4 transition-all hover:border-cyan-300"
                                    >
                                        <span className="mt-0.5 text-[10px] font-bold text-cyan-300">
                                            {String(index + 1).padStart(2, '0')}
                                        </span>

                                        <span className="text-sm font-medium tracking-wide text-zinc-300">
                                            {step}
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        </section>
                    </main>

                    {/* Footer */}
                    <footer className="mt-auto py-10 text-center">
                        <p className="text-[10px] font-bold tracking-[0.5em] text-zinc-700 uppercase">
                            Secure Management System Center
                        </p>
                    </footer>

                     <div className="flex justify-end pb-10">
                         <Link 
                             href={employeeEntryHref} 
                             className="group flex items-center gap-2 text-[10px] font-bold tracking-[0.4em] text-zinc-500 hover:text-cyan-300 transition-colors"
                         >
                            <span>員工入口</span>
                            <span className="text-lg transition-transform group-hover:translate-x-1">
                                →
                            </span>
                        </Link>
                    </div>
                </div>
            </div>

            {/* 優化捲動條與基本樣式 */}
            <style dangerouslySetInnerHTML={{ __html: `
                body { 
                    background: #050816 !important;
                    margin: 0 !important; 
                    padding: 0 !important;
                    color: #fff;
                }

                ::-webkit-scrollbar {
                    width: 6px;
                }

                ::-webkit-scrollbar-track {
                    background: #0B1120;
                }

                ::-webkit-scrollbar-thumb {
                    background: rgba(255,255,255,0.12);
                    border-radius: 10px;
                }

                ::-webkit-scrollbar-thumb:hover {
                    background: rgba(34,211,238,0.45);
                }
            `}} />
        </>
    );
}
