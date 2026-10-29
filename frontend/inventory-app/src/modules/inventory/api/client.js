// Inventory API ekata call karana ekama thana.
// Dashboard / gateway hadapu passe base URL saha auth headers meke witharai wenas karanna.
const BASE = '/api/v1'
const TENANT = import.meta.env.VITE_TENANT_ID || 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa' // TEMP: token eken replace
const ROLE = import.meta.env.VITE_ROLE || 'OM' // TEMP: OM = owner, SC = stock clerk

export async function api(path, { method = 'GET', body, params } = {}) {
  const url = new URL(BASE + path, window.location.origin)
  if (params) {
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v)
    })
  }

  const res = await fetch(url, {
    method,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Tenant-Id': TENANT,
      'X-User-Role': ROLE,
    },
    body: body ? JSON.stringify(body) : undefined,
  })

  let data = null
  try {
    data = await res.json()
  } catch (e) {
    data = null
  }

  return { ok: res.ok, status: res.status, data }
}