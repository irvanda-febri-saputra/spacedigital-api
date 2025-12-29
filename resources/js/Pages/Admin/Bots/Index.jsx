import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DefaultLayout from '@/Layouts/DefaultLayout';
import { IconBot, IconCheckCircle, IconClock, IconChart } from '@/Components/Icons';
import { Skeleton, StatCardSkeleton, TableRowSkeleton } from '@/Components/Skeleton';

const StatusBadge = ({ status }) => {
    const styles = {
        active: 'neo-badge-success',
        inactive: 'neo-badge-gray',
        suspended: 'bg-red-100 text-red-700 px-2.5 py-1 text-xs font-semibold rounded-full border border-red-300',
    };
    
    return (
        <span className={styles[status] || 'neo-badge-gray'}>
            {status?.charAt(0).toUpperCase() + status?.slice(1)}
        </span>
    );
};

const StatCard = ({ title, value, icon: Icon, color }) => (
    <div className="neo-card p-5">
        <div className="flex items-center justify-between">
            <div>
                <p className="text-sm text-gray-500">{title}</p>
                <p className={`text-2xl font-bold ${color}`}>{value}</p>
            </div>
            <div className="w-10 h-10 bg-[#8B5CF6]/10 rounded-lg flex items-center justify-center">
                <Icon className="w-5 h-5 text-[#8B5CF6]" />
            </div>
        </div>
    </div>
);

export default function AdminBotsIndex({ auth, bots, stats, filters }) {
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || '');

    // Initial loading delay
    useEffect(() => {
        const timer = setTimeout(() => setLoading(false), 600);
        return () => clearTimeout(timer);
    }, []);

    const applyFilters = () => {
        router.get('/admin/bots', {
            search: search || undefined,
            status: status || undefined,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('');
        router.get('/admin/bots');
    };

    const handleToggleStatus = (botId, currentStatus) => {
        const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
        if (confirm(`Change bot status to ${newStatus}?`)) {
            router.put(`/admin/bots/${botId}/status`, { status: newStatus });
        }
    };

    const handleDelete = (botId, botName) => {
        if (confirm(`Delete bot "${botName}"? This action cannot be undone!`)) {
            router.delete(`/admin/bots/${botId}`);
        }
    };

    // Loading skeleton
    if (loading) {
        return (
            <DefaultLayout user={auth?.user}>
                <Head title="All Bots" />
                <div className="space-y-6">
                    <div className="mb-6">
                        <Skeleton className="h-8 w-32 mb-2" />
                        <Skeleton className="h-5 w-64" />
                    </div>
                    
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <StatCardSkeleton />
                        <StatCardSkeleton />
                        <StatCardSkeleton />
                        <StatCardSkeleton />
                    </div>
                    
                    <div className="neo-card p-4">
                        <div className="flex gap-4">
                            <Skeleton className="h-10 flex-1 rounded-lg" />
                            <Skeleton className="h-10 w-32 rounded-lg" />
                            <Skeleton className="h-10 w-20 rounded-lg" />
                        </div>
                    </div>
                    
                    <div className="neo-card overflow-hidden">
                        <table className="w-full">
                            <thead className="bg-gray-100 border-b-3 border-gray-900">
                                <tr>
                                    {[...Array(7)].map((_, i) => (
                                        <th key={i} className="px-4 py-3">
                                            <Skeleton className="h-4 w-16" />
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {[...Array(6)].map((_, i) => (
                                    <TableRowSkeleton key={i} columns={7} />
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </DefaultLayout>
        );
    }

    return (
        <DefaultLayout user={auth?.user}>
            <Head title="All Bots" />

            {/* Header */}
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    <IconBot className="w-6 h-6 text-[#8B5CF6]" /> All Bots
                </h1>
                <p className="text-gray-500 mt-1">
                    View and manage all bots across users
                </p>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <StatCard title="Total Bots" value={stats?.total || 0} icon={IconBot} color="text-gray-900" />
                <StatCard title="Active" value={stats?.active || 0} icon={IconCheckCircle} color="text-green-600" />
                <StatCard title="Inactive" value={stats?.inactive || 0} icon={IconClock} color="text-gray-600" />
                <StatCard title="Total Transactions" value={stats?.transactions || 0} icon={IconChart} color="text-[#8B5CF6]" />
            </div>

            {/* Filters */}
            <div className="neo-card p-4 mb-6">
                <div className="flex flex-wrap items-end gap-4">
                    <div className="flex-1 min-w-[200px]">
                        <label className="block text-sm font-semibold text-gray-900 mb-2">Search</label>
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                            placeholder="Bot name or username..."
                            className="neo-input"
                        />
                    </div>
                    <div className="w-36">
                        <label className="block text-sm font-semibold text-gray-900 mb-2">Status</label>
                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className="neo-input"
                        >
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div className="flex gap-2">
                        <button onClick={applyFilters} className="neo-btn-primary text-sm">
                            Filter
                        </button>
                        <button onClick={clearFilters} className="neo-btn-secondary text-sm">
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            {/* Table */}
            <div className="neo-table overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead className="bg-gray-100 border-b-3 border-gray-900">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-bold text-gray-900 uppercase">Bot</th>
                                <th className="px-4 py-3 text-left text-xs font-bold text-gray-900 uppercase">Owner</th>
                                <th className="px-4 py-3 text-left text-xs font-bold text-gray-900 uppercase">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-bold text-gray-900 uppercase">Transactions</th>
                                <th className="px-4 py-3 text-left text-xs font-bold text-gray-900 uppercase">Revenue</th>
                                <th className="px-4 py-3 text-left text-xs font-bold text-gray-900 uppercase">Created</th>
                                <th className="px-4 py-3 text-right text-xs font-bold text-gray-900 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {(!bots?.data || bots.data.length === 0) ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-12 text-center text-gray-500">
                                        <div className="w-12 h-12 mx-auto mb-2 bg-gray-100 rounded-xl flex items-center justify-center">
                                            <IconBot className="w-6 h-6 text-gray-400" />
                                        </div>
                                        No bots found
                                    </td>
                                </tr>
                            ) : (
                                bots.data.map((bot) => (
                                    <tr key={bot.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-4">
                                            <div className="flex items-center gap-3">
                                                <div className="w-10 h-10 rounded-lg bg-[#8B5CF6]/10 flex items-center justify-center">
                                                    <IconBot className="w-5 h-5 text-[#8B5CF6]" />
                                                </div>
                                                <div>
                                                    <p className="font-semibold text-gray-900">{bot.name}</p>
                                                    <p className="text-xs text-gray-500">@{bot.bot_username}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-4">
                                            <div>
                                                <p className="font-medium text-gray-900">{bot.user?.name}</p>
                                                <p className="text-xs text-gray-500">{bot.user?.email}</p>
                                            </div>
                                        </td>
                                        <td className="px-4 py-4">
                                            <StatusBadge status={bot.status} />
                                        </td>
                                        <td className="px-4 py-4 text-gray-600">
                                            {bot.transactions_count || 0}
                                        </td>
                                        <td className="px-4 py-4 font-semibold text-gray-900">
                                            Rp {Number(bot.total_revenue || 0).toLocaleString('id-ID')}
                                        </td>
                                        <td className="px-4 py-4 text-sm text-gray-500">
                                            {bot.created_at}
                                        </td>
                                        <td className="px-4 py-4">
                                            <div className="flex items-center justify-end gap-2">
                                                <button
                                                    onClick={() => handleToggleStatus(bot.id, bot.status)}
                                                    className={`px-3 py-1.5 text-xs font-semibold rounded-lg border-2 transition-colors ${
                                                        bot.status === 'active' 
                                                            ? 'text-yellow-600 border-yellow-600 hover:bg-yellow-50'
                                                            : 'text-green-600 border-green-600 hover:bg-green-50'
                                                    }`}
                                                >
                                                    {bot.status === 'active' ? 'Deactivate' : 'Activate'}
                                                </button>
                                                <button
                                                    onClick={() => handleDelete(bot.id, bot.name)}
                                                    className="px-3 py-1.5 text-xs font-semibold text-red-600 border-2 border-red-600 rounded-lg hover:bg-red-50 transition-colors"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {bots?.last_page > 1 && (
                    <div className="px-4 py-3 border-t-2 border-gray-200 flex items-center justify-between">
                        <p className="text-sm text-gray-500">
                            Showing {bots.from} to {bots.to} of {bots.total}
                        </p>
                        <div className="flex gap-2">
                            {bots.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url || '#'}
                                    className={`px-3 py-1 rounded-lg text-sm font-semibold border-2 ${
                                        link.active
                                            ? 'bg-[#8B5CF6] text-white border-[#8B5CF6]'
                                            : link.url
                                            ? 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                                            : 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </DefaultLayout>
    );
}
