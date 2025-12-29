import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DefaultLayout from '@/Layouts/DefaultLayout';
import { IconBot } from '@/Components/Icons';
import { Skeleton, BotCardSkeleton } from '@/Components/Skeleton';

const StatusBadge = ({ status }) => {
    const colors = {
        active: 'neo-badge-success',
        inactive: 'neo-badge-gray',
        suspended: 'neo-badge-danger',
    };

    return (
        <span className={colors[status] || colors.inactive}>
            {status.charAt(0).toUpperCase() + status.slice(1)}
        </span>
    );
};

export default function BotsIndex({ auth, bots }) {
    const [loading, setLoading] = useState(true);

    // Initial loading delay
    useEffect(() => {
        const timer = setTimeout(() => setLoading(false), 600);
        return () => clearTimeout(timer);
    }, []);

    const handleDelete = (botId, botName) => {
        if (confirm(`Are you sure you want to delete "${botName}"?`)) {
            router.delete(`/bots/${botId}`);
        }
    };

    // Loading skeleton
    if (loading) {
        return (
            <DefaultLayout user={auth?.user}>
                <Head title="My Bots" />
                <div className="space-y-6">
                    <div className="flex justify-between items-center mb-6">
                        <div>
                            <Skeleton className="h-8 w-28 mb-2" />
                            <Skeleton className="h-5 w-48" />
                        </div>
                        <Skeleton className="h-10 w-32 rounded-lg" />
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <BotCardSkeleton />
                        <BotCardSkeleton />
                        <BotCardSkeleton />
                    </div>
                </div>
            </DefaultLayout>
        );
    }

    return (
        <DefaultLayout user={auth?.user}>
            <Head title="My Bots" />

            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">My Bots</h1>
                    <p className="text-gray-400 mt-1">Manage your Telegram bots</p>
                </div>
                <Link href="/bots/create" className="neo-btn-primary inline-flex items-center gap-2 text-sm">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                    </svg>
                    Add New Bot
                </Link>
            </div>

            {/* Bots Grid */}
            {bots.length === 0 ? (
                <div className="neo-card p-12 text-center">
                    <div className="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-xl flex items-center justify-center">
                        <IconBot className="w-8 h-8 text-gray-400" />
                    </div>
                    <h3 className="text-xl font-bold text-gray-900 mb-2">No bots yet</h3>
                    <p className="text-gray-500 mb-6">Create your first bot to get started</p>
                    <Link href="/bots/create" className="neo-btn-primary">Create Bot</Link>
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    {bots.map((bot) => (
                        <div key={bot.id} className="neo-card overflow-hidden">
                            {/* Card Header */}
                            <div className="p-6 border-b-2 border-gray-100">
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className="w-12 h-12 rounded-lg bg-[#8B5CF6]/10 flex items-center justify-center">
                                            <IconBot className="w-6 h-6 text-[#8B5CF6]" />
                                        </div>
                                        <div>
                                            <h3 className="font-bold text-gray-900">{bot.name}</h3>
                                            {bot.bot_username && (
                                                <p className="text-sm text-gray-500">@{bot.bot_username}</p>
                                            )}
                                        </div>
                                    </div>
                                    <StatusBadge status={bot.status} />
                                </div>
                            </div>

                            {/* Card Body */}
                            <div className="p-6 space-y-3 bg-gray-50">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-semibold text-gray-500">Bot ID</span>
                                    <span className="text-sm font-mono font-bold text-[#8B5CF6] bg-white px-2 py-1 rounded border border-[#8B5CF6]/30">
                                        #{bot.id}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-semibold text-gray-500">Gateway</span>
                                    {bot.active_gateway ? (
                                        <div className="flex items-center gap-2 px-2 py-1 bg-white border border-gray-200 rounded-lg">
                                            {bot.active_gateway.logo && (
                                                <img
                                                    src={bot.active_gateway.logo}
                                                    alt={bot.active_gateway.name}
                                                    className="w-4 h-4 object-contain"
                                                    onError={(e) => e.target.style.display = 'none'}
                                                />
                                            )}
                                            <span className="text-sm font-medium text-gray-900">
                                                {bot.active_gateway.name}
                                            </span>
                                        </div>
                                    ) : (
                                        <span className="px-2 py-1 text-xs font-semibold text-gray-500 bg-gray-100 rounded-lg">
                                            Not Configured
                                        </span>
                                    )}
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-semibold text-gray-500">Token</span>
                                    <span className="text-sm font-mono text-gray-600 bg-white px-2 py-1 rounded border border-gray-200">
                                        {bot.masked_token}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-semibold text-gray-500">Created</span>
                                    <span className="text-sm text-gray-600">{bot.created_at}</span>
                                </div>
                            </div>

                            {/* Card Footer */}
                            <div className="px-6 py-4 bg-white border-t-2 border-gray-100 flex justify-end gap-2">
                                <Link
                                    href={`/bots/${bot.id}/edit`}
                                    className="neo-btn-outline-primary"
                                >
                                    Edit
                                </Link>
                                <button
                                    onClick={() => handleDelete(bot.id, bot.name)}
                                    className="neo-btn-outline-danger"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </DefaultLayout>
    );
}
