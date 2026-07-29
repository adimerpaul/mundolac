import{t as e}from"./printd-D4efK-oj.js";var t=e(),n=`
@page{size:80mm auto;margin:4mm}body{margin:0}.ticket{font-family:Arial,sans-serif;font-size:11px;color:#111}
.center{text-align:center}.logo{display:block;width:75px;max-height:65px;object-fit:contain;margin:0 auto 4px}
h2{margin:0;font-size:18px}.line{border-top:1px dashed #333;margin:7px 0}table{width:100%;border-collapse:collapse}
th,td{padding:2px}.right{text-align:right}.total{font-size:15px;font-weight:bold}.cancelled{font-size:26px;color:#c62828;text-align:center;font-weight:bold}
`,r=e=>String(e??``).replaceAll(`&`,`&amp;`).replaceAll(`<`,`&lt;`).replaceAll(`>`,`&gt;`),i=e=>Number(e||0).toFixed(2);function a(e,a={}){let o=(e.detalles||[]).map(e=>`<tr><td>${r(e.nombre)}<br><small>${r(e.cantidad)} ${r(e.unidad)} × ${i(e.precio_unitario)}${e.lote?` · Lote ${r(e.lote)}`:``}</small></td><td class="right">${i(e.total)}</td></tr>`).join(``),s=document.createElement(`div`);s.innerHTML=`<div class="ticket"><div class="center">
    ${a.logo_url?`<img class="logo" src="${r(a.logo_url)}" alt="Logotipo">`:``}
    <h2>${r(a.nombre_empresa||`Mundolac`)}</h2>
    ${a.nit?`<div>NIT: ${r(a.nit)}</div>`:``}
    ${a.direccion?`<div>${r(a.direccion)}</div>`:``}
    ${a.telefono?`<div>Teléfono: ${r(a.telefono)}</div>`:``}
    <b>COMPROBANTE DE COMPRA</b></div><div class="line"></div>
    <b>${r(e.numero)}</b><br>
    Fecha: ${new Date(e.fecha).toLocaleString(`es-BO`)}<br>
    Proveedor: ${r(e.proveedor_nombre)}<br>
    Factura: ${r(e.numero_factura||`S/N`)}<br>
    Registrado por: ${r(e.usuario_nombre)}<br>
    Pago: ${r(e.tipo_pago)}
    <div class="line"></div><table><thead><tr><th>Producto</th><th class="right">Total</th></tr></thead><tbody>${o}</tbody></table>
    <div class="line"></div><table>
      <tr><td>Efectivo</td><td class="right">Bs ${i(e.monto_efectivo)}</td></tr>
      <tr><td>QR</td><td class="right">Bs ${i(e.monto_qr)}</td></tr>
      <tr class="total"><td>TOTAL</td><td class="right">Bs ${i(e.total)}</td></tr>
    </table>
    ${e.comentario?`<div class="line"></div>Nota: ${r(e.comentario)}`:``}
    ${e.estado===`ANULADA`?`<div class="cancelled">ANULADA</div>`:``}
  </div>`,new t.Printd().print(s,[n])}export{a as t};