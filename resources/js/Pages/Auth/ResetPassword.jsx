import { Head, Link, useForm } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';

export default function ResetPassword({ email }) {
    const [showPassword, setShowPassword] = useState(false);
    const inputRefs = useRef([]);
    
    const { data, setData, post, processing, errors } = useForm({
        otp: '',
        password: '',
        password_confirmation: '',
    });

    // Handle OTP input
    const handleOtpChange = (index, value) => {
        // Only allow digits
        if (!/^\d*$/.test(value)) return;
        
        const newOtp = data.otp.split('');
        newOtp[index] = value.slice(-1);
        
        const otpString = newOtp.join('').slice(0, 6);
        setData('otp', otpString);
        
        // Auto-focus next input
        if (value && index < 5) {
            inputRefs.current[index + 1]?.focus();
        }
    };

    const handleOtpKeyDown = (index, e) => {
        if (e.key === 'Backspace' && !data.otp[index] && index > 0) {
            inputRefs.current[index - 1]?.focus();
        }
    };

    const handlePaste = (e) => {
        e.preventDefault();
        const pastedData = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
        setData('otp', pastedData);
        
        // Focus appropriate input
        const focusIndex = Math.min(pastedData.length, 5);
        inputRefs.current[focusIndex]?.focus();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/reset-password');
    };

    const handleResend = () => {
        window.location.href = '/forgot-password/resend';
    };

    return (
        <div className="min-h-screen bg-[#FAFAFA] flex items-center justify-center p-4">
            <Head title="Reset Password" />

            <div className="w-full max-w-md">
                {/* Logo */}
                <div className="text-center mb-8">
                    <Link href="/" className="inline-block">
                        <h1 className="text-3xl font-black text-gray-900" style={{ textShadow: '3px 3px 0 #e5e7eb' }}>
                            SPACEDIGITAL
                        </h1>
                    </Link>
                </div>

                {/* Card */}
                <div className="neo-card p-8">
                    <div className="mb-6">
                        <h2 className="text-2xl font-bold text-gray-900">Reset Password</h2>
                        <p className="text-gray-500 mt-1">
                            Enter the code sent to <span className="font-semibold text-[#8B5CF6]">{email}</span>
                        </p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-5">
                        {/* OTP Input */}
                        <div>
                            <label className="block text-sm font-semibold text-gray-700 mb-3">
                                Verification Code
                            </label>
                            <div className="flex justify-center gap-2" onPaste={handlePaste}>
                                {[0, 1, 2, 3, 4, 5].map((index) => (
                                    <input
                                        key={index}
                                        ref={(el) => (inputRefs.current[index] = el)}
                                        type="text"
                                        inputMode="numeric"
                                        maxLength={1}
                                        value={data.otp[index] || ''}
                                        onChange={(e) => handleOtpChange(index, e.target.value)}
                                        onKeyDown={(e) => handleOtpKeyDown(index, e)}
                                        className="w-12 h-14 text-center text-xl font-bold border-2 border-gray-900 rounded-lg shadow-[3px_3px_0_#1A1A1A] focus:border-[#8B5CF6] focus:shadow-[3px_3px_0_#8B5CF6] focus:outline-none transition-all"
                                    />
                                ))}
                            </div>
                            {errors.otp && (
                                <p className="mt-2 text-sm text-red-500 text-center">{errors.otp}</p>
                            )}
                        </div>

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
                            disabled={processing || data.otp.length < 6}
                            className="neo-btn-primary w-full"
                        >
                            {processing ? 'Resetting...' : 'Reset Password'}
                        </button>
                    </form>

                    <div className="mt-6 text-center space-y-3">
                        <button
                            onClick={handleResend}
                            className="text-sm text-[#8B5CF6] hover:underline font-medium"
                        >
                            Didn't receive code? Resend
                        </button>
                        
                        <div className="border-t border-gray-100 pt-3">
                            <Link href="/login" className="text-sm text-gray-500 hover:underline">
                                ← Back to Login
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
