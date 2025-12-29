import { useState } from 'react';
import { Head, useForm, Link, router } from '@inertiajs/react';
import DefaultLayout from '@/Layouts/DefaultLayout';
import { IconBot, IconCreditCard, IconEye, IconEyeOff } from '@/Components/Icons';
import { useToast, TOAST_MESSAGES } from '@/Components/Toast';

export default function EditBot({ auth, bot, userGateways = [] }) {
    const toast = useToast();
    const showToast = toast?.showToast || (() => {});
    const [showToken, setShowToken] = useState(false);
    
    const { data, setData, put, processing, errors } = useForm({
        name: bot.name || '',
        bot_token: bot.bot_token || '',
        bot_username: bot.bot_username || '',
        user_gateway_id: bot.user_gateway_id || '',
        status: bot.status || 'active',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/bots/${bot.id}`, {
            onSuccess: () => {
                showToast(TOAST_MESSAGES.SAVED, 'success');
            }
        });
    };

    const statuses = [
        { value: 'active', label: 'Active', color: 'text-green-600' },
        { value: 'inactive', label: 'Inactive', color: 'text-gray-600' },
        { value: 'suspended', label: 'Suspended', color: 'text-red-600' },
    ];

    return (
        <DefaultLayout user={auth?.user}>
            <Head title={`Edit ${bot.name}`} />

            {/* Header */}
            <div className="mb-6">
                <Link
                    href="/bots"
                    className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 mb-4"
                >
                    ← Back to Bot Settings
                </Link>
                <h1 className="text-2xl font-bold text-gray-800 dark:text-white">
                    Edit Bot: {bot.name}
                </h1>
                <p className="text-gray-500 dark:text-gray-400 mt-1">
                    Update your bot configuration
                </p>
            </div>

            <form onSubmit={handleSubmit} className="max-w-2xl">
                {/* Bot Information */}
                <div className="neo-card p-6 mb-6">
                    <h2 className="text-lg font-semibold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                        <IconBot className="w-5 h-5 text-[#8B5CF6]" /> Bot Information
                    </h2>
                    
                    <div className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Bot Name *
                            </label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="neo-input"
                                required
                            />
                            {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Bot Username
                            </label>
                            <div className="relative">
                                <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">@</span>
                                <input
                                    type="text"
                                    value={data.bot_username}
                                    onChange={(e) => setData('bot_username', e.target.value)}
                                    className="neo-input pl-8"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Bot Token *
                            </label>
                            <div className="relative">
                                <input
                                    type={showToken ? 'text' : 'password'}
                                    value={data.bot_token}
                                    onChange={(e) => setData('bot_token', e.target.value)}
                                    className="neo-input pr-12 font-mono text-sm"
                                    required
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowToken(!showToken)}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    {showToken ? <IconEyeOff className="w-5 h-5" /> : <IconEye className="w-5 h-5" />}
                                </button>
                            </div>
                            {errors.bot_token && <p className="mt-1 text-sm text-red-500">{errors.bot_token}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Status
                            </label>
                            <select
                                value={data.status}
                                onChange={(e) => setData('status', e.target.value)}
                                className="neo-input"
                            >
                                {statuses.map((s) => (
                                    <option key={s.value} value={s.value}>{s.label}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                </div>

                {/* Payment Gateway Selection */}
                <div className="neo-card p-6 mb-6">
                    <h2 className="text-lg font-semibold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
                        <IconCreditCard className="w-5 h-5 text-[#8B5CF6]" /> Payment Gateway
                    </h2>
                    
                    <div className="space-y-4">
                        {userGateways.length === 0 ? (
                            <div className="bg-yellow-50 border-2 border-yellow-200 rounded-lg p-4">
                                <p className="text-yellow-700 font-medium mb-2">No Payment Gateway Configured</p>
                                <p className="text-sm text-yellow-600 mb-3">
                                    Please configure a payment gateway first before setting up your bot.
                                </p>
                                <Link
                                    href="/payment-gateways"
                                    className="inline-flex items-center gap-1 text-sm font-semibold text-[#8B5CF6] hover:underline"
                                >
                                    Configure Payment Gateway →
                                </Link>
                            </div>
                        ) : (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Select Payment Gateway *
                                </label>
                                <select
                                    value={data.user_gateway_id}
                                    onChange={(e) => setData('user_gateway_id', e.target.value)}
                                    className="neo-input"
                                    required
                                >
                                    <option value="">-- Select Gateway --</option>
                                    {userGateways.map((gw) => (
                                        <option key={gw.id} value={gw.id}>
                                            {(gw.gateway_type || 'Unknown').toUpperCase()} - {gw.merchant_code || gw.gateway_type || 'N/A'}
                                        </option>
                                    ))}
                                </select>
                                {errors.user_gateway_id && <p className="mt-1 text-sm text-red-500">{errors.user_gateway_id}</p>}
                                
                                <p className="mt-2 text-xs text-gray-500">
                                    Your credentials are securely stored in Payment Gateways.{' '}
                                    <Link href="/payment-gateways" className="text-[#8B5CF6] hover:underline">
                                        Manage Gateways
                                    </Link>
                                </p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Submit */}
                <div className="flex items-center gap-4">
                    <button
                        type="submit"
                        disabled={processing || userGateways.length === 0}
                        className="neo-btn-primary"
                    >
                        {processing ? 'Saving...' : 'Save Changes'}
                    </button>
                    <Link
                        href="/bots"
                        className="neo-btn-secondary"
                    >
                        Cancel
                    </Link>
                </div>
            </form>
        </DefaultLayout>
    );
}
