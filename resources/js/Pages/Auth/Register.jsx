import { useState, useEffect, useRef } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';

export default function Register({ turnstileSiteKey }) {
    const [showPassword, setShowPassword] = useState(false);
    const turnstileRef = useRef(null);
    const [turnstileToken, setTurnstileToken] = useState('');

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        turnstile_token: '',
    });

    // Load Turnstile script
    useEffect(() => {
        if (!turnstileSiteKey) return;

        // Check if script already loaded
        if (window.turnstile) {
            renderTurnstile();
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileLoadRegister';
        script.async = true;

        // Define global callback
        window.onTurnstileLoadRegister = () => {
            renderTurnstile();
        };

        document.head.appendChild(script);

        return () => {
            if (script.parentNode) {
                script.parentNode.removeChild(script);
            }
            delete window.onTurnstileLoadRegister;
        };
    }, [turnstileSiteKey]);

    const renderTurnstile = () => {
        if (!turnstileSiteKey || !window.turnstile || !turnstileRef.current) return;
        if (turnstileRef.current.hasChildNodes()) return; // Already rendered

        window.turnstile.render(turnstileRef.current, {
            sitekey: turnstileSiteKey,
            callback: (token) => {
                setTurnstileToken(token);
                setData('turnstile_token', token);
            },
            'expired-callback': () => {
                setTurnstileToken('');
                setData('turnstile_token', '');
            },
            theme: 'light',
        });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/register');
    };

    return (
        <>
            <Head title="Register - SpaceDigital" />

            <div className="min-h-screen bg-[#F5F5F5] flex items-center justify-center p-6">
                <div className="w-full max-w-md">
                    {/* Register Card - Neo Brutalism */}
                    <div className="neo-card p-8">
                        {/* Header */}
                        <div className="text-center mb-6">
                            <h1 className="text-2xl font-bold text-gray-900">
                                Create an Account
                            </h1>
                            <p className="text-gray-500 mt-2">
                                Join Spacedigital to manage your bot
                            </p>
                        </div>

                        {/* Info Box */}
                        <div className="mb-6 flex items-start gap-3 p-4 bg-violet-50 border-2 border-violet-500 shadow-[4px_4px_0_#7C3AED] rounded-lg">
                            <svg className="w-5 h-5 mt-0.5 flex-shrink-0 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span className="font-medium text-violet-700">You'll receive a verification email after registration</span>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            {/* Name */}
                            <div>
                                <label className="block text-sm font-semibold text-gray-900 mb-2">
                                    Full Name
                                </label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    className="neo-input"
                                    placeholder="John Doe"
                                    required
                                />
                                {errors.name && (
                                    <p className="mt-2 text-sm text-red-500">{errors.name}</p>
                                )}
                            </div>

                            {/* Email */}
                            <div>
                                <label className="block text-sm font-semibold text-gray-900 mb-2">
                                    Email
                                </label>
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="neo-input"
                                    placeholder="name@example.com"
                                    required
                                />
                                {errors.email && (
                                    <p className="mt-2 text-sm text-red-500">{errors.email}</p>
                                )}
                            </div>

                            {/* Password */}
                            <div>
                                <label className="block text-sm font-semibold text-gray-900 mb-2">
                                    Password
                                </label>
                                <div className="relative">
                                    <input
                                        type={showPassword ? 'text' : 'password'}
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        className="neo-input pr-12"
                                        placeholder="••••••••"
                                        required
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword(!showPassword)}
                                        className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    >
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            {showPassword ? (
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                            ) : (
                                                <>
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </>
                                            )}
                                        </svg>
                                    </button>
                                </div>
                                {errors.password && (
                                    <p className="mt-2 text-sm text-red-500">{errors.password}</p>
                                )}
                            </div>

                            {/* Confirm Password */}
                            <div>
                                <label className="block text-sm font-semibold text-gray-900 mb-2">
                                    Confirm Password
                                </label>
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    className="neo-input"
                                    placeholder="••••••••"
                                    required
                                />
                            </div>

                            {/* Cloudflare Turnstile */}
                            {turnstileSiteKey && (
                                <div className="flex justify-center">
                                    <div ref={turnstileRef}></div>
                                </div>
                            )}
                            {errors.turnstile && (
                                <p className="text-sm text-red-500 text-center">{errors.turnstile}</p>
                            )}

                            {/* Submit */}
                            <button
                                type="submit"
                                disabled={processing}
                                className="neo-btn-primary w-full mt-6 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {processing ? 'Creating account...' : 'Sign up'}
                            </button>
                        </form>

                        {/* Divider */}
                        <div className="relative my-6">
                            <div className="absolute inset-0 flex items-center">
                                <div className="w-full border-t-2 border-gray-200" />
                            </div>
                            <div className="relative flex justify-center">
                                <span className="px-4 bg-white text-gray-400 text-sm font-medium">
                                    OR
                                </span>
                            </div>
                        </div>

                        {/* Login Link */}
                        <p className="text-center text-gray-500">
                            Already have an account?{' '}
                            <Link href="/login" className="font-semibold text-[#8B5CF6] hover:underline">
                                Sign in
                            </Link>
                        </p>
                    </div>

                    {/* Footer */}
                    <p className="mt-6 text-center text-sm text-gray-500">
                        © 2025 Spacedigital. All rights reserved.
                    </p>
                </div>
            </div>
        </>
    );
}
