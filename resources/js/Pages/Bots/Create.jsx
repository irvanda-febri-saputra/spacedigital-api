import { Head, useForm, Link, router } from '@inertiajs/react';
import DefaultLayout from '@/Layouts/DefaultLayout';

export default function CreateBot({ auth, paymentGateways }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        bot_username: '',
        bot_token: '',
        payment_gateway: '',
        status: 'active',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/bots');
    };

    return (
        <DefaultLayout user={auth?.user}>
            <Head title="Create Bot" />

            {/* Header */}
            <div className="mb-6">
                <Link href="/bots" className="text-[#8B5CF6] hover:underline text-sm font-medium">
                    ← Back to Bots
                </Link>
                <h1 className="text-2xl font-bold text-gray-900 mt-2">Create New Bot</h1>
                <p className="text-gray-400 mt-1">Add a new Telegram bot to your dashboard</p>
            </div>

            {/* Form Card */}
            <div className="max-w-2xl">
                <form onSubmit={handleSubmit}>
                    <div className="neo-card">
                        {/* Bot Information */}
                        <div className="p-6 border-b-2 border-gray-100">
                            <h2 className="text-lg font-bold text-gray-900 mb-4">Bot Information</h2>
                            
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-semibold text-gray-900 mb-2">
                                        Bot Name *
                                    </label>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className="neo-input"
                                        placeholder="My Awesome Bot"
                                        required
                                    />
                                    {errors.name && <p className="mt-2 text-sm text-red-500">{errors.name}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-semibold text-gray-900 mb-2">
                                        Bot Username
                                    </label>
                                    <div className="relative">
                                        <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">@</span>
                                        <input
                                            type="text"
                                            value={data.bot_username}
                                            onChange={(e) => setData('bot_username', e.target.value)}
                                            className="neo-input pl-8"
                                            placeholder="my_awesome_bot"
                                        />
                                    </div>
                                    {errors.bot_username && <p className="mt-2 text-sm text-red-500">{errors.bot_username}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-semibold text-gray-900 mb-2">
                                        Bot Token *
                                    </label>
                                    <input
                                        type="text"
                                        value={data.bot_token}
                                        onChange={(e) => setData('bot_token', e.target.value)}
                                        className="neo-input font-mono text-sm"
                                        placeholder="1234567890:ABCdefGHIjklMNOpqrsTUVwxyz"
                                        required
                                    />
                                    <p className="mt-1 text-xs text-gray-500">
                                        Get your bot token from @BotFather on Telegram
                                    </p>
                                    {errors.bot_token && <p className="mt-2 text-sm text-red-500">{errors.bot_token}</p>}
                                </div>
                            </div>
                        </div>

                        {/* Payment Settings */}
                        <div className="p-6 border-b-2 border-gray-100 bg-gray-50">
                            <h2 className="text-lg font-bold text-gray-900 mb-4">Payment Settings</h2>
                            
                            <div className="space-y-4">
                                <div>
                                    <label className="block text-sm font-semibold text-gray-900 mb-2">
                                        Payment Gateway *
                                    </label>
                                    <select
                                        value={data.payment_gateway}
                                        onChange={(e) => setData('payment_gateway', e.target.value)}
                                        className="neo-input"
                                        required
                                    >
                                        <option value="">Select a gateway</option>
                                        {paymentGateways?.map((gateway) => (
                                            <option key={gateway.id} value={gateway.code}>
                                                {gateway.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.payment_gateway && <p className="mt-2 text-sm text-red-500">{errors.payment_gateway}</p>}
                                </div>

                                <div>
                                    <label className="block text-sm font-semibold text-gray-900 mb-2">
                                        Status
                                    </label>
                                    <select
                                        value={data.status}
                                        onChange={(e) => setData('status', e.target.value)}
                                        className="neo-input"
                                    >
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        {/* Submit */}
                        <div className="p-6 flex justify-end gap-3">
                            <Link href="/bots" className="neo-btn-secondary text-sm">
                                Cancel
                            </Link>
                            <button type="submit" disabled={processing} className="neo-btn-primary text-sm">
                                {processing ? 'Creating...' : 'Create Bot'}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </DefaultLayout>
    );
}
