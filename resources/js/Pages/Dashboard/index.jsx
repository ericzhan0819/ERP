import DashboardLayout from '@/Layouts/DashboardLayout';
import usePermission from '@/hooks/usePermission';

// 共用卡片樣式：沿用 Welcome 頁面玻璃感與深色系，並加上精簡 hover 反饋
const cardClass =
    'rounded-2xl border border-white/10 bg-white/[0.03] p-4 sm:p-5 backdrop-blur-xl transition-all duration-200 hover:-translate-y-0.5 hover:border-cyan-300/30 hover:bg-white/[0.05] hover:shadow-[0_0_28px_rgba(34,211,238,0.08)]';

// KPI 靜態資料：先用 mock data，後續可由 API 取代
const kpiData = [
    { label: '在庫總量', value: '128', unit: '輛', trend: '+6.2%' },
    { label: '本月成交', value: '34', unit: '筆', trend: '+3.8%' },
    { label: '平均毛利', value: '12.4', unit: '%', trend: '+1.1%' },
    { label: '待處理款項', value: '5', unit: '項', trend: '-2.0%' },
];

// 活動動態靜態資料：不串接 API，僅示意近期作業紀錄
const activities = [
    { time: '09:12', text: 'VIN#A102 完成整備結案，成本已歸戶。' },
    { time: '10:40', text: '新增收車案件 2 筆，待估價審核。' },
    { time: '13:05', text: '銷售佣金批次核算完成，等待出帳。' },
    { time: '16:22', text: '保固風險警示 1 筆，已指派維修顧問。' },
];

// 系統狀態靜態資料：用高對比文字呈現健康度與異常提醒
const systemStatus = [
    { label: '資料同步', value: '正常', tone: 'text-emerald-400' },
    { label: '排程任務', value: '執行中', tone: 'text-cyan-300' },
    { label: '風險警示', value: '1 筆待處理', tone: 'text-amber-300' },
];

// 快速操作靜態資料：維持簡潔行動入口，不引入額外元件庫
const quickActions = [
    { label: '新增收車', hint: '建立新車輛檔案' },
    { label: '建立銷售單', hint: '快速開立交易流程' },
    { label: '查看應收款', hint: '追蹤待入帳款項' },
];

// 公告區靜態資料：做為首頁資訊溝通區塊
const announcements = [
    '本週五 18:00 將進行例行維護，預估 20 分鐘。',
    '請於月底前完成高風險車輛保固資料檢核。',
];

// 小型模組元件：統一標題格式，維持 Dashboard 區塊視覺一致
function WidgetFrame({ title, children }) {
    return (
        <section className={cardClass}>
            <p className="text-[10px] font-semibold uppercase tracking-[0.28em] text-zinc-400">Widget</p>
            <h2 className="mt-2 text-base font-semibold text-zinc-100">{title}</h2>
            <div className="mt-4">{children}</div>
        </section>
    );
}

export default function DashboardIndex() {
    const { can, hasRole } = usePermission();

    // Widget 設定陣列：用最小結構實作 modular 與 permission 控制
    const widgets = [
        {
            key: 'kpi-cards',
            title: 'KPI Cards',
            requiredPermissions: [],
            render: () => (
                <div className="grid grid-cols-1 gap-2.5 sm:gap-3 sm:grid-cols-2">
                    {kpiData.map((item) => (
                        <article key={item.label} className="rounded-xl border border-white/10 bg-white/[0.02] p-3.5 sm:p-4">
                            <p className="text-[10px] font-semibold uppercase tracking-[0.22em] text-zinc-500">{item.label}</p>
                            <div className="mt-2 flex items-end justify-between">
                                <p className="text-2xl font-light text-zinc-100">
                                    {item.value}
                                    <span className="ml-1 text-xs font-normal text-zinc-400">{item.unit}</span>
                                </p>
                                <p className={item.trend.startsWith('+') ? 'text-xs font-semibold text-emerald-400' : 'text-xs font-semibold text-rose-400'}>
                                    {item.trend}
                                </p>
                            </div>
                        </article>
                    ))}
                </div>
            ),
        },
        {
            key: 'recent-activities',
            title: 'Recent Activities',
            access: { permission: 'dashboard.activities.view', module: 'dashboard' },
            render: () => (
                <ul className="space-y-2.5 sm:space-y-3">
                    {activities.map((item) => (
                        <li key={`${item.time}-${item.text}`} className="flex gap-3 border-l-2 border-white/10 pl-3">
                            <span className="text-xs font-semibold text-cyan-300">{item.time}</span>
                            <span className="text-sm text-zinc-300">{item.text}</span>
                        </li>
                    ))}
                </ul>
            ),
        },
        {
            key: 'system-status',
            title: 'System Status',
            access: { permission: 'dashboard.system.view', module: 'dashboard' },
            render: () => (
                <div className="space-y-2">
                    {systemStatus.map((item) => (
                        <div key={item.label} className="flex items-center justify-between rounded-xl border border-white/10 bg-white/[0.02] px-3 py-2.5">
                            <p className="text-sm text-zinc-300">{item.label}</p>
                            <p className={`text-sm font-semibold ${item.tone}`}>{item.value}</p>
                        </div>
                    ))}
                </div>
            ),
        },
        {
            key: 'quick-actions',
            title: 'Quick Actions',
            access: { permission: 'dashboard.actions.view', module: 'dashboard' },
            render: () => (
                <div className="space-y-2">
                    {quickActions.map((item) => (
                        <button
                            key={item.label}
                            type="button"
                            className="w-full min-h-11 rounded-xl border border-white/10 bg-white/[0.02] px-3 py-2.5 text-left transition-colors active:scale-[0.99] hover:border-cyan-300/40 hover:bg-white/[0.04]"
                        >
                            <p className="text-sm font-medium text-zinc-100">{item.label}</p>
                            <p className="text-xs text-zinc-400">{item.hint}</p>
                        </button>
                    ))}
                </div>
            ),
        },
        {
            key: 'announcement-area',
            title: 'Announcement Area',
            access: null,
            render: () => (
                <ul className="space-y-2">
                    {announcements.map((item) => (
                        <li key={item} className="rounded-xl border border-white/10 bg-white/[0.02] px-3 py-2.5 text-sm text-zinc-300">
                            {item}
                        </li>
                    ))}
                </ul>
            ),
        },
    ];

    // 權限過濾：只渲染可見 widgets，未授權項目不顯示
    const visibleWidgets = widgets.filter((widget) => !widget.access || can(widget.access));

    return (
        <DashboardLayout title="Dashboard">
            {/* 頁面按鈕示範：同時支援 role + module + permission */}
            {(hasRole(['Admin', 'Manager']) || can({ permission: 'dashboard.actions.view', module: 'dashboard' })) && (
                <div className="mb-3 sm:mb-4 flex justify-end">
                    <button
                        type="button"
                        className="min-h-11 rounded-xl border border-cyan-300/40 bg-cyan-300/10 px-4 py-2.5 text-sm font-medium text-cyan-100 transition-colors active:scale-[0.99] hover:bg-cyan-300/20"
                    >
                        匯出營運摘要
                    </button>
                </div>
            )}

            {/* 響應式格線：mobile 1 欄 / tablet 2 欄 / desktop 4 欄 */}
            <div className="grid grid-cols-1 gap-3 sm:gap-4 md:grid-cols-2 xl:grid-cols-4">
                {visibleWidgets.map((widget) => (
                    <WidgetFrame key={widget.key} title={widget.title}>
                        {widget.render()}
                    </WidgetFrame>
                ))}
            </div>
        </DashboardLayout>
    );
}
