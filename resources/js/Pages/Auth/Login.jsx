import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Link, Head, useForm } from '@inertiajs/react';
import { FaUser, FaLock, FaEye, FaEyeSlash } from 'react-icons/fa';
import { useState } from 'react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    const [passwordVisible, setPasswordVisible] = useState(false);

    const togglePasswordVisibility = () => {
        setPasswordVisible(!passwordVisible);
    };

    return (
        <>
            <Head title="Log in" />

            <div
                className="min-h-screen w-full overflow-x-hidden text-zinc-100 selection:bg-cyan-300/20"
                style={{
                    background: `
                        radial-gradient(circle at top left, rgba(34,211,238,0.08), transparent 30%),
                        radial-gradient(circle at bottom right, rgba(168,85,247,0.08), transparent 35%),
                        linear-gradient(135deg, #050816 0%, #0B1120 45%, #111827 100%)
                    `,
                }}
            >
                <div className="mx-auto flex min-h-screen w-full max-w-[1600px] flex-col px-6 py-12 lg:px-20">
                    <header className="border-b border-white/10 pb-10">
                        <p className="mb-4 ps-1 text-[11px] font-medium uppercase tracking-[0.4em] text-cyan-300/60">
                            EST. 2026 / OO INTERNATIONAL
                        </p>
                        <h1 className="text-4xl font-extralight uppercase tracking-[0.2em] text-white sm:text-5xl lg:text-6xl">
                            OO國際車業
                        </h1>
                        <p className="mt-3 whitespace-nowrap ps-0.5 text-xs font-medium tracking-[0.5em] text-cyan-300">
                            擇車如擇友，敘白如敘舊。
                        </p>
                    </header>

                    <main className="grid flex-1 grid-cols-1 items-center py-12 lg:grid-cols-12">
                        <section className="rounded-[28px] border border-white/10 bg-white/[0.03] p-8 shadow-[0_0_60px_rgba(0,0,0,0.35)] backdrop-blur-xl lg:col-span-6 lg:col-start-4">
                            <div className="mb-8 flex items-end justify-between border-b border-white/5 pb-5">
                                <h2 className="text-xl font-bold uppercase tracking-[0.2em] text-white">員工登入</h2>
                                <span className="text-[10px] font-medium uppercase tracking-[0.3em] text-zinc-500">Secure Auth</span>
                            </div>

                            {status && (
                                <div className="mb-5 rounded-xl border border-emerald-300/25 bg-emerald-400/10 px-4 py-3 text-sm font-medium text-emerald-200">
                                    {status}
                                </div>
                            )}

                            <form onSubmit={submit} className="space-y-5">
                                <div className="relative">
                                    <InputLabel htmlFor="email" value="手機號碼/帳號" className="text-xs font-bold uppercase tracking-[0.2em] text-zinc-400" />
                                    <div className="relative mt-2 flex items-center">
                                        <FaUser className="pointer-events-none absolute left-4 text-xs text-zinc-500" />
                                        <TextInput
                                            id="email"
                                            type="text"
                                            name="email"
                                            value={data.email}
                                            className="block w-full rounded-xl border border-white/10 bg-white/[0.04] py-3 pl-10 pr-4 text-sm text-zinc-100 placeholder:text-zinc-500 focus:border-cyan-300/50 focus:bg-white/[0.06] focus:ring-cyan-300/30"
                                            autoComplete="username"
                                            isFocused={true}
                                            onChange={(e) => setData('email', e.target.value)}
                                            required
                                        />
                                    </div>
                                    <InputError message={errors.email} className="mt-2 text-xs text-rose-300" />
                                </div>

                                <div className="relative">
                                    <InputLabel htmlFor="password" value="密碼" className="text-xs font-bold uppercase tracking-[0.2em] text-zinc-400" />
                                    <div className="relative mt-2 flex items-center">
                                        <FaLock className="pointer-events-none absolute left-4 text-xs text-zinc-500" />
                                        <TextInput
                                            id="password"
                                            type={passwordVisible ? 'text' : 'password'}
                                            name="password"
                                            value={data.password}
                                            className="block w-full rounded-xl border border-white/10 bg-white/[0.04] py-3 pl-10 pr-10 text-sm text-zinc-100 placeholder:text-zinc-500 focus:border-cyan-300/50 focus:bg-white/[0.06] focus:ring-cyan-300/30"
                                            autoComplete="current-password"
                                            onChange={(e) => setData('password', e.target.value)}
                                            required
                                        />
                                        <button
                                            type="button"
                                            className="absolute right-3 rounded-md p-1 text-zinc-500 transition-colors hover:text-cyan-300"
                                            onClick={togglePasswordVisibility}
                                        >
                                            {passwordVisible ? <FaEyeSlash /> : <FaEye />}
                                        </button>
                                    </div>
                                    <InputError message={errors.password} className="mt-2 text-xs text-rose-300" />
                                </div>

                                <div className="flex flex-wrap items-center justify-between gap-3 pt-2">
                                    <label className="flex items-center">
                                        <Checkbox
                                            name="remember"
                                            checked={data.remember}
                                            onChange={(e) => setData('remember', e.target.checked)}
                                        />
                                        <span className="ms-2 text-sm text-zinc-300">記住我</span>
                                    </label>

                                    {canResetPassword && (
                                        <Link
                                            href={route('password.request')}
                                            className="text-xs font-medium uppercase tracking-[0.2em] text-zinc-500 transition-colors hover:text-cyan-300"
                                        >
                                            忘記密碼
                                        </Link>
                                    )}
                                </div>

                                <div className="pt-2">
                                    <PrimaryButton
                                        className="flex w-full items-center justify-center rounded-xl border border-cyan-300/30 bg-cyan-300/15 px-4 py-3 text-xs font-bold uppercase tracking-[0.25em] text-cyan-100 transition-all hover:border-cyan-300/50 hover:bg-cyan-300/20"
                                        disabled={processing}
                                    >
                                        登入
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
                            background: #050816 !important;
                            margin: 0 !important;
                            padding: 0 !important;
                            color: #fff;
                        }
                    `,
                }}
            />
        </>
    );
}
