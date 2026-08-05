import{t as e}from"./printd-D4efK-oj.js";var t=e(),n=`
@page{size:80mm auto;margin:4mm}body{margin:0}.ticket{font-family:Arial,sans-serif;font-size:11px;color:#111}
h2{text-align:center;margin:0}.center{text-align:center}.line{border-top:1px dashed #333;margin:7px 0}
.logo{display:block;width:70px;max-height:70px;object-fit:contain;margin:0 auto 4px}
table{width:100%;border-collapse:collapse}th,td{padding:2px}.right{text-align:right}.bold{font-weight:bold}
.total{font-size:15px}.cancelled{font-size:28px;color:#c62828;text-align:center;font-weight:bold}
`,r=e=>String(e??``).replaceAll(`&`,`&amp;`).replaceAll(`<`,`&lt;`).replaceAll(`>`,`&gt;`),i=e=>Number(e||0).toFixed(2);function a(e,a={},o=``){let s=a.nombre_empresa||`Mundolac`,c=a.logo?`${o}/images/${a.logo}`:``,l=c?`<img class="logo" src="${r(c)}" alt="${r(s)}">`:``,u=(e.detalles||[]).map(e=>`<tr><td>${r(e.nombre)}<br><small>${e.cantidad} × ${i(e.precio_venta)}</small></td><td class="right">${i(e.total)}</td></tr>`).join(``),d=document.createElement(`div`);d.innerHTML=`<div class="ticket">${l}<h2>${r(s).toUpperCase()}</h2><div class="center">COMPROBANTE DE VENTA</div><div class="line"></div>
  <div><b>${r(e.numero)}</b><br>Fecha: ${new Date(e.fecha).toLocaleString(`es-BO`)}<br>Cajero: ${r(e.usuario_nombre)}<br>Pago: ${r(e.tipo_pago)}</div>
  <div class="line"></div><table><thead><tr><th>Producto</th><th class="right">Total</th></tr></thead><tbody>${u}</tbody></table><div class="line"></div>
  <table><tr><td>Subtotal</td><td class="right">${i(e.subtotal)}</td></tr><tr><td>Descuento</td><td class="right">-${i(e.descuento)}</td></tr>
  <tr><td>Efectivo</td><td class="right">${i(e.monto_efectivo)}</td></tr><tr><td>QR</td><td class="right">${i(e.monto_qr)}</td></tr>
  <tr class="bold total"><td>TOTAL Bs</td><td class="right">${i(e.total)}</td></tr></table>
  ${e.estado===`ANULADA`?`<div class="cancelled">ANULADA</div>`:``}<div class="line"></div><div class="center">¡Gracias por su compra!</div></div>`,new t.Printd().print(d,[n])}export{a as t};