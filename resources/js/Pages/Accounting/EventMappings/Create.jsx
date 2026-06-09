import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import Form from './Form';

export default function CreateAccountingEventMapping({ auth, supportedEventTypes = {}, mappingKeyOptions = {}, accountOptions = [], branchOptions = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        event_type: 'vehicle_sale_completed',
        mapping_key: 'accounts_receivable_account',
        account_id: '',
        branch_id: null,
        is_active: true,
        notes: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('employee-system.accounting.event-mappings.store'));
    };

    return (
        <DashboardLayout user={auth.user}>
            <div className="mx-auto max-w-4xl space-y-5 p-4 md:p-6">
                <Header title="新增會計事件映射" description="建立 vehicle_sale_completed 的必要科目對應。" />
                <form onSubmit={submit} className="space-y-4 rounded-2xl border border-default bg-surface p-5">
                    <Form data={data} setData={setData} errors={errors} supportedEventTypes={supportedEventTypes} mappingKeyOptions={mappingKeyOptions} accountOptions={accountOptions} branchOptions={branchOptions} />
                    <Actions processing={processing} />
                </form>
            </div>
        </DashboardLayout>
    );
}

function Header({ title, description }) {
    return <div className="rounded-2xl border border-default bg-surface p-5"><p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted">Accounting</p><h1 className="mt-2 text-2xl font-semibold text-primary">{title}</h1><p className="mt-2 text-sm text-secondary">{description}</p></div>;
}

function Actions({ processing }) {
    return <div className="flex flex-wrap items-center justify-end gap-2 border-t border-default pt-4"><Link href={route('employee-system.accounting.event-mappings.index')} className="rounded-lg border border-default px-4 py-2 text-sm font-semibold text-secondary transition hover:border-primary hover:text-primary">取消</Link><button type="submit" disabled={processing} className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50">建立映射</button></div>;
}
