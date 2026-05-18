import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Edit({ profile }) {
    const profileForm = useForm({
        name: profile?.name ?? '',
        email: profile?.email ?? '',
        phone: profile?.phone ?? '',
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submitProfile = (event) => {
        event.preventDefault();
        profileForm.patch('/employee-system/profile');
    };

    const submitPassword = (event) => {
        event.preventDefault();
        passwordForm.put('/employee-system/profile/password', {
            onSuccess: () => passwordForm.reset(),
        });
    };

    return (
        <DashboardLayout title="個人設定檔">
            <Head title="個人設定檔" />

            <div className="grid gap-6 lg:grid-cols-12">
                <section className="rounded-2xl border border-default bg-surface/70 p-6 lg:col-span-4">
                    <h2 className="text-sm font-semibold text-primary">個人頭像</h2>
                    <div className="mt-4 flex flex-col items-center gap-3">
                        <div className="flex h-24 w-24 items-center justify-center rounded-full border border-dashed border-muted text-xs text-muted">
                            Avatar
                        </div>
                        <p className="text-xs text-muted">頭像上傳功能預留</p>
                    </div>
                </section>

                <section className="rounded-2xl border border-default bg-surface/70 p-6 lg:col-span-8">
                    <h2 className="text-sm font-semibold text-primary">個人資料</h2>
                    <form className="mt-4 space-y-5" onSubmit={submitProfile}>
                        <div>
                            <InputLabel htmlFor="name" value="姓名" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                            <TextInput id="name" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary placeholder:text-muted focus:border-active focus:bg-elevated focus:ring-focus disabled:cursor-not-allowed disabled:opacity-60" value={profileForm.data.name} onChange={(e) => profileForm.setData('name', e.target.value)} disabled={profileForm.processing} />
                            <InputError className="mt-2" message={profileForm.errors.name} />
                        </div>

                        <div>
                            <InputLabel htmlFor="email" value="電子郵件" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                            <TextInput id="email" type="email" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary placeholder:text-muted focus:border-active focus:bg-elevated focus:ring-focus disabled:cursor-not-allowed disabled:opacity-60" value={profileForm.data.email} onChange={(e) => profileForm.setData('email', e.target.value)} disabled={profileForm.processing} />
                            <InputError className="mt-2" message={profileForm.errors.email} />
                        </div>

                        <div>
                            <InputLabel htmlFor="phone" value="電話" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                            <TextInput id="phone" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary placeholder:text-muted focus:border-active focus:bg-elevated focus:ring-focus disabled:cursor-not-allowed disabled:opacity-60" value={profileForm.data.phone} onChange={(e) => profileForm.setData('phone', e.target.value)} disabled={profileForm.processing} />
                            <InputError className="mt-2" message={profileForm.errors.phone} />
                        </div>

                        <PrimaryButton className="rounded-xl border border-active bg-accent-subtle px-4 py-3 text-xs font-bold uppercase tracking-[0.25em] text-accent transition-all hover:bg-active" disabled={profileForm.processing}>儲存個人資料</PrimaryButton>
                    </form>
                </section>

                <section className="rounded-2xl border border-default bg-surface/70 p-6 lg:col-span-12">
                    <h2 className="text-sm font-semibold text-primary">修改密碼</h2>
                    <form className="mt-4 grid gap-5 md:grid-cols-2" onSubmit={submitPassword}>
                        <div className="md:col-span-2">
                            <InputLabel htmlFor="current_password" value="目前密碼" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                            <TextInput id="current_password" type="password" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary placeholder:text-muted focus:border-active focus:bg-elevated focus:ring-focus disabled:cursor-not-allowed disabled:opacity-60" value={passwordForm.data.current_password} onChange={(e) => passwordForm.setData('current_password', e.target.value)} disabled={passwordForm.processing} />
                            <InputError className="mt-2" message={passwordForm.errors.current_password} />
                        </div>

                        <div>
                            <InputLabel htmlFor="password" value="新密碼" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                            <TextInput id="password" type="password" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary placeholder:text-muted focus:border-active focus:bg-elevated focus:ring-focus disabled:cursor-not-allowed disabled:opacity-60" value={passwordForm.data.password} onChange={(e) => passwordForm.setData('password', e.target.value)} disabled={passwordForm.processing} />
                            <InputError className="mt-2" message={passwordForm.errors.password} />
                        </div>

                        <div>
                            <InputLabel htmlFor="password_confirmation" value="確認新密碼" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                            <TextInput id="password_confirmation" type="password" className="mt-2 block w-full rounded-xl border border-default bg-surface px-4 py-3 text-sm text-primary placeholder:text-muted focus:border-active focus:bg-elevated focus:ring-focus disabled:cursor-not-allowed disabled:opacity-60" value={passwordForm.data.password_confirmation} onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)} disabled={passwordForm.processing} />
                        </div>

                        <div className="md:col-span-2">
                            <PrimaryButton className="rounded-xl border border-active bg-accent-subtle px-4 py-3 text-xs font-bold uppercase tracking-[0.25em] text-accent transition-all hover:bg-active" disabled={passwordForm.processing}>更新密碼</PrimaryButton>
                        </div>
                    </form>
                </section>
            </div>
        </DashboardLayout>
    );
}
