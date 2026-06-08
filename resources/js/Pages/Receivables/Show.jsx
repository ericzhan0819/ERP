import React, { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { formatDate } from '@/utils/formatDateTime';

/**
 * 技術註解：單筆收款工作台以後端 can 旗標控制 UX；實際新增/作廢仍由 ReceivableController 權限與 tenant scope 強制執行。
 */
export default function ReceivablesShow({ auth, sale, paymentTypes = {}, paymentMethods = {}, can = {} }) {
    const [voidingPaymentId, setVoidingPaymentId] = useState(null);
    const paymentForm = useForm({ payment_type: 'deposit', payment_method: 'cash', amount: '', paid_at: '', reference_no: '', notes: '' });
    const voidForm = useForm({ void_reason: '' });
    const markSoldForm = useForm({});
    const completionForm = useForm({ completion_note: '' });
    const displayValue = (value) => (value === null || value === undefined || value === '' ? '—' : value);
    const formatNumber = (value) => {
        if (value === null || value === undefined || value === '') return '—';
        const parsed = Number(value);
        return Number.isNaN(parsed) ? displayValue(value) : parsed.toLocaleString('zh-TW');
    };
    const canReceive = can.create_receivables === true && !sale.receivable_block_reason;
    const canMarkSold = can.can_mark_sold_receivable === true && sale.canMarkSold === true;
    const completion = sale.completion ?? {};
    const isCompleted = completion.status === 'completed';
    const canComplete = completion.can_complete === true && Boolean(completion.complete_route);
    const completionBadgeClass = (status) => status === 'completed'
        ? 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-200 dark:ring-emerald-800'
        : 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-800/80 dark:text-slate-200 dark:ring-slate-700';

    const submitPayment = (event) => {
        event.preventDefault();
        paymentForm.post(route('employee-system.receivables.payments.store', sale.id), { preserveScroll: true, onSuccess: () => paymentForm.reset() });
    };
    const submitVoid = (event, paymentId) => {
        event.preventDefault();
        voidForm.patch(route('employee-system.receivables.payments.void', [sale.id, paymentId]), { preserveScroll: true, onSuccess: () => { voidForm.reset(); setVoidingPaymentId(null); } });
    };
    const submitMarkSold = () => {
        // 技術註解：此處只送出使用者明確的一鍵成交意圖；權限與狀態條件仍由後端強制檢查，避免前端 UX 被繞過。
        markSoldForm.patch(route('employee-system.receivables.mark-sold', sale.id), { preserveScroll: true });
    };
    const submitCompletion = (event) => {
        event.preventDefault();
        if (!canComplete) return;

        // 技術註解：完成交易只送出後端允許的備註欄位，避免前端注入操作者、租戶或會計相關欄位。
        completionForm.patch(completion.complete_route, {
            data: { completion_note: completionForm.data.completion_note },
            preserveScroll: true,
            onSuccess: () => completionForm.reset(),
        });
    };

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div><p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">Receivable Workspace</p><h1 className="mt-1 text-2xl font-semibold text-primary">收款管理</h1></div>
                    <div className="flex flex-wrap gap-2"><Link href={route('employee-system.receivables.index')} className="rounded-lg border border-default px-3 py-2 text-sm text-secondary">返回收款列表</Link>{sale.vehicle?.id && <Link href={route('employee-system.vehicles.show', sale.vehicle.id)} className="rounded-lg border border-default px-3 py-2 text-sm text-secondary">前往車輛詳情</Link>}{sale.vehicle?.id && <Link href={route('employee-system.vehicles.edit', sale.vehicle.id)} className="rounded-lg bg-accent px-3 py-2 text-sm text-white">前往車輛編輯</Link>}</div>
                </div>

                <section className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div className="rounded-2xl border border-default bg-surface p-4 text-sm text-secondary"><h2 className="mb-3 font-semibold text-primary">銷售與車輛資訊</h2><div className="grid grid-cols-1 gap-2 sm:grid-cols-2"><p>車輛：{displayValue(sale.vehicle?.stock_number)} / {displayValue(sale.vehicle?.brand)} {displayValue(sale.vehicle?.model)}</p><p>車牌：{displayValue(sale.vehicle?.license_plate)}</p><p>VIN：{displayValue(sale.vehicle?.vin)}</p><p>客戶：{sale.customer?.customer_number ? `${sale.customer.customer_number}｜` : ''}{displayValue(sale.customer_name)}</p><p>電話：{displayValue(sale.customer_phone)}</p><p>銷售狀態：{displayValue(sale.sale_status_label)}</p><p>成交價：{formatNumber(sale.sale_price)}</p><p>成交日：{formatDate(sale.sold_at)}</p><p>業務：{displayValue(sale.salesperson_name)}</p><p>備註：{displayValue(sale.notes)}</p></div></div>
                    <div className="rounded-2xl border border-default bg-surface p-4 text-sm text-secondary"><h2 className="mb-3 font-semibold text-primary">收款摘要</h2><div className="grid grid-cols-2 gap-3"><p>應收金額：<b className="text-primary">{formatNumber(sale.payment_summary?.receivable_amount)}</b></p><p>已收金額：<b className="text-primary">{formatNumber(sale.payment_summary?.received_amount)}</b></p><p>未收金額：<b className="text-primary">{formatNumber(sale.payment_summary?.receivable_balance)}</b></p><p>收款狀態：<b className="text-primary">{displayValue(sale.payment_summary?.receivable_status_label)}</b></p><p>有效收款：{formatNumber(sale.payment_summary?.received_payment_count)} 筆</p><p>紀錄總數：{formatNumber(sale.payment_summary?.payment_record_count)} 筆</p></div>{sale.payment_summary?.receivable_status === 'overpaid' && <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">提醒：此筆銷售目前為超收狀態。</p>}{canMarkSold && <div className="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800"><span>收款條件已滿足，可將銷售與車輛狀態標記為 sold。此動作不是交車完成，也不會自動認列收入或 COGS。</span><button type="button" disabled={markSoldForm.processing} onClick={submitMarkSold} className="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60">標記為成交</button></div>}{!canMarkSold && sale.markSoldHelpText && <div className="mt-3 space-y-1 text-xs text-muted"><p>目前不可標記成交：{sale.markSoldHelpText}</p><p>mark sold 僅代表銷售狀態銜接，交車完成將在後續流程獨立處理。</p></div>}</div>
                </section>

                <section className="rounded-2xl border border-default bg-surface p-4 text-sm text-secondary">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-semibold text-primary">交易完成</h2>
                            <p className="mt-1 text-xs text-muted">此區只消費後端 completion 狀態；正式授權與條件仍由後端控制。</p>
                        </div>
                        <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${completionBadgeClass(completion.status)}`}>
                            {isCompleted ? '已完成交易' : displayValue(completion.status_label)}
                        </span>
                    </div>
                    <div className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <p>狀態：<b className="text-primary">{displayValue(completion.status_label)}</b></p>
                        <p>完成時間：{displayValue(completion.completed_at)}</p>
                        <p>完成人員：{displayValue(completion.completed_by_name)}</p>
                        <p>備註：{displayValue(completion.note)}</p>
                    </div>
                    {isCompleted && <p className="mt-3 rounded-lg border border-default px-3 py-2 text-xs text-muted">此交易已完成。此狀態不代表已自動產生會計分錄。</p>}
                    {!isCompleted && canComplete && (
                        <form onSubmit={submitCompletion} className="mt-3 space-y-3 rounded-xl border border-default p-3">
                            <p className="text-xs text-muted">完成交易只記錄交易完成狀態，不會自動認列收入、COGS 或產生會計分錄。</p>
                            <label className="block text-sm">
                                <span className="mb-1 block text-muted">完成備註</span>
                                <textarea value={completionForm.data.completion_note} onChange={(event) => completionForm.setData('completion_note', event.target.value)} placeholder="例如：交車完成，文件已確認。" className="min-h-20 w-full rounded-lg border border-default bg-transparent px-3 py-2 text-sm" />
                            </label>
                            {completionForm.errors.completion_note && <p className="text-xs text-red-600">{completionForm.errors.completion_note}</p>}
                            <button type="submit" disabled={completionForm.processing} className="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60">確認交車 / 完成交易</button>
                        </form>
                    )}
                    {!isCompleted && !canComplete && (
                        <div className="mt-3 space-y-1 rounded-lg border border-default px-3 py-2 text-xs text-muted">
                            <p>{displayValue(completion.block_reason || '目前尚不可完成交易。')}</p>
                            <p>需先完成收款條件與 mark sold 後，才可能完成交易。</p>
                        </div>
                    )}
                </section>

                <section className="rounded-2xl border border-default bg-surface p-4 text-sm text-secondary">
                    <h2 className="text-sm font-semibold text-primary">流程語意提示</h2>
                    <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-4">
                        <p><span className="block text-xs font-semibold text-primary">Step 1｜Payments</span><span className="text-xs text-muted">記錄實際收到的款項。</span></p>
                        <p><span className="block text-xs font-semibold text-primary">Step 2｜Mark Sold</span><span className="text-xs text-muted">收款條件滿足時，銜接銷售與車輛 sold 狀態。</span></p>
                        <p><span className="block text-xs font-semibold text-primary">Step 3｜Confirm Delivery</span><span className="text-xs text-muted">交車完成 / 完成交易尚未實作，未來會獨立處理。</span></p>
                        <p><span className="block text-xs font-semibold text-primary">Step 4｜Accounting</span><span className="text-xs text-muted">目前不自動產生 accounting event、journal draft、revenue 或 COGS。</span></p>
                    </div>
                </section>

                {canReceive ? <form onSubmit={submitPayment} className="rounded-2xl border border-default bg-surface p-4"><h2 className="mb-1 text-sm font-semibold text-primary">新增收款</h2><p className="mb-3 text-xs text-muted">送出收款只建立 payment record，不代表收入認列。</p><div className="grid grid-cols-1 gap-3 md:grid-cols-3"><select value={paymentForm.data.payment_type} onChange={(e) => paymentForm.setData('payment_type', e.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm">{Object.entries(paymentTypes).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select><select value={paymentForm.data.payment_method} onChange={(e) => paymentForm.setData('payment_method', e.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm">{Object.entries(paymentMethods).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select><input type="number" step="0.01" min="0.01" placeholder="金額" value={paymentForm.data.amount} onChange={(e) => paymentForm.setData('amount', e.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm" /><input type="date" value={paymentForm.data.paid_at} onChange={(e) => paymentForm.setData('paid_at', e.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm" /><input placeholder="參考號碼" value={paymentForm.data.reference_no} onChange={(e) => paymentForm.setData('reference_no', e.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm" /><input placeholder="備註" value={paymentForm.data.notes} onChange={(e) => paymentForm.setData('notes', e.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm" /></div><button disabled={paymentForm.processing} className="mt-3 rounded-lg bg-accent px-4 py-2 text-sm text-white">送出收款</button></form> : sale.receivable_block_reason && <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">{sale.receivable_block_reason}</div>}

                <section className="rounded-2xl border border-default bg-surface p-4"><h2 className="mb-1 text-sm font-semibold text-primary">收款紀錄</h2><p className="mb-3 text-xs text-muted">已作廢收款不計入已收金額；收款紀錄不等於正式會計分錄。</p>{(sale.payments || []).length === 0 ? <p className="text-sm text-muted">尚無收款紀錄。</p> : <div className="space-y-2">{sale.payments.map((payment) => <div key={payment.id} className="rounded-xl border border-default p-3 text-sm text-secondary"><p>{payment.payment_number}｜{payment.payment_type_label}｜{payment.payment_method_label}｜{formatNumber(payment.amount)}｜{formatDate(payment.paid_at)}｜{payment.status_label}</p><p className="mt-1 text-xs text-muted">參考：{displayValue(payment.reference_no)}｜備註：{displayValue(payment.notes)}｜建立者：{displayValue(payment.creator?.name)}｜作廢者：{displayValue(payment.voider?.name)}｜作廢時間：{displayValue(payment.voided_at)}｜作廢原因：{displayValue(payment.void_reason)}</p>{can.void_receivables && payment.status === 'received' && <button type="button" onClick={() => setVoidingPaymentId(voidingPaymentId === payment.id ? null : payment.id)} className="mt-2 text-xs text-red-600 underline">作廢</button>}{voidingPaymentId === payment.id && <form onSubmit={(event) => submitVoid(event, payment.id)} className="mt-2 flex gap-2"><input value={voidForm.data.void_reason} onChange={(e) => voidForm.setData('void_reason', e.target.value)} placeholder="請輸入作廢原因" className="flex-1 rounded-lg border border-default bg-transparent px-3 py-2 text-sm" /><button className="rounded-lg bg-red-600 px-3 py-2 text-sm text-white">確認作廢</button></form>}</div>)}</div>}</section>
            </div>
        </DashboardLayout>
    );
}
