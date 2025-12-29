import { Head, Link, useForm } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';

export default function ForgotPassword({ turnstileSiteKey }) {
    const [turnstileReady, setTurnstileReady] = useState(false);
    const turnstileRef = useRef(null);

    const { data, setData, post, processing, errors } = useForm({
        email: '',
        'cf-turnstile-response': '',
    });

    useEffect(() => {
        if (!turnstileSiteKey) {
            setTurnstileReady(true);
            return;
        }

        // Load Turnstile script
        if (!window.turnstile) {
            const script = document.createElement('script');
            script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
            script.async = true;
            script.defer = true;
            script.onload = () => {
                setTimeout(() => {
                    if (window.turnstile && turnstileRef.current) {
                        window.turnstile.render(turnstileRef.current, {
                            sitekey: turnstileSiteKey,
                            callback: (token) => {
                                setData('cf-turnstile-response', token);
                                setTurnstileReady(true);
                            },
                        });
                    }
                }, 100);
            };
            document.head.appendChild(script);
        } else {
            setTimeout(() => {
                if (turnstileRef.current) {
                    window.turnstile.render(turnstileRef.current, {
                        sitekey: turnstileSiteKey,
                        callback: (token) => {
                            setData('cf-turnstile-response', token);
                            setTurnstileReady(true);
                        },
                    });
                }
            }, 100);
        }
    }, [turnstileSiteKey]);

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/forgot-password');
    };

    return (
        <div className="min-h-screen bg-[#FAFAFA] flex items-center justify-center p-4">
            <Head title="Forgot Password" />

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
                        <h2 className="text-2xl font-bold text-gray-900">Forgot Password</h2>
                        <p className="text-gray-500 mt-1">Enter your email to receive a password reset code</p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div>
                            <label className="block text-sm font-semibold text-gray-700 mb-2">
                                Email Address
                            </label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className="neo-input"
                                placeholder="you@example.com"
                                required
                            />
                            {errors.email && (
                                <p className="mt-1.5 text-sm text-red-500">{errors.email}</p>
                            )}
                        </div>

                        {/* Turnstile Widget */}
                        {turnstileSiteKey && (
                            <div>
                                <div ref={turnstileRef}></div>
                                {errors.turnstile && (
                                    <p className="mt-1.5 text-sm text-red-500">{errors.turnstile}</p>
                                )}
                                {errors['cf-turnstile-response'] && (
                                    <p className="mt-1.5 text-sm text-red-500">{errors['cf-turnstile-response']}</p>
                                )}
                            </div>
                        )}

                        <button
                            type="submit"
                            disabled={processing}
                            className="neo-btn-primary w-full"
                        >
                            {processing ? 'Sending...' : 'Send Reset Code'}
                        </button>
                    </form>

                    <div className="mt-6 text-center">
                        <Link href="/login" className="text-sm text-[#8B5CF6] hover:underline font-medium">
                            ← Back to Login
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
