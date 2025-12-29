import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import {
    IconDashboard,
    IconBot,
    IconCreditCard,
    IconTransaction,
    IconCode,
    IconPlus,
    IconSettings,
    IconUsers,
    IconLogout,
    IconMenu,
    IconMenuClose,
    IconWithdraw,
} from '@/Components/Icons';

const IconX = ({ className = "w-5 h-5" }) => (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M18 6L6 18M6 6l12 12" />
    </svg>
);

export default function Sidebar({ user, currentPath, isOpen, onClose, collapsed, onCollapsedChange }) {
    const menuItems = [
        { name: 'Dashboard', icon: IconDashboard, href: '/dashboard', active: currentPath === '/dashboard' },
        { name: 'Payment Gateways', icon: IconCreditCard, href: '/payment-gateways', active: currentPath?.startsWith('/payment-gateways') },
        { name: 'Bot Settings', icon: IconBot, href: '/bots', active: currentPath?.startsWith('/bots') },
        { name: 'Transactions', icon: IconTransaction, href: '/transactions', active: currentPath?.startsWith('/transactions') },
        { name: 'Create Transaction', icon: IconPlus, href: '/create-transaction', active: currentPath?.startsWith('/create-transaction') },
        { name: 'Atlantic Withdraw', icon: IconWithdraw, href: '/atlantic/withdraw', active: currentPath?.startsWith('/atlantic') },
        { name: 'Order Kuota Tools', icon: IconCode, href: '/orderkuota-tools', active: currentPath?.startsWith('/orderkuota-tools') },
    ];

    const adminMenuItems = [
        { name: 'All Users', icon: IconUsers, href: '/admin/users', active: currentPath?.startsWith('/admin/users') },
        { name: 'All Bots', icon: IconBot, href: '/admin/bots', active: currentPath?.startsWith('/admin/bots') },
    ];

    return (
        <>
            {/* Mobile Overlay */}
            {isOpen && (
                <div
                    className="fixed inset-0 bg-black/60 z-40 lg:hidden"
                    onClick={onClose}
                />
            )}

            <aside
                className={cn(
                    "neo-sidebar transition-all duration-300 z-50",
                    isOpen ? 'translate-x-0' : '-translate-x-full',
                    'lg:translate-x-0',
                    collapsed ? 'w-[72px]' : 'w-[260px]'
                )}
            >
                {/* Header */}
                <div className="flex h-16 items-center border-b-2 border-gray-200 px-4">
                    {!collapsed && (
                        <Link href="/dashboard" className="flex items-center gap-3" onClick={onClose}>
                            {/* Logo Icon */}
                            <div className="relative">
                                <div className="absolute inset-0 bg-gray-900 rounded-xl translate-x-0.5 translate-y-0.5"></div>
                                <img src="/logo.png" alt="SpaceDigital" className="relative w-10 h-10 bg-white rounded-xl border-2 border-gray-900 p-1" />
                            </div>
                            <span className="text-lg font-black text-gray-900 tracking-tight">
                                SPACEDIGITAL
                            </span>
                        </Link>
                    )}

                    {/* Close button - Mobile */}
                    <button
                        onClick={onClose}
                        className="ml-auto p-2 text-gray-500 hover:text-gray-900 rounded-lg lg:hidden"
                    >
                        <IconX className="h-5 w-5" />
                    </button>

                    {/* Collapse button - Desktop */}
                    <button
                        onClick={() => onCollapsedChange?.(!collapsed)}
                        className={cn(
                            "p-2 text-gray-500 hover:text-gray-900 rounded-lg hidden lg:flex",
                            collapsed ? "mx-auto" : "ml-auto"
                        )}
                    >
                        {collapsed ? <IconMenu className="h-5 w-5" /> : <IconMenuClose className="h-5 w-5" />}
                    </button>
                </div>

                {/* Menu */}
                <div className="flex-1 overflow-y-auto py-4">
                    <nav className="px-3 space-y-1">
                        {/* Main Menu */}
                        <div className="space-y-1">
                            {!collapsed && (
                                <p className="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
                                    Menu
                                </p>
                            )}
                            {menuItems.map((item) => (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    onClick={onClose}
                                    className={cn(
                                        "neo-sidebar-item",
                                        item.active && 'active',
                                        collapsed && 'justify-center px-2'
                                    )}
                                    title={collapsed ? item.name : undefined}
                                >
                                    <item.icon className="h-5 w-5 shrink-0" />
                                    {!collapsed && <span>{item.name}</span>}
                                </Link>
                            ))}
                        </div>

                        {/* Admin Menu */}
                        {user?.role === 'super_admin' && (
                            <div className="pt-6 space-y-1">
                                {!collapsed && (
                                    <p className="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
                                        Admin
                                    </p>
                                )}
                                {adminMenuItems.map((item) => (
                                    <Link
                                        key={item.name}
                                        href={item.href}
                                        onClick={onClose}
                                        className={cn(
                                            "neo-sidebar-item",
                                            item.active && 'active',
                                            collapsed && 'justify-center px-2'
                                        )}
                                        title={collapsed ? item.name : undefined}
                                    >
                                        <item.icon className="h-5 w-5 shrink-0" />
                                        {!collapsed && <span>{item.name}</span>}
                                    </Link>
                                ))}
                            </div>
                        )}
                    </nav>
                </div>

                {/* Footer */}
                <div className="border-t-2 border-gray-200 p-3">
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className={cn(
                            "flex w-full items-center gap-3 rounded-lg px-3 py-2.5 font-medium text-gray-500 hover:bg-red-50 hover:text-red-500 transition-all duration-200",
                            collapsed && 'justify-center px-2'
                        )}
                        title={collapsed ? 'Logout' : undefined}
                    >
                        <IconLogout className="h-5 w-5 shrink-0" />
                        {!collapsed && <span>Logout</span>}
                    </Link>
                </div>
            </aside>
        </>
    );
}
