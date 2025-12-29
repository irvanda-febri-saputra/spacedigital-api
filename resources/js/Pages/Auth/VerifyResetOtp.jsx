import { Head, Link, useForm } from '@inertiajs/react';
import { useRef } from 'react';

export default function VerifyResetOtp({ email }) {
    const inputRefs = useRef([]);
    
    const { data, setData, post, processing, errors } = useForm({
        otp: '',
    });

    // Handle OTP input
    const handleOtpChange = (index, value) => {
        if (!/^\d*$/.test(value)) return;
        
        const newOtp = data.otp.split('');
        newOtp[index] = value.slice(-1);
        
        const otpString = newOtp.join('').slice(0, 6);
        setData('otp', otpString);
        
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
        
        const focusIndex = Math.min(pastedData.length, 5);
        inputRefs.current[focusIndex]?.focus();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/forgot-password/verify');
    };

    return (
        <div className="min-h-screen bg-[#FAFAFA] flex items-center justify-center p-4">
            <Head title="Verify Code" />

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
                    <div className="w-8 h-8 rounded-full bg-[#8B5CF6] text-white flex items-center justify-center text-sm font-bold">2</div>
                    <div className="w-12 h-1 bg-gray-200"></div>
                    <div className="w-8 h-8 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center text-sm font-bold">3</div>
                </div>

                {/* Card */}
                <div className="neo-card p-8">
                    <div className="mb-6 text-center">
                        <div className="w-16 h-16 mx-auto mb-4 bg-[#8B5CF6]/10 rounded-full flex items-center justify-center">
                            <svg className="w-8 h-8 text-[#8B5CF6]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h2 className="text-2xl font-bold text-gray-900">Check Your Email</h2>
                        <p className="text-gray-500 mt-2">
                            We sent a 6-digit code to<br />
                            <span className="font-semibold text-[#8B5CF6]">{email}</span>
                        </p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-5">
                        {/* OTP Input */}
                        <div>
                            <label className="block text-sm font-semibold text-gray-700 mb-3 text-center">
                                Enter Verification Code
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
                                <p className="mt-3 text-sm text-red-500 text-center">{errors.otp}</p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={processing || data.otp.length < 6}
                            className="neo-btn-primary w-full"
                        >
                            {processing ? 'Verifying...' : 'Verify Code'}
                        </button>
                    </form>

                    <div className="mt-6 text-center space-y-3">
                        <p className="text-sm text-gray-500">
                            Didn't receive the code?{' '}
                            <Link href="/forgot-password/resend" className="text-[#8B5CF6] hover:underline font-medium">
                                Resend
                            </Link>
                        </p>
                        
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
