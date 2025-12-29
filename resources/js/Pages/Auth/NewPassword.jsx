import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function NewPassword({ email }) {
    const [showPassword, setShowPassword] = useState(false);
    
    const { data, setData, post, processing, errors } = useForm({
        password: '',
        password_confirmation: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/forgot-password/set-password');
    };

    return (
        <div className="min-h-screen bg-[#FAFAFA] flex items-center justify-center p-4">
            <Head title="Set New Password" />

            <div className="w-full max-w-md">
                {/* Logo */}
                <div className="text-center mb-8">
                    <Link href="/" className="inline-block">
                        <h1 className="text-3xl font-black text-gray-900" style={{ textShadow: '3px 3px 0 #e5e7eb' }}>
                            SPACEDIGITAL
                        </h1>
                    </Link>
                </div>

                {/* Progress Steps */}
                <div className="flex items-center justify-center gap-2 mb-6">
                    <div className="w-8 h-8 rounded-full bg-green-500 text-white flex items-center justify-center text-sm font-bold">✓</div>
                    <div className="w-12 h-1 bg-green-500"></div>
                    <div className="w-8 h-8 rounded-full bg-green-500 text-white flex items-center justify-center text-sm font-bold">✓</div>
                    <div className="w-12 h-1 bg-green-500"></div>
                    <div className="w-8 h-8 rounded-full bg-[#8B5CF6] text-white flex items-center justify-center text-sm font-bold">3</div>
                </div>

                {/* Card */}
                <div className="neo-card p-8">
                    <div className="mb-6 text-center">
                        <div className="w-16 h-16 mx-auto mb-4 bg-[#8B5CF6]/10 rounded-full flex items-center justify-center">
                            <svg className="w-8 h-8 text-[#8B5CF6]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                        </div>
                        <h2 className="text-2xl font-bold text-gray-900">Create New Password</h2>
                        <p className="text-gray-500 mt-2">
                            Set a new password for<br />
                            <span className="font-semibold text-[#8B5CF6]">{email}</span>
                        </p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-5">
                        {/* New Password */}
                        <div>
                            <label className="block text-sm font-semibold text-gray-700 mb-2">
                                New Password
                            </label>
                            <div className="relative">
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    className="neo-input pr-12"
                                    placeholder="Min 8 chars, uppercase + lowercase"
                                    required
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    {showPassword ? (
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                        </svg>
                                    ) : (
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    )}
                                </button>
                            </div>
                            {errors.password && (
                                <p className="mt-1.5 text-sm text-red-500">{errors.password}</p>
                            )}
                            <p className="mt-1.5 text-xs text-gray-400">
                                Must be at least 8 characters with uppercase and lowercase letters
                            </p>
                        </div>

                        {/* Confirm Password */}
                        <div>
                            <label className="block text-sm font-semibold text-gray-700 mb-2">
                                Confirm Password
                            </label>
                            <input
                                type={showPassword ? 'text' : 'password'}
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                className="neo-input"
                                placeholder="Repeat your password"
                                required
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={processing || !data.password || !data.password_confirmation}
                            className="neo-btn-primary w-full"
                        >
                            {processing ? 'Updating...' : 'Update Password'}
                        </button>
                    </form>

                    <div className="mt-6 text-center">
                        <Link href="/login" className="text-sm text-gray-500 hover:underline">
                            ← Back to Login
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
