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

export default function Welcome({ brand = {} }) {
    // 技術註解：純 UI Demo 固定導向登入展示頁，不判斷後端認證狀態。
    const employeeEntryHref = typeof route === 'function' ? route('login') : '/login';
    const brandName = brand?.brand_name;
    const brandNameEn = brand?.brand_name_en;
    const brandSubtitle = brand?.brand_subtitle;
    const brandSlogan = brand?.brand_slogan;
    const brandEyebrow = brand?.brand_eyebrow;

    return (
        <>
            <Head>
                <title>{brandName}</title>
                <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
            </Head>

            {/* 背景層 */}
            <div className="min-h-screen w-full overflow-x-hidden m-0 bg-surface p-0 text-primary selection:bg-accent-subtle">
                {/* 內容容器 */}
                <div className="flex min-h-screen w-full flex-col px-6 py-12 lg:px-20 max-w-[1600px] mx-auto">
                    
                    {/* Header */}
                    <header className="grid grid-cols-1 gap-10 border-b border-default pb-10 lg:grid-cols-12 lg:items-end">
                        <div className="relative lg:col-span-8">
                            {/* 小標 */}
                            <p className="mb-4 whitespace-nowrap ps-1 text-[11px] font-medium uppercase tracking-[0.4em] text-muted">
                                {brandEyebrow} / {brandNameEn}
                            </p>

                            {/* 品牌名稱 */}
                            <h1 className="text-4xl whitespace-nowrap font-extralight uppercase tracking-[0.2em] text-primary sm:text-6xl lg:text-7xl">
                                {brandName}
                            </h1>

                            {/* 副標 */}
                            <p className="mt-6 ps-0.5 max-w-2xl whitespace-normal text-sm font-normal leading-8 tracking-wider text-secondary lg:whitespace-nowrap">
                                {brandSubtitle}
                            </p>
    
                            {/* slogan */}
                            <p className="mt-3 whitespace-nowrap ps-0.5 text-xs font-medium tracking-[0.5em] text-accent">
                                {brandSlogan}
                            </p>
                        </div>
                    </header>

                    {/* 主內容區 */}
                    <main className="grid flex-1 grid-cols-1 gap-8 py-12 lg:grid-cols-12">
                        
                        {/* 左側：營運總覽 */}
                        <section className="rounded-[28px] border border-default bg-elevated p-8 shadow-card lg:col-span-8">
                            <div className="mb-8 flex items-end justify-between border-b border-default pb-5">
                                <h2 className="text-xl font-bold uppercase tracking-[0.2em] text-primary">
                                    營運總覽
                                </h2>
                                <span className="text-[10px] font-medium uppercase tracking-[0.3em] text-muted">
                                    Real-time Snapshot
                                </span>
                            </div>

                            <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                {kpiCards.map((card) => (
                                    <article
                                        key={card.label}
                                        className="group rounded-2xl border border-default bg-surface p-6 transition-all duration-300 hover:border-active hover:bg-hover hover:shadow-card"
                                    >
                                        <p className="text-[10px] font-bold uppercase tracking-[0.2em] text-muted transition-colors group-hover:text-accent">
                                            {card.label}
                                        </p>
                                        <div className="mt-4 flex items-end justify-between">
                                            <p className="text-4xl font-light text-primary">
                                                {card.value}
                                                <span className="ml-2 text-sm font-normal text-muted">
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
                        <section className="rounded-[28px] border border-default bg-elevated p-8 shadow-card lg:col-span-4">
                            <h2 className="text-xl font-bold uppercase tracking-[0.2em] text-primary">
                                作業序列
                            </h2>

                            <p className="mt-4 text-xs font-normal leading-7 tracking-widest text-secondary">
                                極簡但嚴謹的流程設計，確保每一筆收支、每一台車況、每一次佣金計算，都可在系統中閉環管理。
                            </p>

                            <ol className="mt-8 space-y-5">
                                {processSteps.map((step, index) => (
                                    <li
                                        key={step}
                                        className="flex items-start gap-4 border-l-2 border-default pl-4 transition-all hover:border-active"
                                    >
                                        <span className="mt-0.5 text-[10px] font-bold text-accent">
                                            {String(index + 1).padStart(2, '0')}
                                        </span>

                                        <span className="text-sm font-medium tracking-wide text-secondary">
                                            {step}
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        </section>
                    </main>

                    {/* Footer */}
                    <footer className="mt-auto py-10 text-center">
                        <p className="text-[10px] font-bold uppercase tracking-[0.5em] text-muted">
                            Secure Management System Center
                        </p>
                    </footer>

                     <div className="flex justify-end pb-10">
                         <Link 
                             href={employeeEntryHref} 
                             className="group flex items-center gap-2 text-[10px] font-bold tracking-[0.4em] text-muted transition-colors hover:text-accent"
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
                    background: var(--color-bg-surface) !important;
                    margin: 0 !important; 
                    padding: 0 !important;
                    color: var(--color-text-primary);
                }

                ::-webkit-scrollbar {
                    width: 6px;
                }

                ::-webkit-scrollbar-track {
                    background: var(--color-bg-elevated);
                }

                ::-webkit-scrollbar-thumb {
                    background: var(--color-border-default);
                    border-radius: 10px;
                }

                ::-webkit-scrollbar-thumb:hover {
                    background: var(--color-border-active);
                }
            `}} />
        </>
    );
}
