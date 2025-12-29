import { useState } from 'react';
import Sidebar from '@/Components/Sidebar';
import Header from '@/Components/Header';
import { cn } from '@/lib/utils';

export default function DefaultLayout({ children, user }) {
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);

    return (
        <div className="min-h-screen bg-[#F5F5F5]">
            {/* Sidebar */}
            <Sidebar 
                user={user} 
                currentPath={window.location.pathname}
                isOpen={sidebarOpen}
                onClose={() => setSidebarOpen(false)}
                collapsed={sidebarCollapsed}
                onCollapsedChange={setSidebarCollapsed}
            />

            {/* Content Area */}
            <div className={cn(
                "flex flex-col min-h-screen transition-all duration-300",
                sidebarCollapsed ? "lg:ml-[72px]" : "lg:ml-[260px]"
            )}>
                {/* Header */}
                <Header 
                    user={user} 
                    onMenuClick={() => setSidebarOpen(true)}
                />

                {/* Main Content */}
                <main className="flex-1 p-4 md:p-6">
                    <div className="mx-auto max-w-7xl">
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}
