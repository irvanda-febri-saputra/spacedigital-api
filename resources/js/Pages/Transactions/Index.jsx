import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DefaultLayout from '@/Layouts/DefaultLayout';
import { Skeleton, StatCardSkeleton, TableRowSkeleton } from '@/Components/Skeleton';

const StatusBadge = ({ status }) => {
    const colors = {
        success: 'neo-badge-success',
        pending: 'neo-badge-warning',
        expired: 'neo-badge-gray',
        failed: 'neo-badge-danger',
    };

    return (
        <span className={colors[status] || colors.pending}>
            {status.charAt(0).toUpperCase() + status.slice(1)}
        </span>
    );
};

export default function TransactionsIndex({ auth, transactions, stats, bots, filters }) {
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || '');
    const [botId, setBotId] = useState(filters?.bot_id || '');

    // Initial loading delay
    useEffect(() => {
        const timer = setTimeout(() => setLoading(false), 600);
        return () => clearTimeout(timer);
    }, []);

    // Auto-refresh periodically
    useEffect(() => {
        const interval = setInterval(() => {
            router.reload({
                only: ['transactions', 'stats'],
                preserveScroll: true,
                preserveState: true,
            });
        }, 5000); // 5 seconds

        return () => clearInterval(interval);
    }, []);

    const applyFilters = () => {
        router.get('/transactions', {
            search: search || undefined,
            status: status || undefined,
            bot_id: botId || undefined,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('');
        setBotId('');
        router.get('/transactions');
    };

    const handleExport = () => {
        const params = new URLSearchParams({
            search: search || '',
            status: status || '',
            bot_id: botId || '',
        });
        window.location.href = `/transactions/export?${params.toString()}`;
    };

    const formatCurrency = (value) => {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(value);
    };

    // Loading skeleton
    if (loading) {
        return (
            <DefaultLayout user={auth?.user}>
                <Head title="Transactions" />
                <div className="space-y-6">
                    {/* Header Skeleton */}
                    <div className="mb-6 flex justify-between items-end">
                        <div>
                            <Skeleton className="h-8 w-40 mb-2" />
                            <Skeleton className="h-5 w-64" />
                        </div>
                        <Skeleton className="h-10 w-28 rounded-lg" />
                    </div>

                    {/* Stats Skeleton */}
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <StatCardSkeleton />
                        <StatCardSkeleton />
                        <StatCardSkeleton />
                        <StatCardSkeleton />
                    </div>

                    {/* Table Skeleton */}
                    <div className="neo-card overflow-hidden">
                        <div className="p-4 border-b-2 border-gray-100">
                            <div className="flex gap-4">
                                <Skeleton className="h-10 flex-1 rounded-lg" />
                                <Skeleton className="h-10 w-32 rounded-lg" />
                                <Skeleton className="h-10 w-32 rounded-lg" />
                            </div>
                        </div>
                        <table className="w-full">
                            <thead className="bg-gray-50 border-b-2 border-gray-900">
                                <tr>
                                    <th className="px-4 py-3"><Skeleton className="h-4 w-20" /></th>
                                    <th className="px-4 py-3"><Skeleton className="h-4 w-24" /></th>
                                    <th className="px-4 py-3"><Skeleton className="h-4 w-16" /></th>
                                    <th className="px-4 py-3"><Skeleton className="h-4 w-16" /></th>
                                    <th className="px-4 py-3"><Skeleton className="h-4 w-20" /></th>
                                </tr>
                            </thead>
                            <tbody>
                                {[...Array(8)].map((_, i) => (
                                    <TableRowSkeleton key={i} columns={5} />
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
            <Head title="Transactions" />

            {/* Header */}
            <div className="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Transactions</h1>
                    <p className="text-gray-400 mt-1">Monitor all transactions from your bots</p>
                </div>
                <button onClick={handleExport} className="neo-btn-secondary text-sm">
                    Export CSV
                </button>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div className="neo-stat-card">
                    <p className="text-sm font-semibold text-gray-500 uppercase">Total</p>
                    <p className="text-2xl font-bold text-gray-900 mt-1">{stats.total}</p>
                </div>
                <div className="neo-stat-card">
                    <p className="text-sm font-semibold text-gray-500 uppercase">Success</p>
                    <p className="text-2xl font-bold text-green-600 mt-1">{stats.success}</p>
                </div>
                <div className="neo-stat-card">
                    <p className="text-sm font-semibold text-gray-500 uppercase">Pending</p>
                    <p className="text-2xl font-bold text-yellow-600 mt-1">{stats.pending}</p>
                </div>
                <div className="neo-stat-card">
                    <p className="text-sm font-semibold text-gray-500 uppercase">Revenue</p>
                    <p className="text-xl font-bold text-[#8B5CF6] mt-1">{formatCurrency(stats.revenue)}</p>
                </div>
            </div>

            {/* Filters */}
            <div className="neo-card p-4 mb-6">
                <div className="flex flex-col gap-4">
                    {/* Status Tabs */}
                    <div className="flex flex-wrap gap-2">
                        {['', 'success', 'pending', 'expired', 'failed'].map((s) => (
                            <button
                                key={s}
                                onClick={() => {
                                    setStatus(s);
                                    router.get('/transactions', {
                                        search: search || undefined,
                                        status: s || undefined,
                                        bot_id: botId || undefined,
                                    }, { preserveState: true });
                                }}
                                className={`px-4 py-2 font-bold text-sm border-2 border-gray-900 shadow-[4px_4px_0px_0px_rgba(0,0,0,1)] transition-all
                                    ${status === s
                                        ? 'bg-[#8B5CF6] text-white translate-y-[2px] shadow-[2px_2px_0px_0px_rgba(0,0,0,1)]'
                                        : 'bg-white text-gray-900 hover:-translate-y-0.5 hover:shadow-[6px_6px_0px_0px_rgba(0,0,0,1)]'
                                    }`}
                            >
                                {s === '' ? 'All Transactions' : s.charAt(0).toUpperCase() + s.slice(1)}
                            </button>
                        ))}
                    </div>

                    {/* Search & Bot Filter */}
                    <div className="flex flex-wrap items-end gap-4">
                        <div className="flex-1 min-w-[200px]">
                            <label className="block text-sm font-semibold text-gray-900 mb-2">Search</label>
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                placeholder="Order ID, product, username..."
                                className="neo-input"
                            />
                        </div>
                        <div className="w-48">
                            <label className="block text-sm font-semibold text-gray-900 mb-2">Bot</label>
                            <select value={botId} onChange={(e) => setBotId(e.target.value)} className="neo-input">
                                <option value="">All Bots</option>
                                {bots.map((bot) => (
                                    <option key={bot.id} value={bot.id}>{bot.name}</option>
                                ))}
                            </select>
                        </div>
                        <div className="flex gap-2">
                            <button onClick={applyFilters} className="neo-btn-primary text-sm h-[42px]">Filter</button>
                            <button onClick={clearFilters} className="neo-btn-secondary text-sm h-[42px]">Clear</button>
                        </div>
                    </div>
                </div>
            </div>

            {/* Table */}
            <div className="neo-table overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead className="bg-gray-100 border-b-3 border-gray-900">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-bold text-gray-900 uppercase">Order</th>
                                <th className="px-4 py-3 text-left text-xs font-bold text-gray-900 uppercase">Customer</th>
                                <th className="px-4 py-3 text-left text-xs font-bold text-gray-900 uppercase">Product</th>
                                <th className="px-4 py-3 text-left text-xs font-bold text-gray-900 uppercase">Amount</th>
                                <th className="px-4 py-3 text-left text-xs font-bold text-gray-900 uppercase">Bot</th>
                                <th className="px-4 py-3 text-left text-xs font-bold text-gray-900 uppercase">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-bold text-gray-900 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            {transactions.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-4 py-12 text-center text-gray-500">
                                        No transactions found
                                    </td>
                                </tr>
                            ) : (
                                transactions.data.map((tx) => (
                                    <tr key={tx.id} className="hover:bg-gray-50 transition-colors">
                                        <td className="px-4 py-4 font-mono text-sm font-bold text-gray-900">{tx.order_id}</td>
                                        <td className="px-4 py-4 text-sm text-gray-600">@{tx.telegram_username || '-'}</td>
                                        <td className="px-4 py-4">
                                            <div className="text-sm font-medium text-gray-900">{tx.product_name}</div>
                                            {tx.variant && <div className="text-xs text-gray-500">{tx.variant}</div>}
                                        </td>
                                        <td className="px-4 py-4 font-bold text-[#8B5CF6]">{formatCurrency(tx.total_price)}</td>
                                        <td className="px-4 py-4 text-sm text-gray-600">{tx.bot?.name || '-'}</td>
                                        <td className="px-4 py-4"><StatusBadge status={tx.status} /></td>
                                        <td className="px-4 py-4 text-sm text-gray-500">{tx.created_at}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {transactions.last_page > 1 && (
                    <div className="px-4 py-3 border-t-2 border-gray-200 flex flex-wrap justify-between items-center gap-2">
                        <p className="text-sm text-gray-600">
                            Showing {transactions.from} to {transactions.to} of {transactions.total}
                        </p>
                        <div className="flex flex-wrap gap-1">
                            {transactions.links.map((link, i) => (
                                <button
                                    key={i}
                                    onClick={() => {
                                        if (link.url) {
                                            // Extract page number from URL and navigate with current filters
                                            const url = new URL(link.url);
                                            const page = url.searchParams.get('page');
                                            router.get('/transactions', {
                                                page: page || 1,
                                                search: search || undefined,
                                                status: status || undefined,
                                                bot_id: botId || undefined,
                                            }, { preserveState: true, preserveScroll: true });
                                        }
                                    }}
                                    disabled={!link.url}
                                    className={`px-3 py-1 text-sm font-bold rounded transition-all ${link.active
                                        ? 'bg-[#8B5CF6] text-white'
                                        : link.url
                                            ? 'bg-gray-100 text-gray-600 hover:bg-gray-200 cursor-pointer'
                                            : 'bg-gray-50 text-gray-400 cursor-not-allowed'
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
