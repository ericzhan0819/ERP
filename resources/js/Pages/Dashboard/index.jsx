import DashboardLayout from '@/Layouts/DashboardLayout';
import { usePage } from '@inertiajs/react';

const panelClass = 'rounded-2xl border border-default bg-surface/80 p-4 backdrop-blur-xl md:p-6';
const mutedLabelClass = 'text-[11px] font-semibold uppercase tracking-[0.24em] text-muted';

const metricCards = [
    { label: '在庫總量', value: '128', unit: '輛', change: '+6.2%', tone: 'text-emerald-300', accent: 'bg-cyan-300/12 text-cyan-100' },
    { label: '本月成交', value: '34', unit: '筆', change: '+3.8%', tone: 'text-emerald-300', accent: 'bg-emerald-300/12 text-emerald-100' },
    { label: '平均毛利', value: '12.4', unit: '%', change: '+1.1%', tone: 'text-emerald-300', accent: 'bg-violet-300/12 text-violet-100' },
    { label: '待處理款項', value: '5', unit: '項', change: '-2.0%', tone: 'text-amber-300', accent: 'bg-amber-300/12 text-amber-100' },
];

const pipelineRows = [
    { stage: '收車估價', count: 18, ratio: '72%', tone: 'bg-cyan-300' },
    { stage: '整備維修', count: 27, ratio: '58%', tone: 'bg-violet-300' },
    { stage: '待銷售上架', count: 42, ratio: '84%', tone: 'bg-emerald-300' },
    { stage: '交車結案', count: 16, ratio: '44%', tone: 'bg-amber-300' },
];

const financeRows = [
    { label: '採購成本', value: 'NT$ 18.6M', delta: '+4.1%' },
    { label: '整備費用', value: 'NT$ 2.4M', delta: '-1.8%' },
    { label: '銷售佣金', value: 'NT$ 0.9M', delta: '+2.6%' },
];

const recentVehicles = [
    { vin: 'A102', model: 'BMW 320i Touring', status: '整備完成', margin: '13.8%', tone: 'text-emerald-300 bg-emerald-300/10' },
    { vin: 'B884', model: 'Lexus NX 200', status: '待估價', margin: '—', tone: 'text-amber-300 bg-amber-300/10' },
    { vin: 'C719', model: 'Mercedes-Benz C300', status: '銷售洽談', margin: '11.2%', tone: 'text-cyan-200 bg-cyan-300/10' },
    { vin: 'D431', model: 'Toyota RAV4 Hybrid', status: '待交車', margin: '9.7%', tone: 'text-violet-200 bg-violet-300/10' },
];

const activities = [
    { time: '09:12', text: 'VIN#A102 整備結案，維修成本已歸戶。' },
    { time: '10:40', text: '新增收車案件 2 筆，等待主管估價審核。' },
    { time: '13:05', text: '銷售佣金批次核算完成，等待出帳確認。' },
    { time: '16:22', text: '保固風險警示 1 筆，已指派維修顧問。' },
];

const quickActions = ['新增收車', '建立銷售單', '查看應收款'];

function MetricCard({ item }) {
    return (
        <article className="rounded-2xl border border-default bg-surface p-4 transition-colors duration-150 hover:border-active hover:bg-hover md:p-5">
            <div className="flex items-start justify-between gap-3">
                <div className={`grid h-11 w-11 place-items-center rounded-xl ${item.accent}`}>
                    <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                        <path d="M4 19V5M4 19h16M8 15v-4M12 15V8M16 15v-6" strokeLinecap="round" strokeLinejoin="round" />
                    </svg>
                </div>
                <span className={`rounded-full bg-subtle px-2.5 py-1 text-xs font-semibold ${item.tone}`}>{item.change}</span>
            </div>
            <p className="mt-5 text-sm text-secondary">{item.label}</p>
            <div className="mt-2 flex items-end gap-2">
                <p className="text-3xl font-semibold tracking-tight text-primary">{item.value}</p>
                <span className="pb-1 text-sm text-muted">{item.unit}</span>
            </div>
        </article>
    );
}

function PanelHeader({ eyebrow, title, action }) {
    return (
        <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p className={mutedLabelClass}>{eyebrow}</p>
                <h2 className="mt-2 text-lg font-semibold tracking-tight text-primary">{title}</h2>
            </div>
            {action}
        </div>
    );
}

export default function DashboardIndex() {
    const { auth } = usePage().props;
    const capabilities = auth?.capabilities ?? {};

    // 技術註解：Dashboard 權限僅採用後端白名單能力鍵，避免前端自行推導真實授權。
    const canUseActions = Boolean(capabilities['dashboard.quick_actions']);
    const canViewFinanceSummary = Boolean(capabilities['dashboard.finance_summary']);
    const canExportSummary = Boolean(capabilities['dashboard.export_summary']);
    const canViewRiskPanel = Boolean(capabilities['dashboard.risk_panel']);

    return (
        <div className="space-y-4 md:space-y-6">
            <section className="grid grid-cols-12 gap-4 md:gap-6">
                <div className="col-span-12 space-y-4 md:space-y-6 xl:col-span-7">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6">
                        {metricCards.map((item) => (
                            <MetricCard key={item.label} item={item} />
                        ))}
                    </div>

                    <section className={panelClass}>
                        <PanelHeader eyebrow="Inventory Flow" title="車輛生命週期管制" />
                        <div className="space-y-5">
                            {pipelineRows.map((row) => (
                                <div key={row.stage}>
                                    <div className="mb-2 flex items-center justify-between text-sm">
                                        <span className="font-medium text-primary">{row.stage}</span>
                                        <span className="text-muted">{row.count} 輛</span>
                                    </div>
                                    <div className="h-2 rounded-full bg-subtle">
                                        <div className={`h-2 rounded-full ${row.tone}`} style={{ width: row.ratio }} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                </div>

                <div className="col-span-12 space-y-4 md:space-y-6 xl:col-span-5">
                    <section className={panelClass}>
                            <PanelHeader
                                eyebrow="Finance Clarity"
                                title="帳務摘要 Widget"
                                action={
                                    canExportSummary ? (
                                        <button className="min-h-10 rounded-xl border border-active bg-accent-subtle px-4 text-sm font-medium text-accent transition-colors hover:bg-active" type="button">
                                            匯出摘要
                                        </button>
                                    ) : null
                                }
                            />
                            {canViewFinanceSummary ? (
                                <>
                                    <div className="rounded-2xl border border-default bg-subtle p-5">
                                        <p className="text-sm text-secondary">本月預估毛利（Placeholder）</p>
                                        <p className="mt-3 text-4xl font-semibold tracking-tight text-primary">NT$ 4.82M</p>
                                        <p className="mt-2 text-sm font-medium text-emerald-300">較上月 +8.4%</p>
                                    </div>
                                    <div className="mt-4 divide-y divide-default">
                                        {financeRows.map((row) => (
                                            <div key={row.label} className="flex items-center justify-between py-3">
                                                <span className="text-sm text-secondary">{row.label}</span>
                                                <div className="text-right">
                                                    <p className="text-sm font-semibold text-primary">{row.value}</p>
                                                    <p className="text-xs text-muted">{row.delta}</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </>
                            ) : (
                                <div className="rounded-2xl border border-default bg-subtle p-5 text-sm text-secondary">無帳務摘要檢視權限</div>
                            )}
                    </section>

                    <section className={panelClass}>
                        <PanelHeader eyebrow="Precision Actions" title="快速操作" />
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3 xl:grid-cols-1">
                            {quickActions.map((label) => (
                                <button
                                    key={label}
                                    type="button"
                                    className="min-h-12 rounded-xl border border-default bg-surface px-4 text-left text-sm font-medium text-primary transition-colors duration-150 hover:border-active hover:bg-hover"
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                    </section>
                </div>
            </section>

            <section className="grid grid-cols-12 gap-4 md:gap-6">
                <section className={`${panelClass} col-span-12 xl:col-span-7`}>
                    <PanelHeader eyebrow="Vehicle Records" title="近期車輛紀錄" />
                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead>
                                <tr className="border-y border-default text-left">
                                    <th className="py-3 pr-4 text-xs font-semibold uppercase tracking-[0.22em] text-muted">VIN</th>
                                    <th className="px-4 py-3 text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500">車型</th>
                                    <th className="px-4 py-3 text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500">狀態</th>
                                    <th className="py-3 pl-4 text-right text-xs font-semibold uppercase tracking-[0.22em] text-zinc-500">毛利</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-default">
                                {recentVehicles.map((vehicle) => (
                                    <tr key={vehicle.vin} className="transition-colors hover:bg-hover">
                                        <td className="py-4 pr-4 text-sm font-semibold text-primary">{vehicle.vin}</td>
                                        <td className="px-4 py-4 text-sm text-secondary">{vehicle.model}</td>
                                        <td className="px-4 py-4">
                                            <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${vehicle.tone}`}>{vehicle.status}</span>
                                        </td>
                                        <td className="py-4 pl-4 text-right text-sm font-semibold text-primary">{vehicle.margin}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className={`${panelClass} col-span-12 xl:col-span-5`}>
                    <PanelHeader eyebrow="Audit Trail" title="作業動態 / 風險" />
                    {canViewRiskPanel ? (
                        <div className="space-y-3">
                            {activities.map((item) => (
                                <div key={`${item.time}-${item.text}`} className="flex gap-3 rounded-xl border border-default bg-surface p-3">
                                    <span className="shrink-0 text-xs font-semibold text-cyan-300">{item.time}</span>
                                    <p className="text-sm leading-6 text-secondary">{item.text}</p>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-xl border border-default bg-surface p-3 text-sm text-secondary">無風險面板檢視權限</div>
                    )}
                </section>
            </section>
        </div>
    );
}

DashboardIndex.layout = (page) => <DashboardLayout title="Operations Overview">{page}</DashboardLayout>;
