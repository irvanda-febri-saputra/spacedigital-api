import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';

export default function VerifyEmail({ email }) {
    const { flash } = usePage().props;
    const [otpValues, setOtpValues] = useState(['', '', '', '', '', '']);
    const inputRefs = useRef([]);

    const { data, setData, post, processing, errors } = useForm({
        code: '',
    });

    const { post: resendPost, processing: resendProcessing } = useForm({});

    // Handle OTP input
    const handleOtpChange = (index, value) => {
        if (!/^\d*$/.test(value)) return; // Only allow digits

        const newOtpValues = [...otpValues];
        newOtpValues[index] = value.slice(-1); // Only take last character
        setOtpValues(newOtpValues);

        // Update form data
        setData('code', newOtpValues.join(''));

        // Move to next input if value is entered
        if (value && index < 5) {
            inputRefs.current[index + 1]?.focus();
        }
    };

    const handleKeyDown = (index, e) => {
        // Move to previous input on backspace
        if (e.key === 'Backspace' && !otpValues[index] && index > 0) {
            inputRefs.current[index - 1]?.focus();
        }
    };

    const handlePaste = (e) => {
        e.preventDefault();
        const pastedData = e.clipboardData.getData('text').slice(0, 6);
        if (!/^\d+$/.test(pastedData)) return;

        const newOtpValues = pastedData.split('').concat(Array(6).fill('')).slice(0, 6);
        setOtpValues(newOtpValues);
        setData('code', newOtpValues.join(''));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/email/verify');
    };

    const handleResend = (e) => {
        e.preventDefault();
        resendPost('/email/verification-notification');
    };

    return (
        <>
            <Head title="Verify Email - SpaceDigital" />
            
            <div className="min-h-screen bg-[#F5F5F5] flex items-center justify-center p-6">
                <div className="w-full max-w-md">
                    <div className="neo-card p-8 text-center">
                        {/* Email Icon */}
                        <div className="w-20 h-20 mx-auto mb-6 bg-[#8B5CF6]/10 rounded-2xl flex items-center justify-center">
                            <svg className="w-10 h-10 text-[#8B5CF6]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>

                        <h1 className="text-2xl font-bold text-gray-900 mb-2">
                            Verify Your Email
                        </h1>
                        
                        <p className="text-gray-500 mb-6">
                            We've sent a 6-digit code to<br/>
                            <span className="font-semibold text-gray-900">{email}</span>
                        </p>

                        {/* Success/Error Messages */}
                        {flash?.message && (
                            <div className="neo-info mb-4 text-green-600 text-sm">
                                {flash.message}
                            </div>
                        )}

                        {/* OTP Input */}
                        <form onSubmit={handleSubmit} className="mb-6">
                            <div className="flex justify-center gap-2 mb-4" onPaste={handlePaste}>
                                {otpValues.map((value, index) => (
                                    <input
                                        key={index}
                                        ref={(el) => (inputRefs.current[index] = el)}
                                        type="text"
                                        inputMode="numeric"
                                        maxLength={1}
                                        value={value}
                                        onChange={(e) => handleOtpChange(index, e.target.value)}
                                        onKeyDown={(e) => handleKeyDown(index, e)}
                                        className="w-12 h-14 text-center text-2xl font-bold border-2 border-gray-300 rounded-lg focus:border-[#8B5CF6] focus:ring-2 focus:ring-[#8B5CF6]/20 outline-none transition-all"
                                    />
                                ))}
                            </div>

                            {errors.code && (
                                <p className="text-sm text-red-500 mb-4">{errors.code}</p>
                            )}

                            <button
                                type="submit"
                                disabled={processing || data.code.length !== 6}
                                className="neo-btn-primary w-full disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {processing ? 'Verifying...' : 'Verify Email'}
                            </button>
                        </form>

                        {/* Resend */}
                        <p className="text-sm text-gray-500 mb-4">
                            Didn't receive the code?
                        </p>
                        <button
                            onClick={handleResend}
                            disabled={resendProcessing}
                            className="text-[#8B5CF6] font-medium hover:underline disabled:opacity-50"
                        >
                            {resendProcessing ? 'Sending...' : 'Resend Code'}
                        </button>

                        <div className="mt-6">
                            <Link 
                                href="/login" 
                                className="text-sm text-gray-500 hover:text-gray-700"
                            >
                                ← Back to Login
                            </Link>
                        </div>
                    </div>

                    <p className="mt-6 text-center text-sm text-gray-500">
                        © 2025 Spacedigital. All rights reserved.
                    </p>
                </div>
            </div>
        </>
    );
}
