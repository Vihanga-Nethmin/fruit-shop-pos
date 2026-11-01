import { api } from './client'
import { unwrapList } from './lookups'

const cap = (s) => String(s || '').toLowerCase().replace(/^\w/, (c) => c.toUpperCase())

// Stock thiyena batch + location lines (wastage / supplier return form walata)
export async function loadStockLines({ onlyReceipt = false } = {}) {
  let page = 1
  let last = 1
  const all = []
  do {
    const r = await api('/inventory/positions', { params: { page } })
    if (!r.ok) break
    const l = unwrapList(r)
    all.push(...l.items)
    last = l.last
    page += 1
  } while (page <= last && page <= 10)

  return all
    .filter((p) => Number(p.physical_qty) > 0 && (!onlyReceipt || p.batch?.source === 'RECEIPT'))
    .map((p) => {
      const unit = p.product?.base_unit === 'EACH' ? 'item' : 'kg'
      const sellBy = p.batch?.sell_by_date ? ' · sell-by ' + p.batch.sell_by_date : ''
      return {
        key: p.batch_id + '|' + p.location_id,
        batch_id: p.batch_id,
        location_id: p.location_id,
        unit,
        available: p.available_qty ?? p.physical_qty,
        label:
          [
            p.product?.name,
            p.location?.code,
            cap(p.batch?.source) + ' ' + String(p.batch_id).slice(0, 8),
            Number(p.physical_qty) + ' ' + unit,
          ].join(' · ') + sellBy,
      }
    })
}