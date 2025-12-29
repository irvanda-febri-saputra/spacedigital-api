import { useState, useEffect, useRef } from 'react';
import { Head, useForm, Link, usePage } from '@inertiajs/react';

export default function Login({ turnstileSiteKey }) {
    const { flash } = usePage().props;
    const [showPassword, setShowPassword] = useState(false);
    const turnstileRef = useRef(null);
    const [turnstileToken, setTurnstileToken] = useState('');
    const [successMessage, setSuccessMessage] = useState(flash?.success || '');
    const [errorMessage, setErrorMessage] = useState(flash?.error || '');
    
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
        turnstile_token: '',
    });

    // Auto-hide success message after 5 seconds
    useEffect(() => {
        if (successMessage) {
            const timer = setTimeout(() => setSuccessMessage(''), 5000);
            return () => clearTimeout(timer);
        }
    }, [successMessage]);

    // Load Turnstile script
    useEffect(() => {
        if (!turnstileSiteKey) return;
        
        // Check if script already loaded
        if (window.turnstile) {
            renderTurnstile();
            return;
        }
        
        const script = document.createElement('script');
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileLoad';
        script.async = true;
        
        // Define global callback
        window.onTurnstileLoad = () => {
            renderTurnstile();
        };
        
        document.head.appendChild(script);

        return () => {
            if (script.parentNode) {
                script.parentNode.removeChild(script);
            }
            delete window.onTurnstileLoad;
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
        post('/login');
    };

    return (
        <>
            <Head title="Login - Spacedigital" />
            
            <div className="min-h-screen bg-[#F5F5F5] flex items-center justify-center p-6">
                <div className="w-full max-w-md">
                    {/* Login Card - Neo Brutalism */}
                    <div className="neo-card p-8">
                        {/* Success Message */}
                        {successMessage && (
                            <div className="mb-6 p-4 bg-green-50 border-2 border-green-500 rounded-lg shadow-[3px_3px_0_#22c55e]">
                                <div className="flex items-center gap-3">
                                    <div className="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center flex-shrink-0">
                                        <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <span className="font-semibold text-green-700">{successMessage}</span>
                                </div>
                            </div>
                        )}

                        {/* Error/Warning Message (Session Expired) */}
                        {errorMessage && (
                            <div className="mb-6 p-4 bg-orange-50 border-2 border-orange-500 rounded-lg shadow-[3px_3px_0_#f97316]">
                                <div className="flex items-center gap-3">
                                    <div className="w-8 h-8 bg-orange-500 rounded-full flex items-center justify-center flex-shrink-0">
                                        <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                    </div>
                                    <span className="font-semibold text-orange-700">{errorMessage}</span>
                                </div>
                            </div>
                        )}

                        {/* Header */}
                        <div className="text-center mb-8">
                            <h1 className="text-2xl font-bold text-gray-900">
                                Welcome Back
                            </h1>
                            <p className="text-gray-500 mt-2">
                                Sign in to access your dashboard
                            </p>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-5">
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
                                    <p className="mt-2 text-sm text-red-500">{errors.password}</p>
                                )}
                                <div className="mt-2 text-right">
                                    <Link href="/forgot-password" className="text-sm text-[#8B5CF6] hover:underline">
                                        Forgot password?
                                    </Link>
                                </div>
                            </div>

                            {/* Remember Me */}
                            <div className="flex items-center">
                                <label className="flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={data.remember}
                                        onChange={(e) => setData('remember', e.target.checked)}
                                        className="w-4 h-4 rounded border-2 border-gray-900 text-[#8B5CF6] focus:ring-[#8B5CF6]"
                                    />
                                    <span className="ml-2 text-sm text-gray-600">
                                        Remember me
                                    </span>
                                </label>
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
                                className="neo-btn-primary w-full disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {processing ? 'Signing in...' : 'Sign in'}
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

                        {/* Register Link */}
                        <p className="text-center text-gray-500">
                            Don't have an account?{' '}
                            <Link href="/register" className="font-semibold text-[#8B5CF6] hover:underline">
                                Sign up
                            </Link>
                        </p>
                    </div>

                    {/* Footer */}
                    <p className="mt-8 text-center text-sm text-gray-500">
                        © 2025 Spacedigital. All rights reserved.
                    </p>
                </div>
            </div>
        </>
    );
}
