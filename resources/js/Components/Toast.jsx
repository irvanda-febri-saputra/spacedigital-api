import { useState, createContext, useContext } from 'react';

const ToastContext = createContext(null);

export function useToast() {
    const context = useContext(ToastContext);
    if (!context) {
        // Return safe fallback if used outside provider
        return { showToast: () => console.warn('Toast: useToast called outside ToastProvider') };
    }
    return context;
}

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);

    const showToast = (message, type = 'success') => {
        const id = Date.now();
        setToasts(prev => [...prev, { id, message, type }]);
        
        // Auto remove after 3 seconds
        setTimeout(() => {
            setToasts(prev => prev.filter(t => t.id !== id));
        }, 3000);
    };

    const removeToast = (id) => {
        setToasts(prev => prev.filter(t => t.id !== id));
    };

    return (
        <ToastContext.Provider value={{ showToast }}>
            {children}
            
            {/* Toast Container */}
            <div className="fixed bottom-6 right-6 z-50 flex flex-col gap-3">
                {toasts.map(toast => (
                    <div
                        key={toast.id}
                        className={`
                            flex items-center gap-3 px-5 py-4 rounded-xl shadow-lg
                            border-2 border-gray-900 shadow-[4px_4px_0_#1A1A1A]
                            animate-slide-up min-w-[300px] max-w-md
                            ${toast.type === 'success' ? 'bg-green-50' : ''}
                            ${toast.type === 'error' ? 'bg-red-50' : ''}
                            ${toast.type === 'info' ? 'bg-blue-50' : ''}
                        `}
                    >
                        {/* Icon */}
                        {toast.type === 'success' && (
                            <div className="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center flex-shrink-0">
                                <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        )}
                        {toast.type === 'error' && (
                            <div className="w-8 h-8 rounded-full bg-red-500 flex items-center justify-center flex-shrink-0">
                                <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>
                        )}
                        {toast.type === 'info' && (
                            <div className="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center flex-shrink-0">
                                <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        )}

                        {/* Message */}
                        <span className="font-semibold text-gray-900">{toast.message}</span>

                        {/* Close Button */}
                        <button
                            onClick={() => removeToast(toast.id)}
                            className="ml-auto text-gray-400 hover:text-gray-600"
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                ))}
            </div>

            <style>{`
                @keyframes slide-up {
                    from {
                        opacity: 0;
                        transform: translateY(20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                .animate-slide-up {
                    animation: slide-up 0.3s ease-out;
                }
            `}</style>
        </ToastContext.Provider>
    );
}

// Professional success messages
export const TOAST_MESSAGES = {
    SAVED: 'Changes saved successfully',
    UPDATED: 'Updated successfully',
    DELETED: 'Deleted successfully',
    CREATED: 'Created successfully',
    COPIED: 'Copied to clipboard',
    SENT: 'Sent successfully',
    AVATAR_UPDATED: 'Profile picture updated',
    PROFILE_UPDATED: 'Profile updated successfully',
    PASSWORD_CHANGED: 'Password changed successfully',
    API_KEY_REGENERATED: 'API key regenerated successfully',
    ERROR_GENERIC: 'Something went wrong. Please try again.',
};
