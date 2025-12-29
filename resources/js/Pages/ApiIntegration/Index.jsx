import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import DefaultLayout from '@/Layouts/DefaultLayout';

export default function ApiIntegrationIndex({ auth, apiKey, baseUrl, endpoints }) {
    const [copied, setCopied] = useState(false);
    const [activeTab, setActiveTab] = useState('curl');
    const [regenerating, setRegenerating] = useState(false);

    const copyToClipboard = (text) => {
        navigator.clipboard.writeText(text);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const handleRegenerate = () => {
        if (confirm('Are you sure you want to regenerate your API Key? Your old key will stop working immediately.')) {
            setRegenerating(true);
            router.post('/api-integration/regenerate-key', {}, {
                onFinish: () => setRegenerating(false),
            });
        }
    };

    const codeExamples = {
        curl: `curl -X POST "${baseUrl || 'https://your-domain.com/api/public'}/payments/create" \\
  -H "X-API-Key: ${apiKey || 'your-api-key'}" \\
  -H "Content-Type: application/json" \\
  -d '{
    "amount": 50000,
    "gateway": "atlantic",
    "product_name": "Premium Plan",
    "customer_name": "John Doe"
  }'`,
        javascript: `const response = await fetch('${baseUrl || 'https://your-domain.com/api/public'}/payments/create', {
  method: 'POST',
  headers: {
    'X-API-Key': '${apiKey || 'your-api-key'}',
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    amount: 50000,
    gateway: 'atlantic',
    product_name: 'Premium Plan',
    customer_name: 'John Doe'
  })
});

const data = await response.json();
console.log(data.data.qr_string); // QRIS string`,
        php: `<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, '${baseUrl || 'https://your-domain.com/api/public'}/payments/create');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: ${apiKey || 'your-api-key'}',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'amount' => 50000,
    'gateway' => 'atlantic',
    'product_name' => 'Premium Plan',
    'customer_name' => 'John Doe'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
echo $data['data']['qr_string']; // QRIS string`,
    };

    return (
        <DefaultLayout user={auth?.user}>
            <Head title="API Integration" />

            {/* Header */}
            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">API Integration</h1>
                <p className="text-gray-400 mt-1">Integrate SpaceDigital with your applications</p>
            </div>

            <div className="space-y-6">
                {/* API Key */}
                <div className="neo-card">
                    <div className="p-6 border-b-2 border-gray-100">
                        <h2 className="text-lg font-bold text-gray-900">Your API Key</h2>
                        <p className="text-sm text-gray-500 mt-1">Use this key to authenticate your API requests</p>
                    </div>
                    <div className="p-6">
                        <div className="flex gap-3">
                            <input
                                type="text"
                                value={apiKey || 'No API Key generated'}
                                readOnly
                                className="neo-input font-mono text-sm flex-1"
                            />
                            <button
                                onClick={() => copyToClipboard(apiKey)}
                                disabled={!apiKey}
                                className="neo-btn-primary text-sm"
                            >
                                {copied ? '✓ Copied!' : 'Copy'}
                            </button>
                            <button
                                onClick={handleRegenerate}
                                disabled={regenerating}
                                className="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg border-2 border-black shadow-[2px_2px_0px_0px_rgba(0,0,0,1)] hover:shadow-[3px_3px_0px_0px_rgba(0,0,0,1)] transition-all text-sm font-medium"
                            >
                                {regenerating ? 'Regenerating...' : 'Regenerate'}
                            </button>
                        </div>
                        <p className="mt-3 text-sm text-amber-600">
                            ⚠️ Keep this key secret. Do not share it publicly.
                        </p>
                    </div>
                </div>

                {/* Endpoints */}
                <div className="neo-card">
                    <div className="p-6 border-b-2 border-gray-100 bg-gray-50">
                        <h2 className="text-lg font-bold text-gray-900">Available Endpoints</h2>
                    </div>
                    <div className="divide-y-2 divide-gray-100">
                        {endpoints?.map((endpoint, idx) => (
                            <div key={idx} className="p-6">
                                <div className="flex items-center gap-3 mb-2">
                                    <span className={`px-2 py-1 text-xs font-bold rounded ${
                                        endpoint.method === 'GET' 
                                            ? 'bg-green-100 text-green-700' 
                                            : endpoint.method === 'POST'
                                            ? 'bg-blue-100 text-blue-700'
                                            : 'bg-yellow-100 text-yellow-700'
                                    }`}>
                                        {endpoint.method}
                                    </span>
                                    <code className="text-sm font-mono text-gray-900">{endpoint.path}</code>
                                </div>
                                <p className="text-sm text-gray-600">{endpoint.description}</p>
                            </div>
                        )) || (
                            <>
                                <div className="p-6">
                                    <div className="flex items-center gap-3 mb-2">
                                        <span className="px-2 py-1 text-xs font-bold rounded bg-blue-100 text-blue-700">POST</span>
                                        <code className="text-sm font-mono text-gray-900">/api/bot/transactions</code>
                                    </div>
                                    <p className="text-sm text-gray-600">Create a new transaction</p>
                                </div>
                                <div className="p-6">
                                    <div className="flex items-center gap-3 mb-2">
                                        <span className="px-2 py-1 text-xs font-bold rounded bg-green-100 text-green-700">GET</span>
                                        <code className="text-sm font-mono text-gray-900">/api/bot/transactions/{'{id}'}</code>
                                    </div>
                                    <p className="text-sm text-gray-600">Get transaction details</p>
                                </div>
                                <div className="p-6">
                                    <div className="flex items-center gap-3 mb-2">
                                        <span className="px-2 py-1 text-xs font-bold rounded bg-green-100 text-green-700">GET</span>
                                        <code className="text-sm font-mono text-gray-900">/api/bot/settings</code>
                                    </div>
                                    <p className="text-sm text-gray-600">Get bot settings and products</p>
                                </div>
                            </>
                        )}
                    </div>
                </div>

                {/* Code Examples */}
                <div className="neo-card">
                    <div className="p-6 border-b-2 border-gray-100">
                        <h2 className="text-lg font-bold text-gray-900">Code Examples</h2>
                    </div>
                    
                    {/* Tabs */}
                    <div className="flex border-b-2 border-gray-100">
                        {['curl', 'javascript', 'php'].map((tab) => (
                            <button
                                key={tab}
                                onClick={() => setActiveTab(tab)}
                                className={`px-6 py-3 text-sm font-semibold transition-colors ${
                                    activeTab === tab
                                        ? 'text-[#8B5CF6] border-b-3 border-[#8B5CF6] -mb-[2px]'
                                        : 'text-gray-500 hover:text-gray-700'
                                }`}
                            >
                                {tab.toUpperCase()}
                            </button>
                        ))}
                    </div>
                    
                    {/* Code Block */}
                    <div className="p-6 bg-gray-900 rounded-b-lg">
                        <pre className="text-sm text-gray-300 overflow-x-auto">
                            <code>{codeExamples[activeTab]}</code>
                        </pre>
                    </div>
                </div>
            </div>
        </DefaultLayout>
    );
}
