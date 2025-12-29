import { Head, useForm, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import DefaultLayout from '@/Layouts/DefaultLayout';
import { IconWallet, IconCreditCard, IconCheckCircle, IconLoader, IconWarning } from '@/Components/Icons';

export default function AtlanticWithdraw({ auth, balance: initialBalance, pendingBalance: initialPending, banks: initialBanks, withdrawFee, flash }) {
    const [verifying, setVerifying] = useState(false);
    const [verified, setVerified] = useState(false);
    const [verifyError, setVerifyError] = useState('');
    
    // Lazy-loaded data states
    const [balance, setBalance] = useState(initialBalance);
    const [pendingBalance, setPendingBalance] = useState(initialPending);
    const [banks, setBanks] = useState(initialBanks || []);
    const [loadingBalance, setLoadingBalance] = useState(initialBalance === null);
    const [loadingBanks, setLoadingBanks] = useState(!initialBanks || initialBanks.length === 0);
    
    const { data, setData, post, processing, errors, reset } = useForm({
        amount: '',
        bank_code: '',
        account_name: '',
        account_number: '',
    });

    // Fetch balance and banks on mount (lazy loading)
    useEffect(() => {
        if (initialBalance === null) {
            fetch('/atlantic/withdraw/balance')
                .then(res => res.json())
                .then(result => {
                    if (result.success) {
                        setBalance(result.balance);
                        setPendingBalance(result.pendingBalance);
                    }
                })
                .catch(() => {})
                .finally(() => setLoadingBalance(false));
        }

        if (!initialBanks || initialBanks.length === 0) {
            fetch('/atlantic/withdraw/banks')
                .then(res => res.json())
                .then(result => {
                    if (result.success) {
                        setBanks(result.banks);
                    }
                })
                .catch(() => {})
                .finally(() => setLoadingBanks(false));
        }
    }, []);

    const formatCurrency = (value) => {
        if (value === null || value === undefined) return '---';
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(value);
    };

    const handleVerifyAccount = async () => {
        if (!data.bank_code || !data.account_number) {
            setVerifyError('Please select bank and enter account number first');
            return;
        }

        setVerifying(true);
        setVerifyError('');
        setVerified(false);

        try {
            const response = await fetch('/atlantic/withdraw/verify', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    bank_code: data.bank_code,
                    account_number: data.account_number,
                }),
            });

            const result = await response.json();

            if (result.success) {
                setData('account_name', result.account_name);
                // Use normalized account number from API if available
                if (result.account_number) {
                    setData('account_number', result.account_number);
                }
                setVerified(true);
            } else {
                setVerifyError(result.message || 'Account not found');
            }
        } catch (error) {
            console.error('Verify account error:', error);
            setVerifyError('Connection error: ' + (error.message || 'Unknown'));
        } finally {
            setVerifying(false);
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/atlantic/withdraw', {
            onSuccess: () => {
                reset();
                setVerified(false);
                // Refresh balance after successful withdrawal
                setLoadingBalance(true);
                fetch('/atlantic/withdraw/balance')
                    .then(res => res.json())
                    .then(result => {
                        if (result.success) {
                            setBalance(result.balance);
                            setPendingBalance(result.pendingBalance);
                        }
                    })
                    .finally(() => setLoadingBalance(false));
            },
        });
    };

    const totalWithdraw = data.amount ? parseInt(data.amount) + withdrawFee : 0;
    const canSubmit = verified && data.amount && parseInt(data.amount) >= 10000 && balance !== null && totalWithdraw <= balance;

    return (
        <DefaultLayout user={auth?.user}>
            <Head title="Atlantic Withdraw" />

            {/* Header */}
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">Atlantic Withdraw</h1>
                <p className="text-gray-500 mt-1">Withdraw your Atlantic H2H balance to bank or e-wallet</p>
            </div>

            {/* Weekend Notice */}
            <div className="neo-card bg-amber-50 border-amber-500 p-4 mb-6">
                <div className="flex items-start gap-3">
                    <IconWarning className="w-5 h-5 text-amber-600 mt-0.5" />
                    <div>
                        <span className="text-amber-800 font-medium">Atlantic Withdrawal Notice</span>
                        <p className="text-amber-700 text-sm mt-1">
                            Withdrawals are not available on weekends (Saturday & Sunday). Please try again on Monday.
                        </p>
                    </div>
                </div>
            </div>

            {/* Flash Messages */}
            {flash?.success && (
                <div className="neo-card bg-green-50 border-green-500 p-4 mb-6">
                    <div className="flex items-center gap-3">
                        <IconCheckCircle className="w-5 h-5 text-green-600" />
                        <span className="text-green-800 font-medium">{flash.success}</span>
                    </div>
                </div>
            )}
            {flash?.error && (
                <div className="neo-card bg-red-50 border-red-500 p-4 mb-6">
                    <div className="flex items-center gap-3">
                        <IconWarning className="w-5 h-5 text-red-600" />
                        <span className="text-red-800 font-medium">{flash.error}</span>
                    </div>
                </div>
            )}

            {/* Balance Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div className="neo-card p-6 bg-gradient-to-br from-[#8B5CF6] to-[#6D28D9]">
                    <div className="flex items-center gap-4">
                        <div className="w-14 h-14 bg-white/20 rounded-lg flex items-center justify-center">
                            {loadingBalance ? (
                                <IconLoader className="w-7 h-7 text-white" />
                            ) : (
                                <IconWallet className="w-7 h-7 text-white" />
                            )}
                        </div>
                        <div>
                            <p className="text-white/80 text-sm font-semibold">Available Balance</p>
                            <p className="text-white text-2xl font-bold">
                                {loadingBalance ? 'Loading...' : formatCurrency(balance)}
                            </p>
                        </div>
                    </div>
                </div>
                <div className="neo-card p-6 bg-gradient-to-br from-pink-500 to-rose-500">
                    <div className="flex items-center gap-4">
                        <div className="w-14 h-14 bg-white/20 rounded-lg flex items-center justify-center">
                            {loadingBalance ? (
                                <IconLoader className="w-7 h-7 text-white" />
                            ) : (
                                <IconCreditCard className="w-7 h-7 text-white" />
                            )}
                        </div>
                        <div>
                            <p className="text-white/80 text-sm font-semibold">Pending Settlement</p>
                            <p className="text-white text-2xl font-bold">
                                {loadingBalance ? 'Loading...' : (pendingBalance === null ? 'N/A' : formatCurrency(pendingBalance))}
                            </p>
                            {pendingBalance === null && !loadingBalance && (
                                <p className="text-white/60 text-xs mt-1">Not available from API</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Withdraw Form */}
            <div className="neo-card p-6">
                <h2 className="text-xl font-bold text-gray-900 mb-2">Withdraw Funds</h2>
                <p className="text-gray-500 text-sm mb-6">Fill in the details below to withdraw your balance</p>

                <form onSubmit={handleSubmit} className="space-y-5">
                    {/* Amount */}
                    <div>
                        <label className="block text-sm font-semibold text-gray-900 mb-2">
                            Amount Withdraw *
                        </label>
                        <input
                            type="number"
                            value={data.amount}
                            onChange={(e) => setData('amount', e.target.value)}
                            className="neo-input"
                            placeholder="Enter amount (min. Rp 10.000)"
                            min="10000"
                        />
                        <p className="mt-1.5 text-sm text-gray-500">
                            Withdrawal fee: <span className="font-semibold text-[#8B5CF6]">{formatCurrency(withdrawFee)}</span>
                        </p>
                        {errors.amount && <p className="mt-1 text-sm text-red-500">{errors.amount}</p>}
                    </div>

                    {/* Bank Selection */}
                    <div>
                        <label className="block text-sm font-semibold text-gray-900 mb-2">
                            Bank / E-Wallet *
                        </label>
                        <select
                            value={data.bank_code}
                            onChange={(e) => {
                                setData('bank_code', e.target.value);
                                setVerified(false);
                                setData('account_name', '');
                            }}
                            className="neo-input"
                            disabled={loadingBanks}
                        >
                            <option value="">
                                {loadingBanks ? 'Loading banks...' : 'Select Bank or E-Wallet'}
                            </option>
                            {banks.map((bank) => (
                                <option key={bank.code} value={bank.code}>
                                    {bank.name} ({bank.type})
                                </option>
                            ))}
                        </select>
                        {errors.bank_code && <p className="mt-1 text-sm text-red-500">{errors.bank_code}</p>}
                    </div>

                    {/* Account Number */}
                    <div>
                        <label className="block text-sm font-semibold text-gray-900 mb-2">
                            Account Number *
                        </label>
                        <div className="flex gap-3">
                            <input
                                type="text"
                                value={data.account_number}
                                onChange={(e) => {
                                    setData('account_number', e.target.value);
                                    setVerified(false);
                                    setData('account_name', '');
                                }}
                                className="neo-input flex-1"
                                placeholder="Enter account number"
                            />
                            <button
                                type="button"
                                onClick={handleVerifyAccount}
                                disabled={verifying || !data.bank_code || !data.account_number}
                                className="neo-btn-secondary px-4 py-2 whitespace-nowrap disabled:opacity-50"
                            >
                                {verifying ? (
                                    <IconLoader className="w-5 h-5" />
                                ) : (
                                    'Verify'
                                )}
                            </button>
                        </div>
                        {verifyError && <p className="mt-1 text-sm text-red-500">{verifyError}</p>}
                        {errors.account_number && <p className="mt-1 text-sm text-red-500">{errors.account_number}</p>}
                    </div>

                    {/* Account Name (User must enter FULL name) */}
                    <div>
                        <label className="block text-sm font-semibold text-gray-900 mb-2">
                            Account Holder Name *
                        </label>
                        <div className="relative">
                            <input
                                type="text"
                                value={data.account_name}
                                onChange={(e) => setData('account_name', e.target.value.toUpperCase())}
                                className={`neo-input ${verified ? 'bg-green-50 border-green-500' : ''}`}
                                placeholder="Enter FULL account holder name (e.g. JOHN DOE)"
                            />
                            {verified && (
                                <IconCheckCircle className="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 text-green-600" />
                            )}
                        </div>
                        {verified && data.account_name && (
                            <p className="mt-1 text-xs text-green-600">
                                ✓ Verified account. Please ensure the name matches the account holder exactly.
                            </p>
                        )}
                        {errors.account_name && <p className="mt-1 text-sm text-red-500">{errors.account_name}</p>}
                    </div>

                    {/* Summary */}
                    {data.amount && parseInt(data.amount) >= 10000 && (
                        <div className="neo-card bg-gray-50 p-4 space-y-2">
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-600">Withdraw Amount</span>
                                <span className="font-medium">{formatCurrency(parseInt(data.amount))}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-600">Fee</span>
                                <span className="font-medium text-red-500">- {formatCurrency(withdrawFee)}</span>
                            </div>
                            <hr className="border-gray-300" />
                            <div className="flex justify-between text-base font-bold">
                                <span className="text-gray-900">Total Deducted</span>
                                <span className="text-[#8B5CF6]">{formatCurrency(totalWithdraw)}</span>
                            </div>
                            {totalWithdraw > balance && (
                                <p className="text-red-500 text-sm mt-2">⚠️ Insufficient balance</p>
                            )}
                        </div>
                    )}

                    {/* Submit Button */}
                    <button
                        type="submit"
                        disabled={!canSubmit || processing}
                        className="neo-btn-primary w-full py-3 text-base disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {processing ? (
                            <span className="flex items-center justify-center gap-2">
                                <IconLoader className="w-5 h-5" />
                                Processing...
                            </span>
                        ) : (
                            'Submit Withdrawal'
                        )}
                    </button>
                </form>
            </div>
        </DefaultLayout>
    );
}
