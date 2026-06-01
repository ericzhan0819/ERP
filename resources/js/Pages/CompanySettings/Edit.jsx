import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Edit({ company }) {
    const form = useForm({
        name: company?.name ?? '',
        code: company?.code ?? '',
        tax_id: company?.tax_id ?? '',
        phone: company?.phone ?? '',
        email: company?.email ?? '',
        address: company?.address ?? '',
        logo_url: company?.logo_url ?? '',
        currency: company?.currency ?? 'TWD',
        brand_name: company?.brand_name ?? '',
        brand_name_en: company?.brand_name_en ?? '',
        brand_subtitle: company?.brand_subtitle ?? '',
        brand_slogan: company?.brand_slogan ?? '',
        brand_eyebrow: company?.brand_eyebrow ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        form.put(route('employee-system.company-settings.update'));
    };

    return (
        <DashboardLayout title="公司設定">
            <Head title="公司設定" />

            <section className="rounded-2xl border border-default bg-surface/70 p-6">
                <h2 className="text-sm font-semibold text-primary">公司基本資料</h2>
                <form className="mt-4 grid gap-5 md:grid-cols-2" onSubmit={submit}>
                    <div>
                        <InputLabel htmlFor="name" value="公司名稱" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                        <TextInput id="name" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} disabled={form.processing} />
                        <InputError className="mt-2" message={form.errors.name} />
                    </div>

                    <div>
                        <InputLabel htmlFor="code" value="公司代碼" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                        {/* 技術註解：code 可能作為系統識別鍵，先鎖定唯讀避免破壞既有關聯。 */}
                        <TextInput id="code" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary" value={form.data.code} disabled />
                    </div>

                    <div>
                        <InputLabel htmlFor="tax_id" value="統一編號" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                        <TextInput id="tax_id" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary" value={form.data.tax_id} onChange={(e) => form.setData('tax_id', e.target.value)} disabled={form.processing} />
                        <InputError className="mt-2" message={form.errors.tax_id} />
                    </div>

                    <div>
                        <InputLabel htmlFor="phone" value="電話" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                        <TextInput id="phone" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} disabled={form.processing} />
                        <InputError className="mt-2" message={form.errors.phone} />
                    </div>

                    <div>
                        <InputLabel htmlFor="email" value="Email" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                        <TextInput id="email" type="email" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} disabled={form.processing} />
                        <InputError className="mt-2" message={form.errors.email} />
                    </div>

                    <div>
                        <InputLabel htmlFor="currency" value="幣別" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                        <TextInput id="currency" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary uppercase" value={form.data.currency} onChange={(e) => form.setData('currency', e.target.value.toUpperCase())} disabled={form.processing} />
                        <InputError className="mt-2" message={form.errors.currency} />
                    </div>

                    <div className="md:col-span-2">
                        <InputLabel htmlFor="address" value="地址" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                        <TextInput id="address" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary" value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} disabled={form.processing} />
                        <InputError className="mt-2" message={form.errors.address} />
                    </div>

                    <div className="md:col-span-2">
                        <InputLabel htmlFor="logo_url" value="Logo 網址" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                        <TextInput id="logo_url" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary" value={form.data.logo_url} onChange={(e) => form.setData('logo_url', e.target.value)} disabled={form.processing} />
                        <InputError className="mt-2" message={form.errors.logo_url} />
                    </div>

                    <div className="md:col-span-2">
                        <h3 className="mb-2 text-sm font-semibold text-primary">首頁與品牌文案</h3>
                    </div>

                    <div>
                        <InputLabel htmlFor="brand_name" value="品牌中文名稱" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                        <TextInput id="brand_name" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary" value={form.data.brand_name} onChange={(e) => form.setData('brand_name', e.target.value)} disabled={form.processing} />
                        <InputError className="mt-2" message={form.errors.brand_name} />
                    </div>

                    <div>
                        <InputLabel htmlFor="brand_name_en" value="品牌英文小標" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                        <TextInput id="brand_name_en" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary" value={form.data.brand_name_en} onChange={(e) => form.setData('brand_name_en', e.target.value)} disabled={form.processing} />
                        <InputError className="mt-2" message={form.errors.brand_name_en} />
                    </div>

                    <div className="md:col-span-2">
                        <InputLabel htmlFor="brand_subtitle" value="品牌副標" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                        <TextInput id="brand_subtitle" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary" value={form.data.brand_subtitle} onChange={(e) => form.setData('brand_subtitle', e.target.value)} disabled={form.processing} />
                        <InputError className="mt-2" message={form.errors.brand_subtitle} />
                    </div>

                    <div className="md:col-span-2">
                        <InputLabel htmlFor="brand_slogan" value="品牌 Slogan" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                        <TextInput id="brand_slogan" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary" value={form.data.brand_slogan} onChange={(e) => form.setData('brand_slogan', e.target.value)} disabled={form.processing} />
                        <InputError className="mt-2" message={form.errors.brand_slogan} />
                    </div>

                    <div className="md:col-span-2">
                        <InputLabel htmlFor="brand_eyebrow" value="首頁上方小標/年份" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                        <TextInput id="brand_eyebrow" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary" value={form.data.brand_eyebrow} onChange={(e) => form.setData('brand_eyebrow', e.target.value)} disabled={form.processing} />
                        <InputError className="mt-2" message={form.errors.brand_eyebrow} />
                    </div>

                    <div className="md:col-span-2">
                        <PrimaryButton className="rounded-xl border border-active bg-accent-subtle px-4 py-3 text-xs font-bold uppercase tracking-[0.25em] text-accent transition-all hover:bg-active" disabled={form.processing}>儲存公司設定</PrimaryButton>
                    </div>
                </form>
            </section>
        </DashboardLayout>
    );
}
