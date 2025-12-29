import { Head, Link, useForm, router } from '@inertiajs/react';
import DefaultLayout from '@/Layouts/DefaultLayout';
import { useState } from 'react';
import { IconCreditCard } from '@/Components/Icons';

export default function ConfigureGateway({ auth, gateway, userGateway, bots = [] }) {
    const [showCredentials, setShowCredentials] = useState({});

    const { data, setData, post, processing, errors } = useForm({
        gateway_id: gateway?.id,
        credentials: userGateway?.credentials || {},
        label: userGateway?.label || '',
    });

    // Parse required_fields - can be array or object
    const parseRequiredFields = () => {
        const rf = gateway?.required_fields;
        if (!rf) return [];

        // If it's a string, parse it first
        let parsed = rf;
        if (typeof rf === 'string') {
            try {
                parsed = JSON.parse(rf);
            } catch (e) {
                return [];
            }
        }

        // If it's an array, return as-is
        if (Array.isArray(parsed)) return parsed;

        // If it's an object, return the keys
        if (typeof parsed === 'object') return Object.keys(parsed);

        return [];
    };

    const requiredFields = parseRequiredFields();

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/payment-gateways');
    };

    const handleAssignToBot = (botId) => {
        if (!userGateway) {
            alert('Please save gateway configuration first');
            return;
        }
        router.post('/payment-gateways/assign', {
            bot_id: botId,
            user_gateway_id: userGateway.id,
        });
    };

    const toggleShowCredential = (field) => {
        setShowCredentials(prev => ({
            ...prev,
            [field]: !prev[field]
        }));
    };

    if (!gateway) {
        return (
            <DefaultLayout user={auth?.user}>
                <div className="py-12 text-center">
                    <p className="text-gray-500">Gateway not found</p>
                </div>
            </DefaultLayout>
        );
    }

    return (
        <DefaultLayout user={auth?.user}>
            <Head title={`Configure ${gateway.name}`} />

            {/* Back Button */}
            <Link
                href="/payment-gateways"
                className="inline-flex items-center text-sm font-medium text-[#8B5CF6] hover:underline mb-6"
            >
                ← Back to Gateways
            </Link>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Gateway Info Card */}
                <div className="lg:col-span-1">
                    <div className="neo-card p-6">
                        <div className="flex items-center gap-3 mb-4">
                            <div className="w-14 h-14 bg-white border border-gray-200 rounded-lg flex items-center justify-center overflow-hidden">
                                {gateway.logo ? (
                                    <img
                                        src={gateway.logo}
                                        alt={gateway.name}
                                        className="w-full h-full object-contain p-2"
                                        onError={(e) => {
                                            e.target.style.display = 'none';
                                            e.target.nextSibling.style.display = 'block';
                                        }}
                                    />
                                ) : null}
                                <IconCreditCard className={`w-7 h-7 text-[#8B5CF6] ${gateway.logo ? 'hidden' : ''}`} />
                            </div>
                            <div>
                                <h3 className="font-bold text-xl text-gray-900">{gateway.name}</h3>
                                <p className="text-sm text-gray-500">{gateway.code}</p>
                            </div>
                        </div>

                        <p className="text-gray-600 text-sm mb-4">{gateway.description}</p>

                        <div className="border-t-2 border-gray-100 pt-4">
                            <h4 className="text-sm font-bold text-gray-900 mb-2">Fee Structure</h4>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Percentage</span>
                                <span className="font-semibold text-gray-900">{Number(gateway.fee_percent)}%</span>
                            </div>
                            {gateway.fee_flat > 0 && (
                                <div className="flex justify-between text-sm mt-1">
                                    <span className="text-gray-500">Flat Fee</span>
                                    <span className="font-semibold text-gray-900">Rp {Number(gateway.fee_flat).toLocaleString()}</span>
                                </div>
                            )}
                        </div>

                        {/* Status */}
                        <div className="border-t-2 border-gray-100 pt-4 mt-4">
                            <div className="flex justify-between items-center">
                                <span className="text-sm text-gray-500">Status</span>
                                <span className={userGateway ? 'neo-badge-success' : 'neo-badge-gray'}>
                                    {userGateway ? 'Configured' : 'Not configured'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Configuration Form */}
                <div className="lg:col-span-2 space-y-6">
                    <form onSubmit={handleSubmit} className="neo-card p-6">
                        <h3 className="text-lg font-bold text-gray-900 mb-6">API Credentials</h3>

                        {/* Label */}
                        <div className="mb-5">
                            <label className="block text-sm font-semibold text-gray-900 mb-2">
                                Label (Optional)
                            </label>
                            <input
                                type="text"
                                value={data.label}
                                onChange={e => setData('label', e.target.value)}
                                placeholder="e.g., Main Store, Test Account"
                                className="neo-input"
                            />
                            <p className="mt-1 text-xs text-gray-500">A friendly name to identify this configuration</p>
                        </div>

                        {/* Dynamic Credential Fields */}
                        {requiredFields.map((field) => (
                            <div key={field} className="mb-5">
                                <label className="block text-sm font-semibold text-gray-900 mb-2">
                                    {field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                                    <span className="text-red-500 ml-1">*</span>
                                </label>
                                <div className="relative">
                                    <input
                                        type={showCredentials[field] ? 'text' : 'password'}
                                        value={data.credentials[field] || ''}
                                        onChange={e => setData('credentials', {
                                            ...data.credentials,
                                            [field]: e.target.value
                                        })}
                                        placeholder={`Enter your ${field.replace(/_/g, ' ')}`}
                                        className="neo-input pr-12"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => toggleShowCredential(field)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                                    >
                                        {showCredentials[field] ? (
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
                                {errors[field] && (
                                    <p className="mt-1 text-sm text-red-600">{errors[field]}</p>
                                )}
                            </div>
                        ))}

                        <button
                            type="submit"
                            disabled={processing}
                            className="neo-btn-primary w-full"
                        >
                            {processing ? 'Saving...' : (userGateway ? 'Update Configuration' : 'Save Configuration')}
                        </button>
                    </form>

                    {/* Assign to Bots */}
                    {userGateway && bots.length > 0 && (
                        <div className="neo-card p-6">
                            <h3 className="text-lg font-bold text-gray-900 mb-2">Assign to Bots</h3>
                            <p className="text-sm text-gray-500 mb-4">
                                Select which bots should use this payment gateway
                            </p>

                            <div className="space-y-3">
                                {bots.map((bot) => (
                                    <div
                                        key={bot.id}
                                        className="flex items-center justify-between p-4 border-2 border-gray-200 rounded-lg"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className={`w-3 h-3 rounded-full ${bot.status === 'active' ? 'bg-green-500' : 'bg-gray-300'
                                                }`}></div>
                                            <div>
                                                <p className="font-semibold text-gray-900">{bot.name}</p>
                                                {bot.bot_username ? (
                                                    <p className="text-xs text-gray-500">@{bot.bot_username}</p>
                                                ) : (
                                                    <p className="text-xs text-gray-400 italic">No username</p>
                                                )}
                                            </div>
                                        </div>

                                        {bot.active_gateway_id === userGateway.id ? (
                                            <span className="neo-badge-success">
                                                ✓ Using this gateway
                                            </span>
                                        ) : (
                                            <button
                                                onClick={() => handleAssignToBot(bot.id)}
                                                className="px-4 py-2 text-sm font-semibold text-gray-700 border-2 border-gray-300 rounded-lg hover:bg-gray-100 transition-colors"
                                            >
                                                Assign
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* No bots message */}
                    {userGateway && bots.length === 0 && (
                        <div className="neo-card p-6 text-center">
                            <p className="text-gray-500 mb-4">No bots created yet</p>
                            <Link href="/bots/create" className="neo-btn-primary">
                                Create a Bot
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </DefaultLayout>
    );
}
