import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FaUser, FaLock, FaEye, FaEyeSlash } from 'react-icons/fa';
import { useState } from 'react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        username: '',
        phone: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const [passwordVisible, setPasswordVisible] = useState(false);

    const submit = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const togglePasswordVisibility = () => {
        setPasswordVisible(!passwordVisible);
    };

    return (
        <div>
        
            <div className="flex flex-col items-center justify-center min-h-screen bg-gray-900">
                <div className="w-full max-w-md bg-gray-800 rounded-lg shadow-2xl p-6">
                    <div className="text-center mb-6">
                        <h1 className="text-3xl font-extralight tracking-[0.2em] text-gray-100 uppercase">旭白國際ERP</h1>
                    </div>
                    
                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <div className="relative">
                                <div className="relative flex items-center">
                                    <FaUser className="absolute left-3 text-gray-400" />
                                    <TextInput
                                        id="username"
                                        name="username"
                                        value={data.username}
                                        placeholder="請輸入用戶名"
                                        className="mt-1 block w-full pl-10 pr-10 py-3 bg-gray-700 border border-gray-600 rounded-md text-white"
                                        autoComplete="username"
                                        isFocused={true}
                                        onChange={(e) => setData('username', e.target.value)}
                                        required
                                    />
                                </div>
                                <InputError message={errors.username} className="mt-2" />
                            </div>
                        </div>
                        <div className="mt-4">
                            <div className="relative">
                                <div className="relative flex items-center">
                                    <FaUser className="absolute left-3 text-gray-400" />
                                    <TextInput
                                        id="phone"
                                        name="phone"
                                        value={data.phone}
                                        placeholder="請輸入手機號碼"
                                        className="mt-1 block w-full pl-10 pr-10 py-3 bg-gray-700 border border-gray-600 rounded-md text-white"
                                        autoComplete="tel"
                                        onChange={(e) => setData('phone', e.target.value)}
                                        required
                                    />
                                </div>
                                <InputError message={errors.phone} className="mt-2" />
                            </div>
                        </div>
                        <div className="mt-4">
                            <div className="relative">
                                <div className="relative flex items-center">
                                    <FaUser className="absolute left-3 text-gray-400" />
                                    <TextInput
                                        id="email"
                                        type="text"
                                        name="email"
                                        value={data.email}
                                        placeholder="請輸入電子郵件"
                                        className="mt-1 block w-full pl-10 pr-10 py-3 bg-gray-700 border border-gray-600 rounded-md text-white"
                                        autoComplete="username"
                                        isFocused={true}
                                        onChange={(e) => setData('email', e.target.value)}
                                        required
                                    />
                                </div>
                                <InputError message={errors.email} className="mt-2" />
                            </div>
                        </div>
                        <div className="mt-4">
                            <div className="relative">
                                <div className="relative flex items-center">
                                    <FaLock className="absolute left-3 text-gray-400" />
                                    <TextInput
                                        id="password"
                                        type={passwordVisible ? "text" : "password"}
                                        name="password"
                                        value={data.password}
                                        placeholder="請輸入密碼"
                                        className="mt-1 block w-full pl-10 pr-10 py-3 bg-gray-700 border border-gray-600 rounded-md text-white"
                                        autoComplete="new-password"
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
                                <InputError message={errors.password} className="mt-2" />
                            </div>
                        </div>
                        <div className="mt-4">
                            <div className="relative">
                                <div className="relative flex items-center">
                                    <FaLock className="absolute left-3 text-gray-400" />
                                    <TextInput
                                        id="password_confirmation"
                                        type="password"
                                        name="password_confirmation"
                                        value={data.password_confirmation}
                                        placeholder="請再次輸入密碼"
                                        className="mt-1 block w-full pl-10 pr-10 py-3 bg-gray-700 border border-gray-600 rounded-md text-white"
                                        autoComplete="new-password"
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                        required
                                    />
                                </div>
                                <InputError message={errors.password_confirmation} className="mt-2" />
                            </div>
                        </div>
                        <div className="mt-4 flex items-center justify-end">
                            <PrimaryButton className="ms-4 bg-indigo-600 w-full rounded-xlflex justify-center items-center" disabled={processing}>
                                註冊
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
