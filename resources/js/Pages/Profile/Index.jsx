import { Head, useForm, router } from '@inertiajs/react';
import DefaultLayout from '@/Layouts/DefaultLayout';
import { useState, useEffect } from 'react';
import { useToast, TOAST_MESSAGES } from '@/Components/Toast';
import { Skeleton, ProfileSkeleton } from '@/Components/Skeleton';

// Random avatar styles from DiceBear
const AVATAR_STYLES = ['adventurer', 'avataaars', 'bottts', 'identicon', 'initials', 'pixel-art', 'shapes'];

export default function ProfileIndex({ auth, user }) {
    const { showToast } = useToast();
    const [loading, setLoading] = useState(true);
    const [avatarSeed, setAvatarSeed] = useState(user?.avatar_seed || user?.email || 'default');
    const [avatarStyle, setAvatarStyle] = useState(user?.avatar_style || 'bottts');

    // Initial loading delay
    useEffect(() => {
        const timer = setTimeout(() => setLoading(false), 600);
        return () => clearTimeout(timer);
    }, []);
    // Separate form for profile info (name only)
    const { data, setData, put, processing, errors } = useForm({
        name: user?.name || '',
    });

    const handleProfileUpdate = (e) => {
        e.preventDefault();
        router.put('/profile', {
            name: data.name,
            email: user?.email,
            avatar_seed: user?.avatar_seed,
            avatar_style: user?.avatar_style,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                showToast(TOAST_MESSAGES.PROFILE_UPDATED, 'success');
            }
        });
    };

    const handleAvatarSave = () => {
        router.put('/profile', {
            name: user?.name,
            email: user?.email,
            avatar_seed: avatarSeed,
            avatar_style: avatarStyle,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                showToast(TOAST_MESSAGES.AVATAR_UPDATED, 'success');
            }
        });
    };

    const generateRandomAvatar = () => {
        const randomSeed = Math.random().toString(36).substring(2, 12);
        const randomStyle = AVATAR_STYLES[Math.floor(Math.random() * AVATAR_STYLES.length)];
        setAvatarSeed(randomSeed);
        setAvatarStyle(randomStyle);
    };

    const getAvatarUrl = (seed, style) => {
        return `https://api.dicebear.com/7.x/${style}/svg?seed=${seed}&backgroundColor=8B5CF6`;
    };

    // Loading skeleton
    if (loading) {
        return (
            <DefaultLayout user={auth?.user}>
                <Head title="My Profile" />
                <div className="max-w-2xl space-y-6">
                    {/* Header Skeleton */}
                    <div className="mb-6">
                        <Skeleton className="h-8 w-32 mb-2" />
                        <Skeleton className="h-5 w-64" />
                    </div>
                    
                    {/* Avatar Card Skeleton */}
                    <div className="neo-card">
                        <div className="p-6 border-b-2 border-gray-100">
                            <Skeleton className="h-6 w-32 mb-1" />
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
                    
                    {/* Profile Info Skeleton */}
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
            </DefaultLayout>
        );
    }

    return (
        <DefaultLayout user={auth?.user}>
            <Head title="My Profile" />

            {/* Header */}
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">My Profile</h1>
                <p className="text-gray-500 mt-1">Update your profile picture and information</p>
            </div>

            <div className="max-w-2xl space-y-6">
                {/* Avatar Section */}
                <div className="neo-card">
                    <div className="p-6 border-b-2 border-gray-100">
                        <h2 className="text-lg font-bold text-gray-900">Profile Picture</h2>
                        <p className="text-sm text-gray-500 mt-1">Your avatar across the platform</p>
                    </div>
                    
                    <div className="p-6">
                        <div className="flex items-center gap-6">
                            {/* Avatar Preview */}
                            <div className="relative">
                                <div className="w-24 h-24 rounded-xl border-3 border-gray-900 shadow-[4px_4px_0_#1A1A1A] overflow-hidden bg-[#8B5CF6]/10">
                                    <img 
                                        src={getAvatarUrl(avatarSeed, avatarStyle)} 
                                        alt="Avatar"
                                        className="w-full h-full object-cover"
                                    />
                                </div>
                            </div>
                            
                            {/* Avatar Controls */}
                            <div className="flex-1 space-y-3">
                                <div>
                                    <label className="block text-sm font-semibold text-gray-900 mb-2">Avatar Style</label>
                                    <select
                                        value={avatarStyle}
                                        onChange={(e) => {
                                            setAvatarStyle(e.target.value);
                                        }}
                                        className="neo-input"
                                    >
                                        {AVATAR_STYLES.map(style => (
                                            <option key={style} value={style}>
                                                {style.charAt(0).toUpperCase() + style.slice(1).replace('-', ' ')}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                
                                <div className="flex gap-2">
                                    <button 
                                        type="button"
                                        onClick={generateRandomAvatar}
                                        className="neo-btn-secondary text-sm"
                                    >
                                        Generate Random
                                    </button>
                                    <button 
                                        type="button"
                                        onClick={handleAvatarSave}
                                        className="neo-btn-primary text-sm"
                                    >
                                        Save Avatar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Profile Information */}
                <div className="neo-card">
                    <div className="p-6 border-b-2 border-gray-100">
                        <h2 className="text-lg font-bold text-gray-900">Profile Information</h2>
                        <p className="text-sm text-gray-500 mt-1">Update your account's profile information</p>
                    </div>
                    
                    <form onSubmit={handleProfileUpdate} className="p-6 space-y-4">
                        <div>
                            <label className="block text-sm font-semibold text-gray-900 mb-2">Name</label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="neo-input"
                            />
                            {errors.name && <p className="mt-2 text-sm text-red-500">{errors.name}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-semibold text-gray-900 mb-2">Email</label>
                            <input
                                type="email"
                                value={user?.email || ''}
                                disabled
                                className="neo-input bg-gray-100 text-gray-500 cursor-not-allowed"
                            />
                            <p className="mt-1 text-xs text-gray-400">Email cannot be changed after registration</p>
                        </div>

                        <button type="submit" disabled={processing} className="neo-btn-primary text-sm">
                            {processing ? 'Saving...' : 'Save Changes'}
                        </button>
                    </form>
                </div>
            </div>
        </DefaultLayout>
    );
}

