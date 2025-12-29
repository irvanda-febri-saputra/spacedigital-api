import { Head, useForm } from '@inertiajs/react';
import DefaultLayout from '@/Layouts/DefaultLayout';
import { useState, useEffect } from 'react';

export default function OrderKuotaTools({ auth, currentToken, currentUsername, qrisString, tokenSavedAt, hasCredentials, flash }) {
    const [tokenStatus, setTokenStatus] = useState(null);
    const [checking, setChecking] = useState(false);
    const [showOtpForm, setShowOtpForm] = useState(false);

    // Login form
    const loginForm = useForm({
        username: currentUsername || '',
        password: '',
    });

    // OTP form
    const otpForm = useForm({
        otp: '',
    });

    // QRIS string form
    const qrisStringForm = useForm({
        qris_string: qrisString || '',
    });

    // Check token validity
    const checkTokenValidity = async () => {
        setChecking(true);
        try {
            const response = await fetch('/orderkuota-tools/check-token');
            const data = await response.json();
            setTokenStatus(data);
        } catch (error) {
            setTokenStatus({ valid: false, message: 'Connection error' });
        }
        setChecking(false);
    };

    // Request OTP
    const handleRequestOtp = (e) => {
        e.preventDefault();
        loginForm.post('/orderkuota-tools/request-otp', {
            onSuccess: () => setShowOtpForm(true),
        });
    };

    // Verify OTP
    const handleVerifyOtp = (e) => {
        e.preventDefault();
        otpForm.post('/orderkuota-tools/verify-otp', {
            onSuccess: () => {
                setShowOtpForm(false);
                checkTokenValidity();
            },
        });
    };

    // Save QRIS string
    const handleSaveQrisString = (e) => {
        e.preventDefault();
        qrisStringForm.post('/orderkuota-tools/save-qris-string');
    };

    // Auto check token on mount
    useEffect(() => {
        if (hasCredentials) {
            checkTokenValidity();
        }
    }, [hasCredentials]);

    return (
        <DefaultLayout user={auth?.user}>
            <Head title="Order Kuota Tools" />

            {/* Header */}
            <div className="mb-6">
                <h1 className="text-3xl font-black text-gray-900">Order Kuota Tools</h1>
                <p className="text-gray-600 mt-1">Configure Order Kuota payment gateway integration</p>
            </div>

            {/* Flash Messages */}
            {flash?.success && (
                <div className="neo-card bg-green-50 border-green-500 p-4 mb-6">
                    <p className="text-green-800 font-semibold">{flash.success}</p>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Left Column - How to Use */}
                <div className="lg:col-span-1">
                    <div className="neo-card p-6">
                        <h3 className="font-bold text-lg mb-4">How to Use</h3>
                        <ol className="space-y-4 text-sm text-gray-700">
                            <li className="flex gap-3">
                                <span className="flex-shrink-0 w-6 h-6 bg-[#8B5CF6] text-white rounded-full flex items-center justify-center font-bold text-xs">1</span>
                                <div>
                                    <p className="font-semibold">Get QRIS String</p>
                                    <p className="text-gray-500">Open Order Kuota app, go to QRIS section, screenshot your QR code, then use
                                        <a href="https://www.imagetotext.info/qr-code-scanner" target="_blank" className="text-[#8B5CF6] underline ml-1">QR Scanner</a> to get the string.
                                    </p>
                                </div>
                            </li>
                            <li className="flex gap-3">
                                <span className="flex-shrink-0 w-6 h-6 bg-[#8B5CF6] text-white rounded-full flex items-center justify-center font-bold text-xs">2</span>
                                <div>
                                    <p className="font-semibold">Login & Get Token</p>
                                    <p className="text-gray-500">Enter your Order Kuota username and password, verify OTP sent to your email.</p>
                                </div>
                            </li>
                            <li className="flex gap-3">
                                <span className="flex-shrink-0 w-6 h-6 bg-[#8B5CF6] text-white rounded-full flex items-center justify-center font-bold text-xs">3</span>
                                <div>
                                    <p className="font-semibold">Configure Gateway</p>
                                    <p className="text-gray-500">Go to <a href="/payment-gateways" className="text-[#8B5CF6] underline">Payment Gateways</a>, configure Order Kuota with your saved credentials.</p>
                                </div>
                            </li>
                            <li className="flex gap-3">
                                <span className="flex-shrink-0 w-6 h-6 bg-[#8B5CF6] text-white rounded-full flex items-center justify-center font-bold text-xs">4</span>
                                <div>
                                    <p className="font-semibold">Assign to Bot</p>
                                    <p className="text-gray-500">Assign Order Kuota gateway to your bot. Payments will be auto-detected via polling.</p>
                                </div>
                            </li>
                        </ol>

                        <div className="mt-6 p-3 bg-yellow-50 border-2 border-yellow-300 rounded-lg">
                            <p className="text-sm text-yellow-800">
                                <span className="font-bold">Note:</span> Orkut memiliki batas maksimum 10 login perangkat. Hindari meminta OTP baru jika token saat ini masih berlaku.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Right Column - Configuration */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Token Status */}
                    <div className="neo-card p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="font-bold text-lg">Token Status</h3>
                            <button
                                onClick={checkTokenValidity}
                                disabled={checking}
                                className="px-4 py-2 font-semibold text-sm bg-[#8B5CF6] text-white border-2 border-black rounded-lg shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] hover:shadow-none hover:translate-x-0.5 hover:translate-y-0.5 transition-all disabled:opacity-50"
                            >
                                {checking ? 'Checking...' : 'Check Token'}
                            </button>
                        </div>

                        <div className="p-4 border-2 border-black rounded-lg bg-gray-50">
                            {checking ? (
                                <p className="text-gray-500">Checking token validity...</p>
                            ) : tokenStatus ? (
                                <div>
                                    <p className={`font-semibold ${tokenStatus.valid ? 'text-green-600' : 'text-red-600'}`}>
                                        Status: {tokenStatus.valid ? 'Valid' : 'Invalid'}
                                    </p>
                                    <p className="text-sm text-gray-600 mt-1">{tokenStatus.message}</p>
                                    {tokenStatus.balance && (
                                        <p className="text-sm text-gray-600">Balance: Rp {parseInt(tokenStatus.balance).toLocaleString()}</p>
                                    )}
                                </div>
                            ) : hasCredentials ? (
                                <p className="text-gray-500">Click "Check Token" to verify your token</p>
                            ) : (
                                <p className="text-gray-500">No token configured. Please login below.</p>
                            )}

                            {currentUsername && (
                                <p className="text-sm text-gray-500 mt-2">Username: {currentUsername}</p>
                            )}
                            {tokenSavedAt && (
                                <p className="text-sm text-gray-500">Token saved: {new Date(tokenSavedAt).toLocaleString()}</p>
                            )}
                        </div>
                    </div>

                    {/* Login Form */}
                    <div className="neo-card p-6">
                        <h3 className="font-bold text-lg mb-4">
                            {showOtpForm ? 'Verify OTP' : 'Login Order Kuota'}
                        </h3>

                        {!showOtpForm ? (
                            <form onSubmit={handleRequestOtp} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-semibold mb-2">Username</label>
                                    <input
                                        type="text"
                                        value={loginForm.data.username}
                                        onChange={(e) => loginForm.setData('username', e.target.value)}
                                        className="neo-input"
                                        placeholder="Your Order Kuota username"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-semibold mb-2">Password</label>
                                    <input
                                        type="password"
                                        value={loginForm.data.password}
                                        onChange={(e) => loginForm.setData('password', e.target.value)}
                                        className="neo-input"
                                        placeholder="Your Order Kuota password"
                                    />
                                </div>
                                {loginForm.errors.login && (
                                    <p className="text-red-600 text-sm">{loginForm.errors.login}</p>
                                )}
                                <button
                                    type="submit"
                                    disabled={loginForm.processing}
                                    className="neo-btn-primary"
                                >
                                    {loginForm.processing ? 'Sending OTP...' : 'Request OTP'}
                                </button>
                            </form>
                        ) : (
                            <form onSubmit={handleVerifyOtp} className="space-y-4">
                                <p className="text-gray-600 mb-4">OTP code has been sent to your email. Check your inbox.</p>
                                <div>
                                    <label className="block text-sm font-semibold mb-2">OTP Code</label>
                                    <input
                                        type="text"
                                        value={otpForm.data.otp}
                                        onChange={(e) => otpForm.setData('otp', e.target.value)}
                                        className="neo-input"
                                        placeholder="Enter 5-digit OTP"
                                        maxLength={5}
                                    />
                                </div>
                                {otpForm.errors.otp && (
                                    <p className="text-red-600 text-sm">{otpForm.errors.otp}</p>
                                )}
                                <div className="flex gap-3">
                                    <button
                                        type="submit"
                                        disabled={otpForm.processing}
                                        className="neo-btn-primary"
                                    >
                                        {otpForm.processing ? 'Verifying...' : 'Verify OTP'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setShowOtpForm(false)}
                                        className="neo-btn-secondary"
                                    >
                                        Back
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>

                    {/* QRIS String */}
                    <div className="neo-card p-6">
                        <h3 className="font-bold text-lg mb-2">QRIS String</h3>
                        <p className="text-sm text-gray-600 mb-4">
                            Paste your static QRIS string from Order Kuota.
                            Use <a href="https://www.imagetotext.info/qr-code-scanner" target="_blank" className="text-[#8B5CF6] underline">QR Scanner</a> to extract string from QR image.
                        </p>

                        <form onSubmit={handleSaveQrisString} className="space-y-4">
                            <div>
                                <textarea
                                    value={qrisStringForm.data.qris_string}
                                    onChange={(e) => qrisStringForm.setData('qris_string', e.target.value)}
                                    className="neo-input min-h-[100px] font-mono text-xs"
                                    placeholder="00020101021126670016COM.NOBUBANK.WWW..."
                                />
                            </div>
                            <button
                                type="submit"
                                disabled={qrisStringForm.processing}
                                className="neo-btn-primary"
                            >
                                {qrisStringForm.processing ? 'Saving...' : 'Save QRIS String'}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </DefaultLayout>
    );
}
