import { Head, useForm, usePage } from '@inertiajs/react';
import DefaultLayout from '@/Layouts/DefaultLayout';
import { useState, useEffect } from 'react';
import {
    IconCheckCircle,
    IconXCircle,
    IconClock,
    IconWarning,
    IconCoins,
    IconPlus,
    IconLoader,
} from '@/Components/Icons';
import { Skeleton } from '@/Components/Skeleton';

function QRCodeDisplay({ qrString, qrImage }) {
    const qrUrl = qrImage || `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qrString)}`;
    
    return (
        <div className="text-center">
            <div className="inline-block p-4 bg-white border-3 border-gray-900 rounded-xl shadow-[4px_4px_0_#1A1A1A]">
                <img 
                    src={qrUrl} 
                    alt="Payment QR Code" 
                    className="rounded-lg"
                    style={{ width: 200, height: 200 }}
                />
            </div>
            <p className="text-sm font-semibold text-gray-700 mt-4">
                Scan to Pay
            </p>
            <p className="text-xs text-gray-500 mt-1">
                Use your mobile banking app to scan this QR code
            </p>
        </div>
    );
}

function TransactionResult({ transaction, onReset }) {
    const [status, setStatus] = useState(transaction.status || 'pending');
    const [polling, setPolling] = useState(true);

    useEffect(() => {
        if (status !== 'pending' || !polling) return;

        const interval = setInterval(async () => {
            try {
                const response = await fetch(`/create-transaction/${transaction.order_id}/status`);
                const data = await response.json();
                
                if (data.success && data.data.status !== 'pending') {
                    setStatus(data.data.status);
                    setPolling(false);
                }
            } catch (error) {
                console.error('Polling error:', error);
            }
        }, 3000);

        return () => clearInterval(interval);
    }, [transaction.order_id, status, polling]);

    return (
        <div className="space-y-6">
            {/* Success Message */}
            <div className="neo-card p-4 bg-green-50 border-green-600 flex items-center gap-3">
                <IconCheckCircle className="h-6 w-6 text-green-600" />
                <p className="text-green-700 font-medium">Transaction created successfully! Use the QR code below to complete payment.</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Transaction Details */}
                <div className="neo-card p-6">
                    <h3 className="text-lg font-bold text-gray-900 mb-4">Transaction Details</h3>
                    
                    <div className="space-y-4">
                        <div className="flex justify-between items-center py-2 border-b border-gray-100">
                            <span className="text-sm font-semibold text-gray-500">Transaction ID</span>
                            <span className="font-mono text-sm text-gray-900 bg-gray-100 px-2 py-1 rounded">{transaction.order_id}</span>
                        </div>
                        <div className="flex justify-between items-center py-2 border-b border-gray-100">
                            <span className="text-sm font-semibold text-gray-500 flex items-center gap-1.5">
                                <IconCoins className="h-4 w-4" /> Amount
                            </span>
                            <span className="font-bold text-[#8B5CF6]">Rp {transaction.amount?.toLocaleString()}</span>
                        </div>
                        <div className="flex justify-between items-center py-2 border-b border-gray-100">
                            <span className="text-sm font-semibold text-gray-500">Status</span>
                            <span className={`neo-badge-${status === 'success' ? 'success' : status === 'pending' ? 'warning' : 'gray'} flex items-center gap-1.5`}>
                                {status === 'pending' && <><IconClock className="h-3.5 w-3.5" /> UNPAID</>}
                                {status === 'success' && <><IconCheckCircle className="h-3.5 w-3.5" /> PAID</>}
                                {status === 'expired' && <><IconClock className="h-3.5 w-3.5" /> EXPIRED</>}
                                {status === 'failed' && <><IconXCircle className="h-3.5 w-3.5" /> FAILED</>}
                            </span>
                        </div>
                        <div className="flex justify-between items-center py-2">
                            <span className="text-sm font-semibold text-gray-500">Expires</span>
                            <span className="text-sm text-red-500">{new Date(transaction.expires_at).toLocaleString()}</span>
                        </div>
                    </div>
                </div>

                {/* QR Code */}
                <div className="neo-card p-6">
                    <h3 className="text-lg font-bold text-gray-900 mb-4 text-center">Payment QR Code</h3>
                    
                    {status === 'pending' ? (
                        <QRCodeDisplay qrString={transaction.qr_string} qrImage={transaction.qr_image} />
                    ) : status === 'success' ? (
                        <div className="text-center py-8">
                            <div className="w-16 h-16 mx-auto bg-green-100 rounded-xl flex items-center justify-center border-2 border-green-600">
                                <IconCheckCircle className="h-8 w-8 text-green-600" />
                            </div>
                            <p className="text-green-600 font-bold mt-4">Payment Successful!</p>
                        </div>
                    ) : (
                        <div className="text-center py-8">
                            <div className="w-16 h-16 mx-auto bg-gray-100 rounded-xl flex items-center justify-center border-2 border-gray-400">
                                <IconClock className="h-8 w-8 text-gray-400" />
                            </div>
                            <p className="text-gray-500 mt-4">Transaction Expired</p>
                        </div>
                    )}

                    {status === 'pending' && (
                        <div className="mt-4 p-3 bg-yellow-50 border-2 border-yellow-400 rounded-lg text-center">
                            <p className="text-xs text-yellow-700 flex items-center justify-center gap-1.5">
                                <IconWarning className="h-3.5 w-3.5" /> Expires on {new Date(transaction.expires_at).toLocaleString()}
                            </p>
                        </div>
                    )}
                </div>
            </div>

            {/* Create Another Button */}
            <div className="text-center">
                <button onClick={onReset} className="neo-btn-primary inline-flex items-center gap-2">
                    <IconPlus className="h-4 w-4" /> Create Another Transaction
                </button>
            </div>
        </div>
    );
}

export default function Index({ auth, gateways }) {
    const { flash } = usePage().props;
    const [loading, setLoading] = useState(true);
    const [showResult, setShowResult] = useState(false);
    const [transaction, setTransaction] = useState(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        gateway_id: gateways[0]?.id || '',
        amount: '',
        product_name: '',
        customer_name: '',
    });

    // Initial loading delay
    useEffect(() => {
        const timer = setTimeout(() => setLoading(false), 600);
        return () => clearTimeout(timer);
    }, []);

    useEffect(() => {
        if (flash?.transaction?.success) {
            setTransaction(flash.transaction);
            setShowResult(true);
        }
    }, [flash]);

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/create-transaction');
    };

    const handleReset = () => {
        setShowResult(false);
        setTransaction(null);
        reset();
    };

    // Loading skeleton
    if (loading) {
        return (
            <DefaultLayout user={auth?.user}>
                <Head title="Create Transaction" />
                <div className="max-w-xl space-y-6">
                    <div className="mb-6">
                        <Skeleton className="h-8 w-48 mb-2" />
                        <Skeleton className="h-5 w-80" />
                    </div>
                    
                    <div className="neo-card p-6 space-y-6">
                        <div>
                            <Skeleton className="h-4 w-20 mb-2" />
                            <Skeleton className="h-12 w-full rounded-lg" />
                        </div>
                        <div>
                            <Skeleton className="h-4 w-16 mb-2" />
                            <Skeleton className="h-12 w-full rounded-lg" />
                        </div>
                        <div>
                            <Skeleton className="h-4 w-28 mb-2" />
                            <Skeleton className="h-12 w-full rounded-lg" />
                        </div>
                        <div>
                            <Skeleton className="h-4 w-32 mb-2" />
                            <Skeleton className="h-12 w-full rounded-lg" />
                        </div>
                        <Skeleton className="h-12 w-full rounded-lg" />
                    </div>
                </div>
            </DefaultLayout>
        );
    }

    return (
        <DefaultLayout user={auth?.user}>
            <Head title="Create Transaction" />

            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">Create Transaction</h1>
                <p className="text-gray-500 mt-1">
                    Manually create a payment transaction for testing purposes.
                </p>
            </div>

            {showResult && transaction ? (
                <TransactionResult transaction={transaction} onReset={handleReset} />
            ) : (
                <div className="max-w-xl">
                    <form onSubmit={handleSubmit} className="neo-card p-6 space-y-6">
                        {/* Amount */}
                        <div>
                            <label className="block text-sm font-semibold text-gray-900 mb-2">
                                Amount *
                            </label>
                            <div className="relative">
                                <span className="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-500 font-semibold">Rp</span>
                                <input
                                    type="number"
                                    value={data.amount}
                                    onChange={(e) => setData('amount', e.target.value)}
                                    placeholder="Enter amount"
                                    className="neo-input pl-12"
                                    required
                                    min="100"
                                />
                            </div>
                            {errors.amount && <p className="text-red-500 text-sm mt-1">{errors.amount}</p>}
                        </div>

                        {/* Project/Gateway */}
                        <div>
                            <label className="block text-sm font-semibold text-gray-900 mb-2">
                                Project *
                            </label>
                            <select
                                value={data.gateway_id}
                                onChange={(e) => setData('gateway_id', e.target.value)}
                                className="neo-input"
                                required
                            >
                                {gateways.map((gateway) => (
                                    <option key={gateway.id} value={gateway.id}>
                                        {gateway.name}
                                    </option>
                                ))}
                            </select>
                            {errors.gateway_id && <p className="text-red-500 text-sm mt-1">{errors.gateway_id}</p>}
                        </div>

                        {/* Product Name */}
                        <div>
                            <label className="block text-sm font-semibold text-gray-900 mb-2">
                                Product Name *
                            </label>
                            <input
                                type="text"
                                value={data.product_name}
                                onChange={(e) => setData('product_name', e.target.value)}
                                placeholder="e.g. Spotify Premium"
                                className="neo-input"
                                required
                            />
                            {errors.product_name && <p className="text-red-500 text-sm mt-1">{errors.product_name}</p>}
                        </div>

                        {/* Customer Name */}
                        <div>
                            <label className="block text-sm font-semibold text-gray-900 mb-2">
                                Customer Name *
                            </label>
                            <input
                                type="text"
                                value={data.customer_name}
                                onChange={(e) => setData('customer_name', e.target.value)}
                                placeholder="e.g. @username or John Doe"
                                className="neo-input"
                                required
                            />
                            {errors.customer_name && <p className="text-red-500 text-sm mt-1">{errors.customer_name}</p>}
                        </div>

                        {/* Error */}
                        {errors.payment && (
                            <div className="p-4 bg-red-50 border-2 border-red-400 rounded-lg">
                                <p className="text-red-600">{errors.payment}</p>
                            </div>
                        )}

                        {/* Submit */}
                        <button
                            type="submit"
                            disabled={processing || gateways.length === 0}
                            className="neo-btn-primary w-full flex items-center justify-center gap-2"
                        >
                            {processing ? (
                                <>
                                    <IconLoader className="h-4 w-4" />
                                    Creating...
                                </>
                            ) : (
                                'Create Transaction'
                            )}
                        </button>

                        {gateways.length === 0 && (
                            <div className="mt-4 p-4 bg-amber-50 border-2 border-amber-400 rounded-xl shadow-[3px_3px_0_#f59e0b]">
                                <div className="flex items-center gap-3">
                                    <div className="w-8 h-8 bg-amber-400 rounded-full flex items-center justify-center flex-shrink-0">
                                        <IconWarning className="h-4 w-4 text-white" />
                                    </div>
                                    <p className="text-sm font-medium text-amber-800">
                                        No payment gateways configured. Please add a gateway in <a href="/payment-gateways" className="underline font-bold hover:text-amber-900">Payment Gateways</a>.
                                    </p>
                                </div>
                            </div>
                        )}
                    </form>
                </div>
            )}
        </DefaultLayout>
    );
}
