import { Printd } from 'printd'

const css = `
@page{size:80mm auto;margin:4mm}body{margin:0}.voucher{font-family:Arial,sans-serif;font-size:11px;color:#111;page-break-after:always}.voucher:last-child{page-break-after:auto}
.center{text-align:center}.logo{display:block;width:72px;max-height:60px;object-fit:contain;margin:0 auto 3px}h2{margin:0;font-size:17px}.title{font-weight:bold;margin-top:3px}
.line{border-top:1px dashed #333;margin:7px 0}table{width:100%;border-collapse:collapse}th,td{padding:2px}.right{text-align:right}.total{font-size:15px;font-weight:bold}
.status{display:inline-block;padding:3px 7px;border:1px solid #333;border-radius:9px;font-weight:bold}.small{font-size:9px;color:#444}
`
const esc = value => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
const money = value => Number(value || 0).toFixed(2)
const date = value => {
  if (!value) return 'Sin fecha'
  const raw = String(value).substring(0, 10)
  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
    const [year, month, day] = raw.split('-')
    return `${day}/${month}/${year}`
  }
  return new Date(value).toLocaleDateString('es-BO')
}
const voucher = (order, company) => {
  const rows = (order.detalles || []).map(item => `<tr><td>${esc(item.nombre)}<br><span class="small">${esc(item.cantidad)} ${esc(item.unidad)} × Bs ${money(item.precio_unitario)}</span></td><td class="right">${money(item.total)}</td></tr>`).join('')
  return `<section class="voucher"><div class="center">
    ${company.logo_url ? `<img class="logo" src="${esc(company.logo_url)}">` : ''}
    <h2>${esc(company.nombre_empresa || 'Mundolac')}</h2>
    ${company.nit ? `<div>NIT: ${esc(company.nit)}</div>` : ''}
    ${company.direccion ? `<div>${esc(company.direccion)}</div>` : ''}
    ${company.telefono ? `<div>Tel: ${esc(company.telefono)}</div>` : ''}
    <div class="title">VOUCHER DE PEDIDO</div></div><div class="line"></div>
    <b>${esc(order.numero)}</b><br>Registrado: ${new Date(order.fecha).toLocaleString('es-BO')}<br>
    Cliente: <b>${esc(order.cliente_nombre)}</b><br>Entrega: ${date(order.fecha_entrega)}<br>
    Dirección: ${esc(order.direccion_entrega || 'Sin dirección')}<br>Pago: ${esc(order.tipo_pago)}<br>
    <div class="center" style="margin-top:5px"><span class="status">${esc(order.estado)}</span></div>
    <div class="line"></div><table><thead><tr><th>Producto</th><th class="right">Total</th></tr></thead><tbody>${rows}</tbody></table>
    <div class="line"></div><table><tr class="total"><td>TOTAL Bs</td><td class="right">${money(order.total)}</td></tr></table>
    ${order.observacion ? `<div class="line"></div><b>Nota:</b> ${esc(order.observacion)}` : ''}
    ${order.latitud_entrega !== null && order.longitud_entrega !== null ? `<div class="line"></div><div class="small center">Ubicación: ${esc(order.latitud_entrega)}, ${esc(order.longitud_entrega)}</div>` : ''}
    <div class="line"></div><div class="center small">Preparado por ${esc(order.usuario_nombre)}</div>
  </section>`
}

export function printOrder (order, company = {}) {
  printOrders([order], company)
}

export function printOrders (orders, company = {}) {
  const element = document.createElement('div')
  element.innerHTML = orders.map(order => voucher(order, company)).join('')
  new Printd().print(element, [css])
}
