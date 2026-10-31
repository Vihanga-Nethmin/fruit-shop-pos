import { api } from './client'

// FR-23: cashiers do not see cost. Role is temporary (VITE_ROLE) until the gateway token.
export const canSeeCost = (import.meta.env.VITE_ROLE || 'OM') === 'OM'

// Backend uses Asia/Colombo for dates
export const today = () => new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Colombo' })

// Plain list ({data: [...]}) ho paginated ({data: {data: [...]}}) dekama handle karanawa
export function unwrapList(r) {
  const d = r.data?.data
  if (Array.isArray(d)) return { items: d, page: 1, last: 1, total: d.length }
  return { items: d?.data || [], page: d?.current_page || 1, last: d?.last_page || 1, total: d?.total || 0 }
}

export async function loadLookups() {
  const [s, l, p] = await Promise.all([api('/suppliers'), api('/locations'), api('/products')])
  return {
    suppliers: unwrapList(s).items,
    locations: unwrapList(l).items,
    products: unwrapList(p).items, // first page (25) only
  }
}

export const errOf = (errs, field) => (errs && errs[field] ? errs[field][0] : '')

export const statusTone = (s) =>
  ({ DRAFT: 'orange', APPROVED: 'blue', POSTED: 'green', CLOSED: 'green', CANCELLED: 'gray' }[s] || 'gray')

export const prettyStatus = (s) =>
  String(s || '').toLowerCase().replace(/^\w/, (c) => c.toUpperCase())

export const money = (v) => 'LKR ' + Number(v ?? 0).toFixed(2)

export function apiMessage(r, fallback) {
  return r.data?.error?.message || r.data?.message || fallback + ' (' + r.status + ').'
}