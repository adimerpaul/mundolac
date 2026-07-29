import { Printd } from 'printd'

const css = `
@page{size:80mm auto;margin:4mm}body{margin:0}.ticket{font-family:Arial,sans-serif;font-size:11px;color:#111}
.center{text-align:center}.logo{display:block;width:75px;max-height:65px;object-fit:contain;margin:0 auto 4px}
h2{margin:0;font-size:18px}.line{border-top:1px dashed #333;margin:7px 0}table{width:100%;border-collapse:collapse}
th,td{padding:2px}.right{text-align:right}.total{font-size:15px;font-weight:bold}.cancelled{font-size:26px;color:#c62828;text-align:center;font-weight:bold}
`
const esc = value => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
const money = value => Number(value || 0).toFixed(2)

export function printPurchase (purchase, company = {}) {
  const rows = (purchase.detalles || []).map(item => `<tr><td>${esc(item.nombre)}<br><small>${esc(item.cantidad)} ${esc(item.unidad)} × ${money(item.precio_unitario)}${item.lote ? ` · Lote ${esc(item.lote)}` : ''}</small></td><td class="right">${money(item.total)}</td></tr>`).join('')
  const element = document.createElement('div')
  element.innerHTML = `<div class="ticket"><div class="center">
    ${company.logo_url ? `<img class="logo" src="${esc(company.logo_url)}" alt="Logotipo">` : ''}
    <h2>${esc(company.nombre_empresa || 'Mundolac')}</h2>
    ${company.nit ? `<div>NIT: ${esc(company.nit)}</div>` : ''}
    ${company.direccion ? `<div>${esc(company.direccion)}</div>` : ''}
    ${company.telefono ? `<div>Teléfono: ${esc(company.telefono)}</div>` : ''}
    <b>COMPROBANTE DE COMPRA</b></div><div class="line"></div>
    <b>${esc(purchase.numero)}</b><br>
    Fecha: ${new Date(purchase.fecha).toLocaleString('es-BO')}<br>
    Proveedor: ${esc(purchase.proveedor_nombre)}<br>
    Factura: ${esc(purchase.numero_factura || 'S/N')}<br>
    Registrado por: ${esc(purchase.usuario_nombre)}<br>
    Pago: ${esc(purchase.tipo_pago)}
    <div class="line"></div><table><thead><tr><th>Producto</th><th class="right">Total</th></tr></thead><tbody>${rows}</tbody></table>
    <div class="line"></div><table>
      <tr><td>Efectivo</td><td class="right">Bs ${money(purchase.monto_efectivo)}</td></tr>
      <tr><td>QR</td><td class="right">Bs ${money(purchase.monto_qr)}</td></tr>
      <tr class="total"><td>TOTAL</td><td class="right">Bs ${money(purchase.total)}</td></tr>
    </table>
    ${purchase.comentario ? `<div class="line"></div>Nota: ${esc(purchase.comentario)}` : ''}
    ${purchase.estado === 'ANULADA' ? '<div class="cancelled">ANULADA</div>' : ''}
  </div>`
  new Printd().print(element, [css])
}
