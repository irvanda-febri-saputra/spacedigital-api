/**
 * Skeleton Loading Components
 * Displays shimmer animation placeholders while content is loading
 */

// Base skeleton with shimmer animation
export function Skeleton({ className = '', ...props }) {
    return (
        <div 
            className={`animate-pulse bg-gray-200 rounded ${className}`}
            {...props}
        />
    );
}

// Skeleton for stat cards (Dashboard)
export function StatCardSkeleton() {
    return (
        <div className="neo-card p-6">
            <div className="flex items-center justify-between">
                <div className="flex-1">
                    <Skeleton className="h-4 w-24 mb-2" />
                    <Skeleton className="h-8 w-16" />
                </div>
                <Skeleton className="w-12 h-12 rounded-xl" />
            </div>
        </div>
    );
}

// Skeleton for bot cards
export function BotCardSkeleton() {
    return (
        <div className="neo-card p-6">
            <div className="flex items-center gap-4 mb-4">
                <Skeleton className="w-12 h-12 rounded-lg" />
                <div className="flex-1">
                    <Skeleton className="h-5 w-32 mb-2" />
                    <Skeleton className="h-4 w-24" />
                </div>
                <Skeleton className="w-16 h-6 rounded-full" />
            </div>
            <div className="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <Skeleton className="h-4 w-20 mb-1" />
                    <Skeleton className="h-6 w-12" />
                </div>
                <div>
                    <Skeleton className="h-4 w-20 mb-1" />
                    <Skeleton className="h-6 w-24" />
                </div>
            </div>
            <Skeleton className="h-10 w-full rounded-lg" />
        </div>
    );
}

// Skeleton for table rows
export function TableRowSkeleton({ columns = 5 }) {
    return (
        <tr className="border-b border-gray-100">
            {Array.from({ length: columns }).map((_, i) => (
                <td key={i} className="px-4 py-4">
                    <Skeleton className="h-4 w-full" />
                </td>
            ))}
        </tr>
    );
}

// Skeleton for transaction table
export function TransactionTableSkeleton({ rows = 5 }) {
    return (
        <div className="neo-card overflow-hidden">
            <div className="overflow-x-auto">
                <table className="w-full">
                    <thead className="bg-gray-50 border-b-2 border-gray-900">
                        <tr>
                            <th className="px-4 py-3 text-left"><Skeleton className="h-4 w-16" /></th>
                            <th className="px-4 py-3 text-left"><Skeleton className="h-4 w-20" /></th>
                            <th className="px-4 py-3 text-left"><Skeleton className="h-4 w-16" /></th>
                            <th className="px-4 py-3 text-left"><Skeleton className="h-4 w-16" /></th>
                            <th className="px-4 py-3 text-left"><Skeleton className="h-4 w-20" /></th>
                        </tr>
                    </thead>
                    <tbody>
                        {Array.from({ length: rows }).map((_, i) => (
                            <TableRowSkeleton key={i} columns={5} />
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

// Full Dashboard skeleton
export function DashboardSkeleton() {
    return (
        <div className="space-y-6">
            {/* Header */}
            <div>
                <Skeleton className="h-8 w-64 mb-2" />
                <Skeleton className="h-5 w-48" />
            </div>
            
            {/* Stats Grid */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCardSkeleton />
                <StatCardSkeleton />
                <StatCardSkeleton />
                <StatCardSkeleton />
            </div>
            
            {/* Bots Section */}
            <div>
                <div className="flex justify-between items-center mb-4">
                    <Skeleton className="h-6 w-24" />
                    <Skeleton className="h-10 w-28 rounded-lg" />
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <BotCardSkeleton />
                    <BotCardSkeleton />
                </div>
            </div>
        </div>
    );
}

// Profile page skeleton
export function ProfileSkeleton() {
    return (
        <div className="max-w-2xl space-y-6">
            {/* Avatar Section */}
            <div className="neo-card">
                <div className="p-6 border-b-2 border-gray-100">
                    <Skeleton className="h-6 w-32 mb-2" />
                    <Skeleton className="h-4 w-48" />
                </div>
                <div className="p-6">
                    <div className="flex items-center gap-6">
                        <Skeleton className="w-24 h-24 rounded-xl" />
                        <div className="flex-1 space-y-3">
                            <Skeleton className="h-10 w-full rounded-lg" />
                            <div className="flex gap-2">
                                <Skeleton className="h-10 w-32 rounded-lg" />
                                <Skeleton className="h-10 w-28 rounded-lg" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            {/* Profile Info */}
            <div className="neo-card">
                <div className="p-6 border-b-2 border-gray-100">
                    <Skeleton className="h-6 w-40" />
                </div>
                <div className="p-6 space-y-4">
                    <div>
                        <Skeleton className="h-4 w-16 mb-2" />
                        <Skeleton className="h-12 w-full rounded-lg" />
                    </div>
                    <div>
                        <Skeleton className="h-4 w-16 mb-2" />
                        <Skeleton className="h-12 w-full rounded-lg" />
                    </div>
                    <Skeleton className="h-10 w-32 rounded-lg" />
                </div>
            </div>
        </div>
    );
}
