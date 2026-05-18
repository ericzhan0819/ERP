import Checkbox from '@/Components/Checkbox';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, useForm } from '@inertiajs/react';
import { FaUser, FaLock, FaEye, FaEyeSlash } from 'react-icons/fa';
import { useState } from 'react';

export default function Login() {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        // 技術註解：改用 Inertia POST 呼叫後端 session auth，成功後由 Laravel 導向員工系統。
        post(typeof route === 'function' ? route('login.store') : '/login', {
            onFinish: () => reset('password'),
        });
    };

    const updateField = (field, value) => {
        // 技術註解：表單狀態交由 Inertia useForm 管理，與後端驗證錯誤保持同步。
        setData(field, value);
    };

    const [passwordVisible, setPasswordVisible] = useState(false);

    const togglePasswordVisibility = () => {
        setPasswordVisible(!passwordVisible);
    };

    return (
        <>
            <Head title="Log in" />

            <div
                className="min-h-screen w-full overflow-x-hidden text-primary selection:bg-accent-subtle"
                style={{
                    background: `
                        radial-gradient(circle at top left, color-mix(in srgb, var(--color-accent-primary) 14%, transparent), transparent 30%),
                        radial-gradient(circle at bottom right, color-mix(in srgb, var(--color-accent-info) 14%, transparent), transparent 35%),
                        linear-gradient(135deg, var(--color-bg-base) 0%, var(--color-bg-surface) 45%, var(--color-bg-elevated) 100%)
                    `,
                }}
            >
                <div className="mx-auto flex min-h-screen w-full max-w-[1600px] flex-col px-6 py-12 lg:px-20">
                    <header className="border-b border-default pb-10">
                        <p className="mb-4 ps-1 text-[11px] font-medium uppercase tracking-[0.4em] text-muted">
                            EST. 2026 / OO INTERNATIONAL
                        </p>
                        <h1 className="text-4xl font-extralight uppercase tracking-[0.2em] text-primary sm:text-5xl lg:text-6xl">
                            OO國際車業
                        </h1>
                        <p className="mt-3 whitespace-nowrap ps-0.5 text-xs font-medium tracking-[0.5em] text-accent">
                            擇車如擇友，敘白如敘舊。
                        </p>
                    </header>

                    <main className="grid flex-1 grid-cols-1 items-center py-12 lg:grid-cols-12">
                        <section className="rounded-[28px] border border-default bg-surface/70 p-8 shadow-card backdrop-blur-xl lg:col-span-6 lg:col-start-4">
                            <div className="mb-8 flex items-end justify-between border-b border-default pb-5">
                                <h2 className="text-xl font-bold uppercase tracking-[0.2em] text-primary">員工登入</h2>
                                <span className="text-[10px] font-medium uppercase tracking-[0.3em] text-muted">Secure Auth</span>
                            </div>

                            <form onSubmit={submit} className="space-y-5">
                                <div className="relative">
                                    <InputLabel htmlFor="email" value="Email 或手機號碼" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                                    <div className="relative mt-2 flex items-center">
                                        <FaUser className="pointer-events-none absolute left-4 text-xs text-muted" />
                                        <TextInput
                                            id="email"
                                            type="text"
                                            name="email"
                                            value={data.email}
                                            className="block w-full rounded-xl border border-default bg-surface py-3 pl-10 pr-4 text-sm text-primary placeholder:text-muted focus:border-active focus:bg-elevated focus:ring-focus"
                                            autoComplete="username"
                                            isFocused={true}
                                            onChange={(e) => updateField('email', e.target.value)}
                                            placeholder="Email 或手機號碼"
                                            required
                                        />
                                    </div>
                                    <InputError message={errors.email} className="mt-2" />
                                </div>

                                <div className="relative">
                                    <InputLabel htmlFor="password" value="密碼" className="text-xs font-bold uppercase tracking-[0.2em] text-muted" />
                                    <div className="relative mt-2 flex items-center">
                                        <FaLock className="pointer-events-none absolute left-4 text-xs text-muted" />
                                        <TextInput
                                            id="password"
                                            type={passwordVisible ? 'text' : 'password'}
                                            name="password"
                                            value={data.password}
                                            className="block w-full rounded-xl border border-default bg-surface py-3 pl-10 pr-10 text-sm text-primary placeholder:text-muted focus:border-active focus:bg-elevated focus:ring-focus"
                                            autoComplete="current-password"
                                            onChange={(e) => updateField('password', e.target.value)}
                                            required
                                        />
                                        <button
                                            type="button"
                                            className="absolute right-3 rounded-md p-1 text-muted transition-colors hover:text-accent"
                                            onClick={togglePasswordVisibility}
                                        >
                                            {passwordVisible ? <FaEyeSlash /> : <FaEye />}
                                        </button>
                                    </div>
                                    <InputError message={errors.password} className="mt-2" />
                                </div>

                                <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
                                    <label className="flex items-center">
                                        <Checkbox
                                            name="remember"
                                            checked={data.remember}
                                            onChange={(e) => updateField('remember', e.target.checked)}
                                        />
                                        <span className="ms-2 text-sm text-secondary">記住我</span>
                                    </label>
                                </div>

                                <div className="pt-2">
                                    <PrimaryButton
                                        className="flex w-full items-center justify-center rounded-xl border border-active bg-accent-subtle px-4 py-3 text-xs font-bold uppercase tracking-[0.25em] text-accent transition-all hover:bg-active"
                                        disabled={processing}
                                    >
                                        {processing ? '驗證中' : '登入'}
                                    </PrimaryButton>
                                </div>
                            </form>
                        </section>
                    </main>
                </div>
            </div>

            <style
                dangerouslySetInnerHTML={{
                    __html: `
                        body {
                            background: var(--color-bg-base) !important;
                            margin: 0 !important;
                            padding: 0 !important;
                            color: var(--color-text-primary);
                        }
                    `,
                }}
            />
        </>
    );
}
