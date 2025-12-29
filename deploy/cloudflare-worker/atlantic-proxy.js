// Cloudflare Worker: Atlantic API Proxy
// Deploy this to Cloudflare Workers

addEventListener('fetch', event => {
    event.respondWith(handleRequest(event.request))
})

async function handleRequest(request) {
    // Only allow POST requests
    if (request.method !== 'POST') {
        return new Response(JSON.stringify({ error: 'Method not allowed' }), {
            status: 405,
            headers: { 'Content-Type': 'application/json' }
        })
    }

    try {
        // Get the request body
        const formData = await request.formData()

        // Get endpoint from the 'endpoint' field
        const endpoint = formData.get('endpoint')
        if (!endpoint) {
            return new Response(JSON.stringify({ error: 'Missing endpoint parameter' }), {
                status: 400,
                headers: { 'Content-Type': 'application/json' }
            })
        }

        // Remove 'endpoint' from formData and build new body
        const newFormData = new FormData()
        for (const [key, value] of formData.entries()) {
            if (key !== 'endpoint') {
                newFormData.append(key, value)
            }
        }

        // Forward request to Atlantic API
        const atlanticUrl = 'https://atlantich2h.com' + endpoint

        const response = await fetch(atlanticUrl, {
            method: 'POST',
            body: new URLSearchParams([...newFormData.entries()]),
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            }
        })

        const responseText = await response.text()

        // Check if response is HTML (Cloudflare challenge)
        if (responseText.includes('<!DOCTYPE html>') || responseText.includes('<html')) {
            return new Response(JSON.stringify({
                status: false,
                error: 'Atlantic API returned HTML response',
                message: 'Cloudflare challenge detected'
            }), {
                status: 502,
                headers: { 'Content-Type': 'application/json' }
            })
        }

        // Return JSON response
        return new Response(responseText, {
            status: response.status,
            headers: {
                'Content-Type': 'application/json',
                'Access-Control-Allow-Origin': '*'
            }
        })

    } catch (error) {
        return new Response(JSON.stringify({
            status: false,
            error: error.message
        }), {
            status: 500,
            headers: { 'Content-Type': 'application/json' }
        })
    }
}
