const documentationHTML = `
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dokumentasi API QRIS - Order Kuota</title>
  <style>
    body { font-family: Arial, sans-serif; background: #1a1a2e; color: #eee; padding: 20px; }
    h1 { color: #8B5CF6; }
    h2 { margin-top: 30px; color: #a78bfa; }
    pre { background: #16213e; padding: 10px; border-radius: 5px; overflow-x: auto; }
    code { background: #16213e; padding: 2px 5px; }
    .endpoint { background: #0f3460; padding: 15px; border-radius: 8px; margin: 15px 0; }
    .method { background: #8B5CF6; color: white; padding: 4px 10px; border-radius: 4px; font-weight: bold; }
  </style>
</head>
<body>
  <h1>Dokumentasi API QRIS - Order Kuota</h1>

  <h2>1. Login (Step 1: Minta OTP)</h2>
  <div class="endpoint">
    <p><span class="method">POST</span> <code>/api/login</code></p>
    <pre>username=USERNAME&password=PASSWORD</pre>
    <p>Deskripsi: Mengirimkan OTP ke Email.</p>
  </div>

  <h2>2. Login (Step 2: Verifikasi OTP)</h2>
  <div class="endpoint">
    <p><span class="method">POST</span> <code>/api/get-token</code></p>
    <pre>username=USERNAME&otp=123456</pre>
    <p>Deskripsi: Verifikasi OTP dan mendapatkan token akses.</p>
  </div>

  <h2>3. Cek Mutasi QRIS</h2>
  <div class="endpoint">
    <p><span class="method">POST</span> <code>/api/qris-history</code></p>
    <pre>username=USERNAME&token=AUTH_TOKEN&jenis=masuk</pre>
    <p>Deskripsi: Menampilkan daftar transaksi QRIS (masuk/keluar).</p>
  </div>

  <h2>4. Tarik Saldo QRIS</h2>
  <div class="endpoint">
    <p><span class="method">POST</span> <code>/api/qris-withdraw</code></p>
    <pre>username=USERNAME&token=AUTH_TOKEN&amount=10000</pre>
    <p>Deskripsi: Menarik saldo QRIS ke rekening terdaftar.</p>
  </div>

  <hr>
  <p><small>UNOFFICIAL API - Gunakan dengan bijak!</small></p>
</body>
</html>
`;

const API_BASE = "https://app.orderkuota.com/api/v2";

// Generate SHA512 signature for Order Kuota API
async function generateSignature(params, timestamp) {
    const formatted_params = [];

    for (const key in params) {
        const value = String(params[key]);
        formatted_params.push(value.length + value);
    }

    formatted_params.sort();
    const var_a = formatted_params.join("");
    const hash_input = timestamp + var_a;

    // Use Web Crypto API for SHA512
    const encoder = new TextEncoder();
    const data = encoder.encode(hash_input);
    const hashBuffer = await crypto.subtle.digest('SHA-512', data);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
}

// Make signed request to Order Kuota API
async function makeSignedRequest(path, params) {
    const timestamp = Date.now().toString();
    params.request_time = timestamp;

    const signature = await generateSignature(params, timestamp);

    const body = new URLSearchParams(params).toString();

    const response = await fetch(`https://app.orderkuota.com${path}`, {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            "User-Agent": "okhttp/4.12.0",
            "Signature": signature,
            "Timestamp": timestamp,
        },
        body: body
    });

    const text = await response.text();

    try {
        return { status: response.status, body: JSON.parse(text) };
    } catch (e) {
        throw new Error("Gagal mem-parse response JSON: " + e.message);
    }
}

export default {
    async fetch(request, env) {
        const url = new URL(request.url);
        const pathname = url.pathname;

        // CORS headers
        const corsHeaders = {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type',
        };

        // Handle preflight
        if (request.method === 'OPTIONS') {
            return new Response(null, { headers: corsHeaders });
        }

        // Documentation page
        if (request.method === "GET" && (pathname === "/" || pathname === "/docs")) {
            return new Response(documentationHTML, {
                headers: { "Content-Type": "text/html; charset=UTF-8", ...corsHeaders }
            });
        }

        if (request.method !== "POST") {
            return new Response("Method Not Allowed", { status: 405 });
        }

        // Parse body - support both JSON and form-urlencoded
        let data = {};
        const contentType = request.headers.get('content-type') || '';

        try {
            if (contentType.includes('application/json')) {
                data = await request.json();
            } else if (contentType.includes('form-urlencoded')) {
                const formData = await request.formData();
                data = Object.fromEntries(formData.entries());
            } else {
                // Try JSON first, then form data
                const text = await request.text();
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    const params = new URLSearchParams(text);
                    data = Object.fromEntries(params.entries());
                }
            }
        } catch (e) {
            return jsonResponse({ error: 'Invalid request body', details: e.message }, 400, corsHeaders);
        }

        try {
            switch (pathname) {
                case "/api/login":
                    return await loginRequest(data.username, data.password, corsHeaders);
                case "/api/get-token":
                    return await getToken(data.username, data.otp, corsHeaders);
                case "/api/qris-history":
                case "/api/mutasi":
                    return await getQrisHistory(data.username, data.token, data.jenis || "masuk", corsHeaders);
                case "/api/qris-withdraw":
                    return await withdrawQris(data.username, data.token, data.amount, corsHeaders);
                case "/api/unified-mutations":
                    return await getUnifiedMutations(data, corsHeaders);
                case "/api/create-transaction":
                    return await createTransaction(data, corsHeaders);
                default:
                    return jsonResponse({ error: "Not Found" }, 404, corsHeaders);
            }
        } catch (err) {
            return jsonResponse({ error: err.message }, 500, corsHeaders);
        }
    }
};

async function loginRequest(username, password, corsHeaders) {
    if (!username || !password) {
        return jsonResponse({ success: false, message: 'Username dan password wajib diisi' }, 400, corsHeaders);
    }

    try {
        const params = {
            username,
            password,
            app_reg_id: "feWAyrROTHe_RYH3Sbruw8:APA91bFbdiCCuyMLLTtieOr4W5fiSlzPHwUOe9w75UwmiHt7zywlgKi_zlKi5WUSq6pJdqHNkRD7J98p2hU7UBKK5R2wh5xcOQRhLoyb9PNWXTDiFmjrua4",
            phone_uuid: "feWAyrROTHe_RYH3Sbruw8",
            phone_model: "23124RA7EO",
            phone_android_version: "15",
            app_version_code: "251029",
            app_version_name: "25.10.29",
            ui_mode: "light",
        };

        const result = await makeSignedRequest("/api/v2/login", params);
        return jsonResponse(result.body, result.status, corsHeaders);
    } catch (err) {
        return jsonResponse({ success: false, error: err.message }, 500, corsHeaders);
    }
}

async function getToken(username, otp, corsHeaders) {
    if (!username || !otp) {
        return jsonResponse({ success: false, message: 'Username dan OTP wajib diisi' }, 400, corsHeaders);
    }

    try {
        // OTP is sent as password in the second login request
        const params = {
            username,
            password: otp,
            app_reg_id: "feWAyrROTHe_RYH3Sbruw8:APA91bFbdiCCuyMLLTtieOr4W5fiSlzPHwUOe9w75UwmiHt7zywlgKi_zlKi5WUSq6pJdqHNkRD7J98p2hU7UBKK5R2wh5xcOQRhLoyb9PNWXTDiFmjrua4",
            phone_uuid: "feWAyrROTHe_RYH3Sbruw8",
            phone_model: "23124RA7EO",
            phone_android_version: "15",
            app_version_code: "251029",
            app_version_name: "25.10.29",
            ui_mode: "light",
        };

        const result = await makeSignedRequest("/api/v2/login", params);
        return jsonResponse(result.body, result.status, corsHeaders);
    } catch (err) {
        return jsonResponse({ success: false, error: err.message }, 500, corsHeaders);
    }
}

async function getQrisHistory(username, token, jenis, corsHeaders) {
    if (!username || !token) {
        return jsonResponse({ success: false, message: 'Username dan token wajib diisi' }, 400, corsHeaders);
    }

    // Parse token to get accountId
    const tokenParts = token.split(':');
    if (tokenParts.length !== 2) {
        return jsonResponse({
            success: false,
            message: "Format token tidak valid. Harus 'accountId:actualToken'."
        }, 400, corsHeaders);
    }

    const [accountId, _] = tokenParts;

    try {
        const params = {
            app_reg_id: "feWAyrROTHe_RYH3Sbruw8:APA91bFbdiCCuyMLLTtieOr4W5fiSlzPHwUOe9w75UwmiHt7zywlgKi_zlKi5WUSq6pJdqHNkRD7J98p2hU7UBKK5R2wh5xcOQRhLoyb9PNWXTDiFmjrua4",
            phone_uuid: "feWAyrROTHe_RYH3Sbruw8",
            phone_model: "23124RA7EO",
            phone_android_version: "15",
            app_version_code: "251029",
            app_version_name: "25.10.29",
            ui_mode: "light",
            auth_username: username,
            auth_token: token,
            "requests[0]": "account",
            "requests[qris_history][keterangan]": "",
            "requests[qris_history][jumlah]": "",
            "requests[qris_history][page]": "1",
            "requests[qris_history][dari_tanggal]": "",
            "requests[qris_history][ke_tanggal]": "",
        };

        const result = await makeSignedRequest(`/api/v2/qris/mutasi/${accountId}`, params);
        return jsonResponse(result.body, result.status, corsHeaders);
    } catch (err) {
        return jsonResponse({ success: false, error: err.message }, 500, corsHeaders);
    }
}

async function withdrawQris(username, token, amount, corsHeaders) {
    if (!username || !token || !amount) {
        return jsonResponse({ success: false, message: 'Username, token, dan amount wajib diisi' }, 400, corsHeaders);
    }

    try {
        const params = {
            auth_token: token,
            auth_username: username,
            "requests[qris_withdraw][amount]": amount,
            app_reg_id: "feWAyrROTHe_RYH3Sbruw8:APA91bFbdiCCuyMLLTtieOr4W5fiSlzPHwUOe9w75UwmiHt7zywlgKi_zlKi5WUSq6pJdqHNkRD7J98p2hU7UBKK5R2wh5xcOQRhLoyb9PNWXTDiFmjrua4",
            phone_uuid: "feWAyrROTHe_RYH3Sbruw8",
            phone_model: "23124RA7EO",
            phone_android_version: "15",
            app_version_code: "251029",
            app_version_name: "25.10.29",
            ui_mode: "light",
        };

        const result = await makeSignedRequest("/api/v2/get", params);
        return jsonResponse(result.body, result.status, corsHeaders);
    } catch (err) {
        return jsonResponse({ success: false, error: err.message }, 500, corsHeaders);
    }
}

/**
 * Unified Mutations Endpoint
 * Supports: orderkuota, qiospay
 * Returns normalized format with ref_id for matching
 */
async function getUnifiedMutations(data, corsHeaders) {
    const gateway = data.gateway || 'orderkuota';
    
    try {
        if (gateway === 'orderkuota') {
            return await getOrderKuotaMutations(data, corsHeaders);
        } else if (gateway === 'qiospay') {
            return await getQiosPayMutations(data, corsHeaders);
        } else {
            return jsonResponse({ 
                success: false, 
                error: `Unsupported gateway: ${gateway}` 
            }, 400, corsHeaders);
        }
    } catch (err) {
        return jsonResponse({ 
            success: false, 
            gateway,
            error: err.message 
        }, 500, corsHeaders);
    }
}

/**
 * Get OrderKuota mutations with normalized format
 */
async function getOrderKuotaMutations(data, corsHeaders) {
    const { username, token } = data;
    
    if (!username || !token) {
        return jsonResponse({ 
            success: false, 
            error: 'Username dan token wajib diisi' 
        }, 400, corsHeaders);
    }

    // Parse token to get accountId
    const tokenParts = token.split(':');
    if (tokenParts.length !== 2) {
        return jsonResponse({
            success: false,
            error: "Format token tidak valid. Harus 'accountId:actualToken'."
        }, 400, corsHeaders);
    }

    const [accountId, _] = tokenParts;

    const params = {
        app_reg_id: "feWAyrROTHe_RYH3Sbruw8:APA91bFbdiCCuyMLLTtieOr4W5fiSlzPHwUOe9w75UwmiHt7zywlgKi_zlKi5WUSq6pJdqHNkRD7J98p2hU7UBKK5R2wh5xcOQRhLoyb9PNWXTDiFmjrua4",
        phone_uuid: "feWAyrROTHe_RYH3Sbruw8",
        phone_model: "23124RA7EO",
        phone_android_version: "15",
        app_version_code: "251029",
        app_version_name: "25.10.29",
        ui_mode: "light",
        auth_username: username,
        auth_token: token,
        "requests[0]": "account",
        "requests[qris_history][keterangan]": "",
        "requests[qris_history][jumlah]": "",
        "requests[qris_history][page]": "1",
        "requests[qris_history][dari_tanggal]": "",
        "requests[qris_history][ke_tanggal]": "",
    };

    const result = await makeSignedRequest(`/api/v2/qris/mutasi/${accountId}`, params);
    
    if (!result.body || !result.body.success) {
        return jsonResponse({
            success: false,
            gateway: 'orderkuota',
            error: result.body?.message || 'Failed to get mutations'
        }, result.status, corsHeaders);
    }

    // Normalize OrderKuota format to unified format
    const rawMutations = result.body.qris_history?.results || [];
    const mutations = rawMutations
        .filter(m => m.status === 'IN') // Only incoming payments
        .map(m => {
            // Parse kredit format "10.000" -> 10000
            const amount = parseInt(String(m.kredit || '0').replace(/[.,]/g, ''), 10);
            
            // Parse tanggal format "11/12/2025 19:44" -> ISO
            let paidAt = null;
            if (m.tanggal) {
                const parts = m.tanggal.match(/(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})/);
                if (parts) {
                    paidAt = `${parts[3]}-${parts[2]}-${parts[1]}T${parts[4]}:${parts[5]}:00+07:00`;
                }
            }
            
            return {
                // Use mutation id as unique identifier for deduplication
                ref_id: String(m.id || ''),
                keterangan: m.keterangan || '',  // Keep for logging/debugging
                amount: amount,
                status: 'paid',
                paid_at: paidAt,
                raw: m  // Include original for debugging
            };
        });

    return jsonResponse({
        success: true,
        gateway: 'orderkuota',
        mutations: mutations
    }, 200, corsHeaders);
}

/**
 * Get QiosPay mutations with normalized format
 */
async function getQiosPayMutations(data, corsHeaders) {
    const { merchant_code, api_key } = data;
    
    if (!merchant_code || !api_key) {
        return jsonResponse({ 
            success: false, 
            error: 'merchant_code dan api_key wajib diisi' 
        }, 400, corsHeaders);
    }

    try {
        const url = `https://qiospay.id/api/mutasi/qris/${merchant_code}/${api_key}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: { 'User-Agent': 'UnifiedPaymentWorker/1.0' }
        });
        
        const result = await response.json();
        
        if (!result.data) {
            return jsonResponse({
                success: false,
                gateway: 'qiospay',
                error: 'No mutation data'
            }, 400, corsHeaders);
        }

        // Normalize QiosPay format to unified format
        // Try multiple possible unique identifiers
        const mutations = result.data
            .filter(m => m.type === 'CR') // Only credit (incoming)
            .map(m => ({
                ref_id: String(m.id || m.issuer_reff || m.buyer_reff || m.refnum || m.ket || m.keterangan || m.sender_reff || m.trxid || ''),
                amount: parseInt(m.amount || m.nominal || 0, 10),
                status: 'paid',
                paid_at: m.date || m.created_at || m.time || null,
                raw: m
            }));

        return jsonResponse({
            success: true,
            gateway: 'qiospay',
            mutations: mutations
        }, 200, corsHeaders);
        
    } catch (err) {
        return jsonResponse({
            success: false,
            gateway: 'qiospay',
            error: err.message
        }, 500, corsHeaders);
    }
}

/**
 * Create Transaction - Generate QRIS with unique transaction ID
 * Like friend's /createtransaksi endpoint
 */
async function createTransaction(data, corsHeaders) {
    const { amount, qr_string, gateway = 'orderkuota', fee_percent = 0.7 } = data;
    
    if (!amount || !qr_string) {
        return jsonResponse({
            success: false,
            error: 'amount dan qr_string wajib diisi'
        }, 400, corsHeaders);
    }
    
    try {
        const baseAmount = parseInt(amount, 10);
        
        // Calculate fee (0.7%)
        const fee = Math.ceil(baseAmount * (fee_percent / 100));
        const totalAmount = baseAmount + fee;
        
        // Generate unique transaction ID
        const transactionId = generateTransactionId(gateway.toUpperCase());
        
        // Generate dynamic QRIS with total amount
        const dynamicQris = generateDynamicQris(qr_string, totalAmount);
        
        if (!dynamicQris.success) {
            return jsonResponse({
                success: false,
                error: dynamicQris.error || 'Failed to generate dynamic QRIS'
            }, 400, corsHeaders);
        }
        
        // Generate QR image URL (using qrserver API)
        const imageUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(dynamicQris.qrString)}`;
        
        return jsonResponse({
            success: true,
            data: {
                transaction_id: transactionId,
                amount: baseAmount,
                fee: fee,
                total_amount: totalAmount,
                qr_string: dynamicQris.qrString,
                image_url: imageUrl,
                status: 'unpaid',
                type: `${gateway.toUpperCase()}_QRIS`,
                gateway: gateway,
                created_at: new Date().toISOString(),
                expires_at: new Date(Date.now() + 5 * 60 * 1000).toISOString() // 5 minutes
            }
        }, 200, corsHeaders);
        
    } catch (err) {
        return jsonResponse({
            success: false,
            error: err.message
        }, 500, corsHeaders);
    }
}

/**
 * Generate unique transaction ID
 */
function generateTransactionId(prefix = 'TRX') {
    const now = new Date();
    const dateStr = now.toISOString().slice(0, 10).replace(/-/g, '');
    const timeStr = now.toISOString().slice(11, 19).replace(/:/g, '');
    const random = Math.random().toString(36).substring(2, 8).toUpperCase();
    return `${prefix}-${dateStr}${timeStr}-${random}`;
}

/**
 * Generate Dynamic QRIS from Static QRIS
 * Converts static QRIS (010211) to dynamic QRIS (010212) with amount
 */
function generateDynamicQris(staticQris, amount) {
    try {
        if (!staticQris || !amount) {
            return { success: false, error: 'QRIS string and amount required' };
        }
        
        // Remove old CRC (last 4 chars) and CRC tag (6304)
        let qrBase = staticQris.substring(0, staticQris.length - 8);
        
        // Change from static (010211) to dynamic (010212)
        qrBase = qrBase.replace('010211', '010212');
        
        // Build amount tag: 54 [length] [amount]
        const amountStr = amount.toString();
        const amountLength = amountStr.length.toString().padStart(2, '0');
        const amountTag = '54' + amountLength + amountStr;
        
        // Find position to insert amount (before 5802ID)
        const countryCodePos = qrBase.indexOf('5802ID');
        
        if (countryCodePos === -1) {
            return { success: false, error: 'Invalid QRIS: Country code 5802ID not found' };
        }
        
        // Insert amount tag before country code
        const beforeCountry = qrBase.substring(0, countryCodePos);
        const afterCountry = qrBase.substring(countryCodePos);
        const qrWithAmount = beforeCountry + amountTag + afterCountry;
        
        // Add CRC tag and calculate CRC16
        const qrWithCRCTag = qrWithAmount + '6304';
        const crc = calculateCRC16(qrWithCRCTag);
        
        // Final QRIS string
        const finalQris = qrWithCRCTag + crc;
        
        return {
            success: true,
            qrString: finalQris,
            amount: amount
        };
    } catch (err) {
        return { success: false, error: err.message };
    }
}

/**
 * Calculate CRC16-CCITT for QRIS
 */
function calculateCRC16(str) {
    let crc = 0xFFFF;
    
    for (let c = 0; c < str.length; c++) {
        crc ^= str.charCodeAt(c) << 8;
        
        for (let i = 0; i < 8; i++) {
            if ((crc & 0x8000) !== 0) {
                crc = (crc << 1) ^ 0x1021;
            } else {
                crc = crc << 1;
            }
            crc &= 0xFFFF;
        }
    }
    
    return crc.toString(16).toUpperCase().padStart(4, '0');
}

function jsonResponse(data, status = 200, corsHeaders = {}) {
    return new Response(JSON.stringify(data, null, 2), {
        status,
        headers: {
            'Content-Type': 'application/json',
            ...corsHeaders,
        },
    });
}
