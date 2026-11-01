// Inventory API ekata call karana ekama thana.
// Dashboard / gateway hadapu passe base URL saha auth headers meke witharai wenas karanna.
const BASE = '/api/v1'
const TENANT = import.meta.env.VITE_TENANT_ID || 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa' // TEMP: token eken replace
const ROLE = import.meta.env.VITE_ROLE || 'OM' // TEMP: OM = owner, SC = stock clerk

function buildUrl(path, params) {
  const url = new URL(BASE + path, window.location.origin)
  if (params) {
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v)
    })
  }
  return url
}

export async function api(path, { method = 'GET', body, params } = {}) {
  const res = await fetch(buildUrl(path, params), {
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

// CSV (ho wena file) download karanna. Headers yawanna ona nisa plain link ekak wada karanne nae.
export async function download(path, params) {
  const res = await fetch(buildUrl(path, params), {
    headers: {
      Accept: 'text/csv, application/json',
      'X-Tenant-Id': TENANT,
      'X-User-Role': ROLE,
    },
  })

  if (!res.ok) {
    let data = null
    try {
      data = await res.json()
    } catch (e) {
      data = null
    }
    return { ok: false, status: res.status, data }
  }

  const blob = await res.blob()
  const match = /filename="?([^";]+)"?/i.exec(res.headers.get('Content-Disposition') || '')
  return { ok: true, status: res.status, blob, filename: match ? match[1] : null }
}