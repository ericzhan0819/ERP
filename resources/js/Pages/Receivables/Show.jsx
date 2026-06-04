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
    const displayValue = (value) => (value === null || value === undefined || value === '' ? '—' : value);
    const formatNumber = (value) => {
        if (value === null || value === undefined || value === '') return '—';
        const parsed = Number(value);
        return Number.isNaN(parsed) ? displayValue(value) : parsed.toLocaleString('zh-TW');
    };
    const canReceive = can.create_receivables === true && !sale.receivable_block_reason;
    const canMarkSold = can.can_mark_sold_receivable === true && sale.canMarkSold === true;

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

    return (
        <DashboardLayout user={auth.user}>
            <div className="space-y-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div><p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">Receivable Workspace</p><h1 className="mt-1 text-2xl font-semibold text-primary">收款管理</h1></div>
                    <div className="flex flex-wrap gap-2"><Link href={route('employee-system.receivables.index')} className="rounded-lg border border-default px-3 py-2 text-sm text-secondary">返回收款列表</Link>{sale.vehicle?.id && <Link href={route('employee-system.vehicles.show', sale.vehicle.id)} className="rounded-lg border border-default px-3 py-2 text-sm text-secondary">前往車輛詳情</Link>}{sale.vehicle?.id && <Link href={route('employee-system.vehicles.edit', sale.vehicle.id)} className="rounded-lg bg-accent px-3 py-2 text-sm text-white">前往車輛編輯</Link>}</div>
                </div>

                <section className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div className="rounded-2xl border border-default bg-surface p-4 text-sm text-secondary"><h2 className="mb-3 font-semibold text-primary">銷售與車輛資訊</h2><div className="grid grid-cols-1 gap-2 sm:grid-cols-2"><p>車輛：{displayValue(sale.vehicle?.stock_number)} / {displayValue(sale.vehicle?.brand)} {displayValue(sale.vehicle?.model)}</p><p>車牌：{displayValue(sale.vehicle?.license_plate)}</p><p>VIN：{displayValue(sale.vehicle?.vin)}</p><p>客戶：{sale.customer?.customer_number ? `${sale.customer.customer_number}｜` : ''}{displayValue(sale.customer_name)}</p><p>電話：{displayValue(sale.customer_phone)}</p><p>銷售狀態：{displayValue(sale.sale_status_label)}</p><p>成交價：{formatNumber(sale.sale_price)}</p><p>成交日：{formatDate(sale.sold_at)}</p><p>業務：{displayValue(sale.salesperson_name)}</p><p>備註：{displayValue(sale.notes)}</p></div></div>
                    <div className="rounded-2xl border border-default bg-surface p-4 text-sm text-secondary"><h2 className="mb-3 font-semibold text-primary">收款摘要</h2><div className="grid grid-cols-2 gap-3"><p>應收金額：<b className="text-primary">{formatNumber(sale.payment_summary?.receivable_amount)}</b></p><p>已收金額：<b className="text-primary">{formatNumber(sale.payment_summary?.received_amount)}</b></p><p>未收金額：<b className="text-primary">{formatNumber(sale.payment_summary?.receivable_balance)}</b></p><p>收款狀態：<b className="text-primary">{displayValue(sale.payment_summary?.receivable_status_label)}</b></p><p>有效收款：{formatNumber(sale.payment_summary?.received_payment_count)} 筆</p><p>紀錄總數：{formatNumber(sale.payment_summary?.payment_record_count)} 筆</p></div>{sale.payment_summary?.receivable_status === 'overpaid' && <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">提醒：此筆銷售目前為超收狀態。</p>}{canMarkSold && <div className="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800"><span>收款已完成，可將此筆銷售標記為成交。</span><button type="button" disabled={markSoldForm.processing} onClick={submitMarkSold} className="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60">標記為成交</button></div>}{!canMarkSold && sale.markSoldHelpText && <p className="mt-3 text-xs text-muted">目前不可標記成交：{sale.markSoldHelpText}</p>}</div>
                </section>

                {canReceive ? <form onSubmit={submitPayment} className="rounded-2xl border border-default bg-surface p-4"><h2 className="mb-3 text-sm font-semibold text-primary">新增收款</h2><div className="grid grid-cols-1 gap-3 md:grid-cols-3"><select value={paymentForm.data.payment_type} onChange={(e) => paymentForm.setData('payment_type', e.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm">{Object.entries(paymentTypes).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select><select value={paymentForm.data.payment_method} onChange={(e) => paymentForm.setData('payment_method', e.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm">{Object.entries(paymentMethods).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select><input type="number" step="0.01" min="0.01" placeholder="金額" value={paymentForm.data.amount} onChange={(e) => paymentForm.setData('amount', e.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm" /><input type="date" value={paymentForm.data.paid_at} onChange={(e) => paymentForm.setData('paid_at', e.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm" /><input placeholder="參考號碼" value={paymentForm.data.reference_no} onChange={(e) => paymentForm.setData('reference_no', e.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm" /><input placeholder="備註" value={paymentForm.data.notes} onChange={(e) => paymentForm.setData('notes', e.target.value)} className="rounded-lg border border-default bg-transparent px-3 py-2 text-sm" /></div><button disabled={paymentForm.processing} className="mt-3 rounded-lg bg-accent px-4 py-2 text-sm text-white">送出收款</button></form> : sale.receivable_block_reason && <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">{sale.receivable_block_reason}</div>}

                <section className="rounded-2xl border border-default bg-surface p-4"><h2 className="mb-3 text-sm font-semibold text-primary">收款紀錄</h2>{(sale.payments || []).length === 0 ? <p className="text-sm text-muted">尚無收款紀錄。</p> : <div className="space-y-2">{sale.payments.map((payment) => <div key={payment.id} className="rounded-xl border border-default p-3 text-sm text-secondary"><p>{payment.payment_number}｜{payment.payment_type_label}｜{payment.payment_method_label}｜{formatNumber(payment.amount)}｜{formatDate(payment.paid_at)}｜{payment.status_label}</p><p className="mt-1 text-xs text-muted">參考：{displayValue(payment.reference_no)}｜備註：{displayValue(payment.notes)}｜建立者：{displayValue(payment.creator?.name)}｜作廢者：{displayValue(payment.voider?.name)}｜作廢時間：{displayValue(payment.voided_at)}｜作廢原因：{displayValue(payment.void_reason)}</p>{can.void_receivables && payment.status === 'received' && <button type="button" onClick={() => setVoidingPaymentId(voidingPaymentId === payment.id ? null : payment.id)} className="mt-2 text-xs text-red-600 underline">作廢</button>}{voidingPaymentId === payment.id && <form onSubmit={(event) => submitVoid(event, payment.id)} className="mt-2 flex gap-2"><input value={voidForm.data.void_reason} onChange={(e) => voidForm.setData('void_reason', e.target.value)} placeholder="請輸入作廢原因" className="flex-1 rounded-lg border border-default bg-transparent px-3 py-2 text-sm" /><button className="rounded-lg bg-red-600 px-3 py-2 text-sm text-white">確認作廢</button></form>}</div>)}</div>}</section>
            </div>
        </DashboardLayout>
    );
}