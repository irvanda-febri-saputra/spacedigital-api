import { Head, router } from '@inertiajs/react';
import DefaultLayout from '@/Layouts/DefaultLayout';
import { useState, useEffect, useCallback } from 'react';

export default function NotificationsIndex({ auth }) {
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

    useEffect(() => {
        fetchNotifications();

        // Auto-refresh every 30 seconds
        const interval = setInterval(fetchNotifications, 30000);

        // Listen for real-time notifications
        if (window.Echo && auth?.user?.id) {
            const channel = window.Echo.private(`user.${auth.user.id}`);
            channel.listen('.notification.created', (data) => {
                setNotifications(prev => [data.notification, ...prev]);
                setUnreadCount(prev => prev + 1);
            });

            return () => {
                clearInterval(interval);
                channel.stopListening('.notification.created');
            };
        }

        return () => clearInterval(interval);
    }, [auth?.user?.id, fetchNotifications]);

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

    const markAllAsRead = async () => {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            await fetch('/notifications/read-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            setNotifications(prev => prev.map(n => ({ ...n, read: true })));
            setUnreadCount(0);
        } catch (error) {
            console.error('Failed to mark all as read:', error);
        }
    };

    const deleteNotification = async (id) => {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            await fetch(`/notifications/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            setNotifications(prev => prev.filter(n => n.id !== id));
        } catch (error) {
            console.error('Failed to delete notification:', error);
        }
    };

    const getNotificationIcon = (type) => {
        switch (type) {
            case 'success': return '💰';
            case 'warning': return '⚠️';
            case 'info': return '🤖';
            case 'system': return '🔔';
            case 'error': return '❌';
            default: return '📌';
        }
    };

    const getNotificationBg = (type) => {
        switch (type) {
            case 'success': return 'bg-green-50 border-green-200';
            case 'warning': return 'bg-yellow-50 border-yellow-200';
            case 'info': return 'bg-blue-50 border-blue-200';
            case 'system': return 'bg-purple-50 border-purple-200';
            case 'error': return 'bg-red-50 border-red-200';
            default: return 'bg-gray-50 border-gray-200';
        }
    };

    return (
        <DefaultLayout user={auth?.user}>
            <Head title="Notifications" />

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Notifications</h1>
                    <p className="text-gray-500 mt-1">
                        {unreadCount > 0 ? `${unreadCount} unread notifications` : 'All caught up!'}
                    </p>
                </div>
                {unreadCount > 0 && (
                    <button
                        onClick={markAllAsRead}
                        className="neo-btn-secondary text-sm"
                    >
                        Mark all as read
                    </button>
                )}
            </div>

            <div className="max-w-3xl">
                {loading ? (
                    <div className="neo-card p-8 text-center">
                        <div className="animate-spin w-8 h-8 border-3 border-purple-500 border-t-transparent rounded-full mx-auto"></div>
                        <p className="text-gray-500 mt-4">Loading notifications...</p>
                    </div>
                ) : notifications.length > 0 ? (
                    <div className="space-y-3">
                        {notifications.map((notification) => (
                            <div
                                key={notification.id}
                                className={`neo-card p-4 transition-all ${!notification.read ? 'ring-2 ring-purple-300 bg-purple-50/30' : ''}`}
                            >
                                <div className="flex gap-4">
                                    <div className={`flex-shrink-0 w-12 h-12 rounded-lg flex items-center justify-center text-2xl ${getNotificationBg(notification.type)}`}>
                                        {getNotificationIcon(notification.type)}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <p className={`font-semibold ${!notification.read ? 'text-gray-900' : 'text-gray-700'}`}>
                                                    {notification.title}
                                                </p>
                                                <p className="text-sm text-gray-500 mt-1">
                                                    {notification.message}
                                                </p>
                                            </div>
                                            {!notification.read && (
                                                <span className="flex-shrink-0 w-3 h-3 rounded-full bg-purple-500 animate-pulse"></span>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-4 mt-3">
                                            <span className="text-xs text-gray-400">
                                                {notification.time}
                                            </span>
                                            {!notification.read && (
                                                <button
                                                    onClick={() => markAsRead(notification.id)}
                                                    className="text-xs text-purple-600 hover:underline"
                                                >
                                                    Mark as read
                                                </button>
                                            )}
                                            <button
                                                onClick={() => deleteNotification(notification.id)}
                                                className="text-xs text-red-500 hover:underline"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="neo-card p-12 text-center">
                        <span className="text-5xl">🔔</span>
                        <h3 className="text-lg font-semibold text-gray-900 mt-4">No notifications yet</h3>
                        <p className="text-gray-500 mt-2">
                            We'll notify you when something important happens
                        </p>
                    </div>
                )}
            </div>
        </DefaultLayout>
    );
}
