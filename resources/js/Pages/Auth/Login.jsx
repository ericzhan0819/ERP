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
        <div>
            <Head title="Log in" />
            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <div className="flex flex-col items-center justify-center min-h-screen bg-gray-900">
                <div className="w-full max-w-md bg-gray-800 rounded-lg shadow-2xl p-6">
                    <div className="text-center mb-6">
                        <h1 className="text-3xl font-extralight tracking-[0.2em] text-gray-100 uppercase">旭白國際ERP</h1>
                    </div>

                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <div className="relative">
                                <InputLabel htmlFor="email" value="手機號碼/帳號" className="text-gray-200" />
                                <div className="relative flex items-center">
                                    <FaUser className="absolute left-3 text-gray-400" />
                                    <TextInput
                                        id="email"
                                        type="text"
                                        name="email"
                                        value={data.email}
                                        className="mt-1 block w-full pl-10 pr-10 py-3 bg-gray-700 border border-gray-600 rounded-md text-white"
                                        autoComplete="username"
                                        isFocused={true}
                                        onChange={(e) => setData('email', e.target.value)}
                                        required
                                    />
                                </div>
                                <InputError message={errors.email} className="mt-2 text-red-400" />
                            </div>
                        </div>

                        <div className="mt-4">
                            <div className="relative">
                                <InputLabel htmlFor="password" value="密碼" className="text-gray-300" />
                                <div className="relative flex items-center">
                                    <FaLock className="absolute left-3 text-gray-400" />
                                    <TextInput
                                        id="password"
                                        type={passwordVisible ? "text" : "password"}
                                        name="password"
                                        value={data.password}
                                        className="mt-1 block w-full pl-10 pr-10 py-3 bg-gray-700 border border-gray-600 rounded-md text-white"
                                        autoComplete="current-password"
                                        onChange={(e) => setData('password', e.target.value)}
                                        required
                                    />
                                    <button
                                        type="button"
                                        className="absolute right-3 text-gray-400"
                                        onClick={togglePasswordVisibility}
                                    >
                                        {passwordVisible ? <FaEyeSlash /> : <FaEye />}
                                    </button>
                                </div>
                                <InputError message={errors.password} className="mt-2 text-red-400" />
                            </div>
                        </div>

                        <div className="mt-4 block">
                            <label className="flex items-center">
                                <Checkbox
                                    name="remember"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                />
                                <span className="ms-2 text-sm text-gray-300">
                                    記住我
                                </span>
                            </label>
                        </div>

                        <div className="mt-4 flex items-center justify-end">
                            <PrimaryButton className="ms-4 bg-indigo-600 w-full rounded-xl flex justify-center items-center" disabled={processing}>
                                登入
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
