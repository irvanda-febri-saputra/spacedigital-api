# SpaceDigital Bot API Documentation

Base URL: `https://your-domain.com/api`

## Authentication

All API endpoints require authentication using the `X-API-Key` header.
Use your bot's API Key (set in Dashboard > Bots > Edit Bot > API Key).

```
X-API-Key: your_api_key_here
```

---

## Endpoints

### 1. Get Bot Settings

Fetch bot configuration and payment gateway settings.

**Request:**
```
GET /api/bot/settings
```

**Response:**
```json
{
    "success": true,
    "data": {
        "bot_id": 1,
        "name": "My Store Bot",
        "bot_username": "mystore_bot",
        "payment_gateway": "qiospay",
        "pg_merchant_code": "MERCHANT123",
        "pg_api_key": "api_key_here",
        "pg_qr_string": "00020101021226...",
        "status": "active",
        "settings": null
    }
}
```

---

### 2. Create Transaction

Create a new pending transaction.

**Request:**
```
POST /api/bot/transactions
Content-Type: application/json
```

**Body:**
```json
{
    "telegram_user_id": "123456789",
    "telegram_username": "john_doe",
    "product_name": "Netflix Premium",
    "variant": "1 Month",
    "quantity": 1,
    "price": 55000,
    "total_price": 55000,
    "payment_ref": null,
    "expired_at": "2024-12-05T14:00:00Z"
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "order_id": "ORD-ABCD1234XY",
        "total_price": 55000,
        "status": "pending",
        "expired_at": "2024-12-05T14:00:00.000000Z"
    }
}
```

---

### 3. Get Transaction

Get transaction details by order ID.

**Request:**
```
GET /api/bot/transactions/{order_id}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 1,
        "order_id": "ORD-ABCD1234XY",
        "telegram_user_id": "123456789",
        "telegram_username": "john_doe",
        "product_name": "Netflix Premium",
        "variant": "1 Month",
        "quantity": 1,
        "price": 55000,
        "total_price": 55000,
        "status": "pending",
        "paid_at": null,
        "created_at": "2024-12-05T13:30:00.000000Z"
    }
}
```

---

### 4. Update Transaction Status

Update transaction status (for payment webhook callback).

**Request:**
```
POST /api/bot/transactions/{order_id}/status
Content-Type: application/json
```

**Body:**
```json
{
    "status": "success",
    "payment_ref": "QIOS12345"
}
```

**Status values:** `pending`, `success`, `expired`, `failed`

**Response:**
```json
{
    "success": true,
    "data": {
        "order_id": "ORD-ABCD1234XY",
        "status": "success",
        "paid_at": "2024-12-05T13:45:00.000000Z"
    }
}
```

---

## Error Responses

**401 Unauthorized:**
```json
{
    "success": false,
    "error": "Invalid API key"
}
```

**403 Forbidden:**
```json
{
    "success": false,
    "error": "Bot is not active"
}
```

**404 Not Found:**
```json
{
    "success": false,
    "error": "Transaction not found"
}
```

---

## Node.js Example

```javascript
const axios = require('axios');

const API_BASE = 'https://your-domain.com/api';
const API_KEY = 'your_api_key_here';

// Get bot settings
async function getBotSettings() {
    const response = await axios.get(`${API_BASE}/bot/settings`, {
        headers: { 'X-API-Key': API_KEY }
    });
    return response.data;
}

// Create transaction
async function createTransaction(data) {
    const response = await axios.post(`${API_BASE}/bot/transactions`, data, {
        headers: { 
            'X-API-Key': API_KEY,
            'Content-Type': 'application/json'
        }
    });
    return response.data;
}

// Update transaction status
async function updateTransactionStatus(orderId, status, paymentRef = null) {
    const response = await axios.post(
        `${API_BASE}/bot/transactions/${orderId}/status`,
        { status, payment_ref: paymentRef },
        { headers: { 'X-API-Key': API_KEY } }
    );
    return response.data;
}
```

---

## Rate Limiting

API requests are limited to **60 requests per minute** per API key.
