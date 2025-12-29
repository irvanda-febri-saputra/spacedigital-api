import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DefaultLayout from '@/Layouts/DefaultLayout';
import { IconUsers, IconCheckCircle, IconClock, IconBan } from '@/Components/Icons';
import { Skeleton, StatCardSkeleton, TableRowSkeleton } from '@/Components/Skeleton';

const StatusBadge = ({ status }) => {
    const colors = {
        active: 'bg-green-100 text-green-700',
        pending: 'bg-yellow-100 text-yellow-700',
        suspended: 'bg-red-100 text-red-700',
    };
    
    return (
        <span className={`px-2.5 py-1 text-xs font-medium rounded-full ${colors[status]}`}>
            {status.charAt(0).toUpperCase() + status.slice(1)}
        </span>
    );
};

const RoleBadge = ({ role }) => {
    const colors = {
        super_admin: 'bg-purple-100 text-purple-700',
        user: 'bg-gray-100 text-gray-700',
    };
    
    const labels = {
        super_admin: 'Super Admin',
        user: 'User',
    };
    
    return (
        <span className={`px-2.5 py-1 text-xs font-medium rounded-full ${colors[role]}`}>
            {labels[role]}
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

export default function UsersIndex({ auth, users, stats, filters }) {
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState(filters?.search || '');
    const [status, setStatus] = useState(filters?.status || '');
    const [role, setRole] = useState(filters?.role || '');

    useEffect(() => {
        const timer = setTimeout(() => setLoading(false), 600);
        return () => clearTimeout(timer);
    }, []);

    const applyFilters = () => {
        router.get('/admin/users', {
            search: search || undefined,
            status: status || undefined,
            role: role || undefined,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('');
        setRole('');
        router.get('/admin/users');
    };

    const handleApprove = (userId) => {
        router.post(`/admin/users/${userId}/approve`);
    };

    const handleSuspend = (userId) => {
        router.post(`/admin/users/${userId}/suspend`);
    };

    const handleRoleChange = (userId, newRole) => {
        router.put(`/admin/users/${userId}/role`, { role: newRole });
    };

    const handleDelete = (userId, userName) => {
        if (confirm(`Delete user "${userName}"? This action cannot be undone!`)) {
            router.delete(`/admin/users/${userId}`);
        }
    };

    // Loading skeleton
    if (loading) {
        return (
            <DefaultLayout user={auth?.user}>
                <Head title="All Users" />
                <div className="space-y-6">
                    <div className="mb-6">
                        <Skeleton className="h-8 w-44 mb-2" />
                        <Skeleton className="h-5 w-72" />
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
                            <Skeleton className="h-10 w-32 rounded-lg" />
                            <Skeleton className="h-10 w-20 rounded-lg" />
                        </div>
                    </div>
                    
                    <div className="neo-card overflow-hidden">
                        <table className="w-full">
                            <thead className="bg-gray-100 border-b-3 border-gray-900">
                                <tr>
                                    {[...Array(6)].map((_, i) => (
                                        <th key={i} className="px-4 py-3">
                                            <Skeleton className="h-4 w-16" />
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {[...Array(8)].map((_, i) => (
                                    <TableRowSkeleton key={i} columns={6} />
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
            <Head title="User Management" />

            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    <IconUsers className="w-6 h-6 text-[#8B5CF6]" /> User Management
                </h1>
                <p className="text-gray-500 mt-1">
                    Manage users, approve registrations, and assign roles
                </p>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <StatCard title="Total Users" value={stats.total} icon={IconUsers} color="text-gray-900" />
                <StatCard title="Active" value={stats.active} icon={IconCheckCircle} color="text-green-600" />
                <StatCard title="Pending" value={stats.pending} icon={IconClock} color="text-yellow-600" />
                <StatCard title="Suspended" value={stats.suspended} icon={IconBan} color="text-red-600" />
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
                            placeholder="Name or email..."
                            className="neo-input"
                        />
                    </div>
                    <div className="w-36">
                        <label className="block text-sm font-semibold text-gray-900 mb-2">Status</label>
                        <select value={status} onChange={(e) => setStatus(e.target.value)} className="neo-input">
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <div className="w-36">
                        <label className="block text-sm font-semibold text-gray-900 mb-2">Role</label>
                        <select value={role} onChange={(e) => setRole(e.target.value)} className="neo-input">
                            <option value="">All</option>
                            <option value="super_admin">Super Admin</option>
                            <option value="user">User</option>
                        </select>
                    </div>
                    <div className="flex gap-2">
                        <button onClick={applyFilters} className="neo-btn-primary text-sm">Filter</button>
                        <button onClick={clearFilters} className="neo-btn-secondary text-sm">Clear</button>
                    </div>
                </div>
            </div>

            {/* Table */}
            <div className="neo-table overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead className="bg-gray-100 border-b-3 border-gray-900">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-bold text-gray-900 uppercase">User</th>
                                <th className="px-4 py-3 text-left text-xs font-bold text-gray-900 uppercase">Role</th>
                                <th className="px-4 py-3 text-left text-xs font-bold text-gray-900 uppercase">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-bold text-gray-900 uppercase">Bots</th>
                                <th className="px-4 py-3 text-left text-xs font-bold text-gray-900 uppercase">Joined</th>
                                <th className="px-4 py-3 text-right text-xs font-bold text-gray-900 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {users.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-12 text-center text-gray-500">
                                        <div className="w-12 h-12 mx-auto mb-2 bg-gray-100 rounded-xl flex items-center justify-center">
                                            <IconUsers className="w-6 h-6 text-gray-400" />
                                        </div>
                                        No users found
                                    </td>
                                </tr>
                            ) : (
                                users.data.map((user) => (
                                    <tr key={user.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <img 
                                                    src={`https://api.dicebear.com/7.x/${user.avatar_style || 'bottts'}/svg?seed=${user.avatar_seed || user.email}&backgroundColor=8B5CF6`}
                                                    alt={user.name}
                                                    className="w-10 h-10 rounded-full border-2 border-gray-200 bg-purple-50"
                                                />
                                                <div>
                                                    <div className="font-medium text-gray-900">{user.name}</div>
                                                    <div className="text-sm text-gray-500">{user.email}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3"><RoleBadge role={user.role} /></td>
                                        <td className="px-4 py-3"><StatusBadge status={user.status} /></td>
                                        <td className="px-4 py-3 text-gray-600">{user.bots_count}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">{user.created_at}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-2">
                                                {user.status === 'pending' && (
                                                    <button onClick={() => handleApprove(user.id)} className="px-2 py-1 text-xs font-medium text-green-600 hover:bg-green-50 rounded">
                                                        Approve
                                                    </button>
                                                )}
                                                {user.status === 'active' && user.id !== auth.user.id && (
                                                    <button onClick={() => handleSuspend(user.id)} className="px-2 py-1 text-xs font-medium text-yellow-600 hover:bg-yellow-50 rounded">
                                                        Suspend
                                                    </button>
                                                )}
                                                {user.status === 'suspended' && (
                                                    <button onClick={() => handleApprove(user.id)} className="px-2 py-1 text-xs font-medium text-green-600 hover:bg-green-50 rounded">
                                                        Reactivate
                                                    </button>
                                                )}
                                                {user.id !== auth.user.id && (
                                                    <>
                                                        <select
                                                            value={user.role}
                                                            onChange={(e) => handleRoleChange(user.id, e.target.value)}
                                                            className="text-xs border border-gray-300 rounded px-2 py-1"
                                                        >
                                                            <option value="user">User</option>
                                                            <option value="super_admin">Admin</option>
                                                        </select>
                                                        <button onClick={() => handleDelete(user.id, user.name)} className="px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 rounded">
                                                            Delete
                                                        </button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {users.last_page > 1 && (
                    <div className="px-4 py-3 border-t-2 border-gray-200 flex items-center justify-between">
                        <p className="text-sm text-gray-500">
                            Showing {users.from} to {users.to} of {users.total}
                        </p>
                        <div className="flex gap-2">
                            {users.links.map((link, i) => (
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
