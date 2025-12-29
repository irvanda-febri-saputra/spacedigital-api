// Cloudflare Worker: Atlantic API Proxy

export default {
    async fetch(request) {
        if (request.method !== 'POST') {
            return new Response(JSON.stringify({ error: 'Method not allowed' }), {
                status: 405, headers: { 'Content-Type': 'application/json' }
            })
        }

        try {
            const formData = await request.formData()
            const endpoint = formData.get('endpoint')

            if (!endpoint) {
                return new Response(JSON.stringify({ error: 'Missing endpoint' }), {
                    status: 400, headers: { 'Content-Type': 'application/json' }
                })
            }

            const newBody = new URLSearchParams()
            for (const [key, value] of formData.entries()) {
                if (key !== 'endpoint') newBody.append(key, value)
            }

            const response = await fetch('https://atlantich2h.com' + endpoint, {
                method: 'POST',
                body: newBody,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            })

            const text = await response.text()

            return new Response(text, {
                status: response.status,
                headers: {
                    'Content-Type': 'application/json',
                    'Access-Control-Allow-Origin': '*'
                }
            })
        } catch (e) {
            return new Response(JSON.stringify({ error: e.message }), {
                status: 500, headers: { 'Content-Type': 'application/json' }
            })
        }
    }
}
