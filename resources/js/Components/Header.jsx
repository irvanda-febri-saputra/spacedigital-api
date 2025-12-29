import { useState, useEffect, useCallback } from 'react';
import { Link, router } from '@inertiajs/react';
import {
    IconBell,
    IconUser,
    IconSettings,
    IconLogout,
    IconMenu,
} from '@/Components/Icons';

export default function Header({ user, onMenuClick }) {
    const [dropdownOpen, setDropdownOpen] = useState(false);
    const [notificationOpen, setNotificationOpen] = useState(false);
    const [notifications, setNotifications] = useState([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [loading, setLoading] = useState(true);

    // Fetch notifications from API
    const fetchNotifications = useCallback(async () => {
        try {
            const response = await fetch('/notifications/api', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (response.ok) {
                const data = await response.json();
                setNotifications(data.notifications || []);
                setUnreadCount(data.unread_count || 0);
            }
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
        } finally {
            setLoading(false);
        }
    }, []);

    // Initial fetch and WebSocket setup
    useEffect(() => {
        fetchNotifications();

        // Polling fallback every 30 seconds (in case WebSocket disconnects)
        const pollInterval = setInterval(fetchNotifications, 30000);

        // Listen for real-time notifications via Laravel Echo
        if (window.Echo && user?.id) {
            const channel = window.Echo.private(`user.${user.id}`);

            channel.listen('.notification.created', (data) => {
                console.log('New notification received:', data);
                // Add new notification to top of list
                setNotifications(prev => [data.notification, ...prev]);
                setUnreadCount(prev => prev + 1);

                // Show browser notification if permitted
                if (Notification.permission === 'granted') {
                    new Notification(data.notification.title, {
                        body: data.notification.message,
                        icon: '/logo.png'
                    });
                }
            });

            return () => {
                clearInterval(pollInterval);
                channel.stopListening('.notification.created');
            };
        }

        return () => clearInterval(pollInterval);
    }, [user?.id, fetchNotifications]);

    // Request browser notification permission
    useEffect(() => {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }, []);

    // Mark notification as read
    const markAsRead = async (id) => {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            await fetch(`/notifications/${id}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            setNotifications(prev => prev.map(n =>
                n.id === id ? { ...n, read: true } : n
            ));
            setUnreadCount(prev => Math.max(0, prev - 1));
        } catch (error) {
            console.error('Failed to mark as read:', error);
        }
    };

    // Mark all as read
    const markAllAsRead = async () => {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const response = await fetch('/notifications/read-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (response.ok) {
                // Update local state immediately for UI feedback
                setNotifications(prev => prev.map(n => ({ ...n, read: true })));
                setUnreadCount(0);
                // Re-fetch to ensure sync with server
                await fetchNotifications();
            } else {
                console.error('Failed to mark all as read:', response.status);
            }
        } catch (error) {
            console.error('Failed to mark all as read:', error);
        }
    };

    const getNotificationIcon = (type) => {
        switch (type) {
            case 'success':
                return <span className="text-green-500">💰</span>;
            case 'warning':
                return <span className="text-yellow-500">⚠️</span>;
            case 'info':
                return <span className="text-blue-500">🤖</span>;
            case 'system':
                return <span className="text-purple-500">🔔</span>;
            case 'error':
                return <span className="text-red-500">❌</span>;
            default:
                return <span>📌</span>;
        }
    };

    return (
        <header className="neo-header flex h-16 items-center">
            {/* Mobile Menu Button */}
            <button
                onClick={onMenuClick}
                className="lg:hidden mr-4 p-2 text-gray-600 hover:text-gray-900 rounded-lg transition-colors"
            >
                <IconMenu className="h-5 w-5" />
            </button>

            {/* Spacer */}
            <div className="flex-1" />

            {/* Right side */}
            <div className="flex items-center gap-3">
                {/* Notifications */}
                <div className="relative">
                    <button
                        onClick={() => {
                            setNotificationOpen(!notificationOpen);
                            setDropdownOpen(false);
                        }}
                        className="p-2 text-gray-600 hover:text-gray-900 rounded-lg transition-colors relative"
                    >
                        <IconBell className="h-5 w-5" />
                        {unreadCount > 0 && (
                            <span className="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-[#8B5CF6] text-[10px] text-white font-bold animate-pulse">
                                {unreadCount > 9 ? '9+' : unreadCount}
                            </span>
                        )}
                    </button>

                    {/* Notification Dropdown */}
                    {notificationOpen && (
                        <>
                            <div
                                className="fixed inset-0 z-40"
                                onClick={() => setNotificationOpen(false)}
                            />
                            <div className="absolute right-0 mt-2 w-80 sm:w-96 neo-card z-50 max-h-[70vh] overflow-hidden flex flex-col">
                                <div className="px-4 py-3 border-b-2 border-gray-100 flex items-center justify-between bg-gradient-to-r from-purple-50 to-white">
                                    <div>
                                        <h3 className="text-sm font-bold text-gray-900">Notifications</h3>
                                        <p className="text-xs text-gray-500">{unreadCount} unread</p>
                                    </div>
                                    {unreadCount > 0 && (
                                        <button
                                            onClick={markAllAsRead}
                                            className="text-xs text-[#8B5CF6] font-medium hover:underline"
                                        >
                                            Mark all read
                                        </button>
                                    )}
                                </div>

                                <div className="overflow-y-auto flex-1 max-h-80">
                                    {loading ? (
                                        <div className="px-4 py-8 text-center">
                                            <div className="animate-spin w-6 h-6 border-2 border-purple-500 border-t-transparent rounded-full mx-auto"></div>
                                            <p className="text-sm text-gray-500 mt-2">Loading...</p>
                                        </div>
                                    ) : notifications.length > 0 ? (
                                        notifications.map((notification) => (
                                            <div
                                                key={notification.id}
                                                onClick={() => !notification.read && markAsRead(notification.id)}
                                                className={`px-4 py-3 border-b border-gray-100 hover:bg-gray-50 transition-colors cursor-pointer ${!notification.read ? 'bg-purple-50/50' : ''}`}
                                            >
                                                <div className="flex gap-3">
                                                    <div className="flex-shrink-0 text-lg">
                                                        {getNotificationIcon(notification.type)}
                                                    </div>
                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-start justify-between gap-2">
                                                            <p className={`text-sm font-semibold ${!notification.read ? 'text-gray-900' : 'text-gray-700'}`}>
                                                                {notification.title}
                                                            </p>
                                                            {!notification.read && (
                                                                <span className="flex-shrink-0 w-2 h-2 rounded-full bg-[#8B5CF6] mt-1.5"></span>
                                                            )}
                                                        </div>
                                                        <p className="text-xs text-gray-500 mt-0.5 line-clamp-2">
                                                            {notification.message}
                                                        </p>
                                                        <p className="text-xs text-gray-400 mt-1">
                                                            {notification.time}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        ))
                                    ) : (
                                        <div className="px-4 py-8 text-center">
                                            <span className="text-3xl">🔔</span>
                                            <p className="text-sm text-gray-500 mt-2">No notifications yet</p>
                                            <p className="text-xs text-gray-400 mt-1">We'll notify you when something happens</p>
                                        </div>
                                    )}
                                </div>

                                {notifications.length > 0 && (
                                    <div className="px-4 py-3 border-t-2 border-gray-100 bg-gray-50">
                                        <button
                                            onClick={() => {
                                                setNotificationOpen(false);
                                                // Refresh to show all notifications
                                                router.visit('/notifications');
                                            }}
                                            className="block w-full text-center text-sm text-[#8B5CF6] font-medium hover:underline"
                                        >
                                            View all notifications →
                                        </button>
                                    </div>
                                )}
                            </div>
                        </>
                    )}
                </div>

                {/* User Dropdown */}
                <div className="relative">
                    <button
                        onClick={() => {
                            setDropdownOpen(!dropdownOpen);
                            setNotificationOpen(false);
                        }}
                        className="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-100 transition-colors"
                    >
                        <span className="hidden text-right md:block">
                            <span className="block text-sm font-semibold text-gray-900">
                                {user?.name || 'User'}
                            </span>
                            <span className="block text-xs text-gray-500">
                                {user?.role === 'super_admin' ? 'Super Admin' : 'User'}
                            </span>
                        </span>
                        <span className="flex h-9 w-9 items-center justify-center rounded-lg overflow-hidden border-2 border-gray-900 shadow-[2px_2px_0_#1A1A1A] bg-[#8B5CF6]/10">
                            <img
                                src={`https://api.dicebear.com/7.x/${user?.avatar_style || 'bottts'}/svg?seed=${user?.avatar_seed || user?.email || 'default'}&backgroundColor=8B5CF6`}
                                alt="Avatar"
                                className="w-full h-full object-cover"
                            />
                        </span>
                    </button>

                    {/* Dropdown */}
                    {dropdownOpen && (
                        <>
                            <div
                                className="fixed inset-0 z-40"
                                onClick={() => setDropdownOpen(false)}
                            />
                            <div className="absolute right-0 mt-2 w-72 neo-card z-50">
                                <div className="px-4 py-3 border-b-2 border-gray-100">
                                    <p className="text-xs text-gray-500 font-medium">Signed in as</p>
                                    <p className="text-sm font-semibold text-gray-900 truncate">
                                        {user?.email}
                                    </p>
                                </div>

                                {/* API Key Section */}
                                {user?.api_key && (
                                    <div className="px-4 py-3 border-b-2 border-gray-100 bg-gray-50">
                                        <p className="text-xs text-gray-500 font-medium mb-1">🔑 API Key</p>
                                        <div className="flex items-center gap-2">
                                            <code className="flex-1 text-xs bg-white px-2 py-1 rounded border border-gray-200 truncate font-mono">
                                                {user.api_key}
                                            </code>
                                            <button
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    navigator.clipboard.writeText(user.api_key);
                                                    alert('API Key copied!');
                                                }}
                                                className="px-2 py-1 text-xs bg-[#8B5CF6] text-white rounded font-medium hover:bg-[#7C3AED] transition-colors"
                                            >
                                                Copy
                                            </button>
                                        </div>
                                    </div>
                                )}

                                <div className="py-2">
                                    <Link
                                        href="/profile"
                                        className="flex items-center gap-3 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                                        onClick={() => setDropdownOpen(false)}
                                    >
                                        <IconUser className="h-4 w-4" />
                                        My Profile
                                    </Link>
                                    <Link
                                        href="/settings"
                                        className="flex items-center gap-3 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                                        onClick={() => setDropdownOpen(false)}
                                    >
                                        <IconSettings className="h-4 w-4" />
                                        Settings
                                    </Link>
                                </div>
                                <div className="border-t-2 border-gray-100 py-2">
                                    <Link
                                        href="/logout"
                                        method="post"
                                        as="button"
                                        className="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 transition-colors"
                                    >
                                        <IconLogout className="h-4 w-4" />
                                        Logout
                                    </Link>
                                </div>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </header>
    );
}
