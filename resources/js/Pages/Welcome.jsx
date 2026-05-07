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
    '銷售、佣金與帳務即時對賬',
    '單車獲利追蹤與風險預警',
];

export default function Welcome({ auth }) {
    return (
        <>
            <Head>
                <title>OO國際車業</title>
                {/* 背景延伸到手機狀態列，解決上方白邊 */}
                <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
            </Head>

            {/* 1. 全域背景層 */}
            <div 
                className="min-h-screen w-full text-gray-100 overflow-x-hidden m-0 p-0 selection:bg-indigo-500/30"
                style={{ backgroundColor: '#0f172a' }} 
            >
                {/* 處理響應式間距與最大寬度 */}
                <div className="flex min-h-screen w-full flex-col px-6 py-12 lg:px-20 max-w-[1600px] mx-auto">
                    
                    {/* Header */}
                    <header className="grid grid-cols-1 gap-10 border-b border-gray-700/50 pb-10 lg:grid-cols-12 lg:items-end">
                        <div className="relative lg:col-span-8">
                            {/* 小標 */}
                            <p className="mb-4 whitespace-nowrap ps-1 text-[11px] font-thin uppercase tracking-[0.4em] text-gray-500">
                                EST. 2026 / OO INTERNATIONAL
                            </p>

                            {/* 品牌名稱 */}
                            <h1 className="text-4xl whitespace-nowrap font-extralight uppercase tracking-[0.2em] text-white sm:text-6xl lg:text-7xl">
                                OO國際車業
                            </h1>

                            {/* 副標 */}
                            <p className="mt-6 ps-0.5 max-w-2xl text-sm font-light leading-8 tracking-wider text-gray-400 whitespace-normal lg:whitespace-nowrap">
                                以「絕對透明、系統秩序、專業可靠」為核心，<br className="sm:hidden" />
                                建立擇車如擇友的中古車管理中樞。
                            </p>
    
                            {/* slogan */}
                            <p className="mt-3 whitespace-nowrap ps-0.5 text-xs font-thin tracking-[0.5em] text-amber-200/60">
                                擇車如擇友，敘白如敘舊
                            </p>
                        </div>
                    </header>

                    {/* 主內容區 */}
                    <main className="grid flex-1 grid-cols-1 gap-8 py-12 lg:grid-cols-12">
                        
                        {/* 左側：營運總覽 */}
                        <section className="rounded-xl border border-gray-800 bg-gray-900/40 p-8 backdrop-blur-sm lg:col-span-8">
                            <div className="mb-8 flex items-end justify-between border-b border-gray-800 pb-5">
                                <h2 className="text-xl font-extralight uppercase tracking-[0.2em] text-gray-200">
                                    營運總覽
                                </h2>
                                <span className="text-[10px] uppercase tracking-[0.3em] text-gray-500">
                                    Real-time Snapshot
                                </span>
                            </div>

                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                {kpiCards.map((card) => (
                                    <article
                                        key={card.label}
                                        className="rounded-lg border border-gray-800 bg-[#0f172a]/80 p-6 transition-all hover:border-gray-700"
                                    >
                                        <p className="text-[10px] uppercase tracking-[0.2em] text-gray-500">
                                            {card.label}
                                        </p>
                                        <div className="mt-4 flex items-end justify-between">
                                            <p className="text-4xl font-extralight text-gray-100">
                                                {card.value}
                                                <span className="ml-2 text-sm font-thin text-gray-500">
                                                    {card.unit}
                                                </span>
                                            </p>
                                            <p className={`text-xs tracking-widest ${card.trend.startsWith('+') ? 'text-indigo-400' : 'text-rose-400'}`}>
                                                {card.trend}
                                            </p>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        </section>

                        {/* 右側：作業流程 */}
                        <section className="rounded-xl border border-gray-800 bg-gray-900/40 p-8 backdrop-blur-sm lg:col-span-4">
                            <h2 className="text-xl font-extralight uppercase tracking-[0.2em] text-gray-200">
                                作業序列
                            </h2>
                            <p className="mt-4 text-xs leading-7 tracking-widest text-gray-500">
                                極簡但嚴謹的流程設計，確保每一筆收支、每一台車況、每一次佣金計算，都可在同一個系統中閉環管理。
                            </p>

                            <ol className="mt-8 space-y-5">
                                {processSteps.map((step, index) => (
                                    <li
                                        key={step}
                                        className="flex items-start gap-4 border-l border-gray-800 pl-4 transition-colors hover:border-indigo-500/50"
                                    >
                                        <span className="mt-0.5 text-[10px] font-medium text-indigo-400/70">
                                            {String(index + 1).padStart(2, '0')}
                                        </span>
                                        <span className="text-sm font-light tracking-wide text-gray-300">{step}</span>
                                    </li>
                                ))}
                            </ol>
                        </section>
                    </main>

                    {/* 增加底部留白，防止被手機系統條遮擋 */}
                    <footer className="mt-auto py-10 text-center">
                        <p className="text-[10px] tracking-[0.5em] text-gray-600 uppercase">
                            Secure Management System Center
                        </p>
                    </footer>
                    <div className="flex justify-end pb-10">
                        <Link 
                            href={route('login')} 
                            className="text-[9px] tracking-[0.4em] text-gray-800/40 hover:text-indigo-400/50 transition-colors"
                        >
                            員工入口
                        </Link>
                    </div>
                </div>
            </div>
            

            {/* 解決光暈與白邊 */}
            <style dangerouslySetInnerHTML={{ __html: `
                /* 確保 body 也是深色，解決所有白邊根源 */
                body { 
                    background-color: #0f172a !important; 
                    margin: 0 !important; 
                    padding: 0 !important; 
                }
                /* 如果系統有預設光暈遮罩，將其強制透明化 */
                [class*="gradient-to-t"], [class*="fixed-bottom"] { 
                    display: none !important; 
                }
                /* 優化捲動條外觀 */
                ::-webkit-scrollbar { width: 5px; }
                ::-webkit-scrollbar-track { background: #0f172a; }
                ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; }
            `}} />
        </>
        
    );
}