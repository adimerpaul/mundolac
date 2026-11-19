<template>
  <q-page class="q-pa-sm" :class="{'page-with-bar':$q.screen.lt.md}">
    <div class="row items-center q-mb-sm">
      <div><div class="text-subtitle1 text-weight-bold">Nueva venta</div><div class="text-caption text-grey-7">Selecciona productos y confirma el carrito</div></div>
      <q-space/><q-btn dense flat icon="receipt_long" label="Ver ventas" no-caps to="/ventas"/>
    </div>
    <div class="row q-col-gutter-sm">
      <div class="col-12 col-md-6">
        <q-card flat bordered>
          <q-card-section class="row q-col-gutter-xs items-center q-pa-sm">
            <div class="col-12 col-sm row no-wrap items-center q-gutter-x-xs">
              <q-input ref="searchInput" v-model="search" dense outlined :autofocus="$q.screen.gt.sm" clearable debounce="250" class="col" :placeholder="$q.screen.lt.sm?'Buscar producto o código':'Buscar nombre, código o escanear código de barras'" @update:model-value="reloadProducts" @keydown.enter.prevent="onSearchEnter" @keydown.tab="onSearchTab">
                <template #prepend><q-icon name="search"/></template>
              </q-input>
              <q-btn unelevated color="primary" icon="qr_code_scanner" :label="$q.screen.gt.xs?'Escanear':undefined" no-caps class="scan-btn" @click="scannerOpen=true"><q-tooltip>Escanear QR / código de barras con la cámara</q-tooltip></q-btn>
            </div>
            <div class="col-12 col-sm-auto row no-wrap items-center q-gutter-x-xs">
              <q-select v-model="category" :options="categories" option-label="nombre" dense outlined clearable label="Categoría" class="col" style="min-width:150px" @update:model-value="reloadProducts"/>
              <q-btn dense unelevated no-caps color="primary" icon="sync" :label="$q.screen.gt.xs?'Actualizar precio':undefined" class="scan-btn" :loading="loadingProducts" @click="reloadProducts"/>
            </div>
          </q-card-section>
          <q-separator/>
          <q-card-section class="q-pa-sm product-grid">
            <q-card v-for="product in products" :key="product.id" flat bordered class="product-card cursor-pointer" :class="{'product-card--empty':product.stock_inicial<=0}" @click="add(product)">
              <div class="product-image"><img v-if="product.foto" :src="photoUrl(product.foto)"/><q-icon v-else name="inventory_2" size="42px" color="grey-4"/></div>
              <q-card-section class="q-pa-xs">
                <div class="text-caption text-weight-bold ellipsis-2-lines product-name">{{product.nombre}}</div>
                <div class="row items-center"><span class="text-primary text-weight-bold">Bs {{money(product.precio_venta)}}</span><q-space/><q-badge :color="product.stock_inicial>0?'positive':'negative'" :label="`Stock ${product.stock_inicial}`"/></div>
              </q-card-section>
            </q-card>
          </q-card-section>
          <q-separator/>
          <q-card-section class="row justify-center q-pa-xs">
            <q-pagination v-model="page" :max="lastPage" :max-pages="6" direction-links boundary-links size="sm" @update:model-value="loadProducts"/>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-md-6">
        <q-card ref="cartCard" flat bordered class="cart-card">
          <q-card-section class="row items-center q-py-sm"><q-icon name="shopping_cart" color="primary" size="22px" class="q-mr-xs"/><div class="text-subtitle1 text-weight-bold">Carrito</div><q-space/><q-badge color="primary" :label="itemCount"/></q-card-section>
          <q-separator/>
          <q-list v-if="cart.length" separator class="cart-list">
            <q-item v-for="item in cart" :key="item.id" dense class="q-px-sm">
              <q-item-section avatar><q-avatar rounded size="34px" color="grey-2"><img v-if="item.foto" :src="photoUrl(item.foto)"/><q-icon v-else name="inventory_2"/></q-avatar></q-item-section>
              <q-item-section>
                <q-item-label lines="1" class="text-caption text-weight-medium">{{item.nombre}}</q-item-label>
                <div class="line-editor">
                  <label class="price-label">Cantidad <input v-model.number="item.cantidad" class="qty-input" type="number" min="1" :max="item.stock_inicial" step="1" @blur="validateQty(item)"></label>
                  <label class="price-label">Precio Bs <input v-model.number="item.precio_venta" class="price-input" type="number" min="0" step="0.0001" @keyup.enter="syncLineTotal(item)" @blur="syncLineTotal(item)"></label>
                  <label v-if="canProfit" class="price-label">P. compra Bs <input v-model.number="item.precio_compra" class="price-input cost-input" type="number" min="0" step="0.01" @blur="item.precio_compra=Math.max(0,Number(item.precio_compra)||0)"></label>
                  <label class="total-label">Total Bs <input v-model.number="item.total_editable" class="total-input" type="number" min="0" step="0.01" @keyup.enter="$event.target.blur()" @blur="applyLineTotal(item)"></label>
                </div>
                <div v-if="canProfit" class="text-caption" :class="lineProfit(item)>=0?'text-positive':'text-negative'">Ganancia: Bs {{money(lineProfit(item))}}</div>
              </q-item-section>
              <q-item-section side><div class="row items-center no-wrap"><q-btn dense flat round size="sm" icon="remove" @click="changeQty(item,-1)"/><q-btn dense flat round size="sm" icon="add" @click="changeQty(item,1)"/><q-btn dense flat round size="sm" icon="delete" color="negative" @click="removeItem(item)"/></div></q-item-section>
            </q-item>
          </q-list>
          <q-card-section v-else class="text-center text-grey-6 q-py-xl"><q-icon name="remove_shopping_cart" size="42px"/><div>Agrega productos</div></q-card-section>
          <q-separator/>
          <q-card-section class="q-pa-sm">
            <q-input v-model.number="discount" dense outlined type="number" min="0" :max="subtotal" step="0.01" label="Descuento" prefix="Bs" class="q-mb-sm"/>
            <q-select v-model="paymentType" dense outlined :options="paymentTypes" label="Tipo de pago" class="q-mb-sm"/>
            <div v-if="paymentType==='COMBINADO'" class="row q-col-gutter-sm q-mb-sm">
              <q-input v-model.number="cashAmount" dense outlined type="number" min="0" step="0.01" label="Monto efectivo" prefix="Bs" class="col-6"/>
              <q-input v-model.number="qrAmount" dense outlined type="number" min="0" step="0.01" label="Monto QR" prefix="Bs" class="col-6"/>
              <div class="col-12 text-caption" :class="paymentDifference===0?'text-positive':'text-negative'">Diferencia: Bs {{money(paymentDifference)}}</div>
            </div>
            <q-banner v-else dense rounded :class="paymentType==='EFECTIVO'?'bg-green-1 text-green-9':'bg-blue-1 text-blue-9'" class="q-mb-sm"><q-icon :name="paymentType==='EFECTIVO'?'payments':'qr_code_2'" class="q-mr-xs"/>Pago {{paymentType}}: Bs {{money(total)}}</q-banner>
            <q-input v-model="observation" dense outlined autogrow label="Observación" class="q-mb-sm"/>
            <div class="row text-body2"><span>Subtotal</span><q-space/><b>Bs {{money(subtotal)}}</b></div>
            <div class="row text-body2 text-negative"><span>Descuento</span><q-space/><b>- Bs {{money(validDiscount)}}</b></div>
            <div v-if="canProfit" class="row text-body2" :class="profit>=0?'text-positive':'text-negative'"><span>Ganancia</span><q-space/><b>Bs {{money(profit)}}</b></div>
            <div class="row text-h6 text-primary q-mt-xs"><b>Total</b><q-space/><b>Bs {{money(total)}}</b></div>
          </q-card-section>
          <q-card-actions class="q-pa-sm"><q-btn class="full-width" color="positive" unelevated icon="point_of_sale" label="Confirmar venta" no-caps :disable="!cart.length" :loading="saving" @click="confirmSale"/></q-card-actions>
        </q-card>
      </div>
    </div>

    <ScannerDialog v-model="scannerOpen" title="Escanear para vender" @detected="onScan" @hide="focusSearch">
      <div class="q-pa-sm">
        <q-banner v-if="scanStatus" dense rounded :class="scanStatus.ok?'bg-green-1 text-green-9':'bg-red-1 text-red-9'" class="q-mb-sm">
          <template #avatar><q-icon :name="scanStatus.ok?'check_circle':'error'" :color="scanStatus.ok?'positive':'negative'"/></template>
          <div class="text-weight-bold ellipsis">{{scanStatus.title}}</div><div class="text-caption">{{scanStatus.detail}}</div>
        </q-banner>
        <div v-else class="text-center text-grey-7 text-caption q-mb-sm">Apunta al QR o código de barras del producto. Se agrega solo al carrito.</div>
        <div class="row items-center">
          <div><div class="text-caption text-grey-7">Carrito · {{itemCount}} ítems</div><div class="text-h6 text-primary text-weight-bold">Bs {{money(total)}}</div></div>
          <q-space/>
          <q-btn unelevated no-caps color="positive" icon="shopping_cart_checkout" label="Listo" size="lg" @click="scannerOpen=false;scrollToCart()"/>
        </div>
      </div>
    </ScannerDialog>

    <q-page-sticky v-if="$q.screen.lt.md" expand position="bottom" class="mobile-bar">
      <div class="row no-wrap items-center full-width q-pa-sm q-gutter-x-sm bg-white">
        <q-btn unelevated round color="primary" icon="qr_code_scanner" size="lg" @click="scannerOpen=true"/>
        <q-btn unelevated no-caps color="positive" class="col" size="lg" :disable="!cart.length" @click="scrollToCart">
          <q-icon name="shopping_cart" class="q-mr-sm"/><span>{{itemCount}}</span><q-space/><b>Bs {{money(total)}}</b>
        </q-btn>
      </div>
    </q-page-sticky>

    <q-dialog v-model="qtyDialog" @hide="focusSearch">
      <q-card style="min-width:300px">
        <q-card-section class="q-pb-none">
          <div class="text-subtitle1 text-weight-bold">{{qtyProduct?.nombre}}</div>
          <div class="text-caption text-grey-7">Bs {{money(qtyProduct?.precio_venta)}} · Stock {{qtyProduct?.stock_inicial}}<span v-if="qtyInCart"> · En carrito {{qtyInCart}}</span></div>
        </q-card-section>
        <q-card-section>
          <q-input v-model.number="qtyValue" dense outlined autofocus type="number" min="1" step="1" label="Cantidad" input-class="text-h6" @focus="$event.target.select()" @keyup.enter="confirmQty" hint="Enter para agregar · Esc para cancelar"/>
        </q-card-section>
        <q-card-actions align="right"><q-btn flat no-caps label="Cancelar" v-close-popup/><q-btn unelevated no-caps color="primary" label="Agregar" @click="confirmQty"/></q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, nextTick, onMounted, ref, watch } from 'vue'
import { printSale } from '../../addons/ventaPrint'
import ScannerDialog from '../../components/ScannerDialog.vue'
const {proxy}=getCurrentInstance()
const products=ref([]),categories=ref([]),cart=ref([]),search=ref(''),category=ref(null),discount=ref(0),observation=ref(''),saving=ref(false),searchInput=ref(null)
const scannerOpen=ref(false),scanStatus=ref(null),cartCard=ref(null)
const qtyDialog=ref(false),qtyProduct=ref(null),qtyValue=ref(1)
const qtyInCart=computed(()=>cart.value.find(i=>i.id===qtyProduct.value?.id)?.cantidad||0)
const page=ref(1),lastPage=ref(1),loadingProducts=ref(false)
const paymentType=ref('EFECTIVO'),paymentTypes=['EFECTIVO','QR','COMBINADO'],cashAmount=ref(0),qrAmount=ref(0)
const photoUrl=path=>`${proxy.$imgBase}/images/${path}`,money=v=>Number(v||0).toFixed(2)
const subtotal=computed(()=>cart.value.reduce((sum,i)=>sum+Number(i.precio_venta)*i.cantidad,0))
const validDiscount=computed(()=>Math.min(Math.max(Number(discount.value)||0,0),subtotal.value))
const total=computed(()=>subtotal.value-validDiscount.value)
const canProfit=computed(()=>proxy.$store.hasPermission('Ver Ganancias'))
const lineProfit=item=>(Number(item.precio_venta||0)-Number(item.precio_compra||0))*Number(item.cantidad||0)
const profit=computed(()=>cart.value.reduce((sum,i)=>sum+lineProfit(i),0)-validDiscount.value)
const itemCount=computed(()=>cart.value.reduce((sum,i)=>sum+i.cantidad,0))
const paymentDifference=computed(()=>Number((total.value-(Number(cashAmount.value)||0)-(Number(qrAmount.value)||0)).toFixed(2)))
watch([paymentType,total],()=>{if(paymentType.value==='EFECTIVO'){cashAmount.value=total.value;qrAmount.value=0}else if(paymentType.value==='QR'){cashAmount.value=0;qrAmount.value=total.value}else if(Number(cashAmount.value)+Number(qrAmount.value)===0){cashAmount.value=total.value;qrAmount.value=0}})
function loadProducts(){loadingProducts.value=true;proxy.$axios.get('/productos',{params:{q:search.value,categoria_id:category.value?.id,per_page:15,page:page.value}}).then(({data})=>{products.value=data.data;lastPage.value=data.last_page||1;if(page.value>lastPage.value){page.value=lastPage.value;loadProducts()}}).finally(()=>{loadingProducts.value=false})}
function reloadProducts(){page.value=1;loadProducts()}
function add(product,silent=false){const fail=msg=>{if(!silent)proxy.$alert.error(msg);return msg};if(product.stock_inicial<=0)return fail('Producto sin stock');const item=cart.value.find(i=>i.id===product.id);if(item){if(item.cantidad>=product.stock_inicial)return fail('No hay más stock');item.cantidad++;syncLineTotal(item)}else cart.value.push({...product,cantidad:1,total_editable:Number(product.precio_venta).toFixed(2)});return null}
function onSearchEnter(e){if(saving.value||qtyDialog.value)return;const code=(e.target.value||'').trim();if(code)addScanned(code);else if(cart.value.length)confirmSale()}
function onSearchTab(e){const code=(e.target.value||'').trim();if(!code||saving.value||qtyDialog.value)return;e.preventDefault();addScanned(code)}
async function findByCode(code){const q=code.toUpperCase(),match=p=>p.codigo?.toUpperCase()===q||p.codigo_barras?.toUpperCase()===q;let product=products.value.find(match);if(!product){const {data}=await proxy.$axios.get('/productos',{params:{q:code,per_page:15}});product=data.data.find(match)}return product||null}
async function addScanned(code){search.value='';if(searchInput.value?.nativeEl)searchInput.value.nativeEl.value='';let product=null;try{product=await findByCode(code)}catch{proxy.$alert.error(`Sin conexión, no se pudo buscar ${code}`);feedback(false);return focusSearch()}if(!product){search.value=code;reloadProducts();proxy.$alert.warning(`Sin código exacto: ${code}`);feedback(false);return focusSearch(true)}reloadProducts();const err=add(product,true);if(err){proxy.$alert.error(`${product.nombre}: ${err}`);feedback(false)}else{const qty=cart.value.find(i=>i.id===product.id)?.cantidad||1;proxy.$alert.success(`${product.nombre} · En carrito: ${qty}`);feedback(true)}focusSearch()}
function confirmQty(){const product=qtyProduct.value,amount=Math.floor(Number(qtyValue.value)||0);if(!product)return;if(amount<1)return proxy.$alert.error('Cantidad inválida');const item=cart.value.find(i=>i.id===product.id),next=(item?.cantidad||0)+amount;if(next>product.stock_inicial)return proxy.$alert.error(`Stock insuficiente (disponible ${product.stock_inicial-(item?.cantidad||0)})`);if(item){item.cantidad=next;syncLineTotal(item)}else cart.value.push({...product,cantidad:amount,total_editable:(Number(product.precio_venta)*amount).toFixed(2)});qtyDialog.value=false}
function focusSearch(select=false){if(proxy.$q.screen.lt.md||scannerOpen.value)return;nextTick(()=>setTimeout(()=>{searchInput.value?.focus();if(select===true)searchInput.value?.select()},50))}
function scrollToCart(){nextTick(()=>cartCard.value?.$el?.scrollIntoView({behavior:'smooth',block:'start'}))}
let audioCtx=null
function feedback(ok){try{navigator.vibrate?.(ok?80:[60,60,60])}catch{/* sin vibración */}try{audioCtx=audioCtx||new (window.AudioContext||window.webkitAudioContext)();const o=audioCtx.createOscillator(),g=audioCtx.createGain();o.frequency.value=ok?1400:300;g.gain.value=0.15;o.connect(g);g.connect(audioCtx.destination);o.start();o.stop(audioCtx.currentTime+(ok?0.12:0.3))}catch{/* sin audio */}}
async function onScan(code){let product=null;try{product=await findByCode(code)}catch{scanStatus.value={ok:false,title:'Sin conexión',detail:`No se pudo buscar ${code}`};return feedback(false)}if(!product){scanStatus.value={ok:false,title:'Producto no encontrado',detail:`Código: ${code}`};return feedback(false)}const err=add(product,true);if(err){scanStatus.value={ok:false,title:product.nombre,detail:err};return feedback(false)}const qty=cart.value.find(i=>i.id===product.id)?.cantidad||1;scanStatus.value={ok:true,title:product.nombre,detail:`Agregado · Bs ${money(product.precio_venta)} · En carrito: ${qty}`};feedback(true)}
function changeQty(item,amount){const next=item.cantidad+amount;if(next<=0)return removeItem(item);if(next>item.stock_inicial)return proxy.$alert.error('Stock insuficiente');item.cantidad=next;syncLineTotal(item)}
function validateQty(item){const value=Math.floor(Number(item.cantidad)||1);if(value>item.stock_inicial){item.cantidad=item.stock_inicial;proxy.$alert.error('La cantidad fue ajustada al stock disponible')}else item.cantidad=Math.max(1,value);syncLineTotal(item)}
function syncLineTotal(item){item.total_editable=(Number(item.precio_venta||0)*Number(item.cantidad||0)).toFixed(2)}
function applyLineTotal(item){const totalValue=Math.max(0,Number(item.total_editable)||0),quantity=Math.max(1,Number(item.cantidad)||1);item.total_editable=totalValue.toFixed(2);item.precio_venta=Number((totalValue/quantity).toFixed(4))}
function removeItem(item){cart.value=cart.value.filter(i=>i.id!==item.id)}
function confirmSale(){cart.value.forEach(item=>{const requestedTotal=item.total_editable;validateQty(item);item.total_editable=requestedTotal;applyLineTotal(item)});if(cart.value.some(i=>Number(i.precio_venta)<0||i.precio_venta===''))return proxy.$alert.error('Revisa los precios de venta');if(paymentType.value==='COMBINADO'&&paymentDifference.value!==0)return proxy.$alert.error('Efectivo y QR deben sumar el total');proxy.$alert.dialog(`¿Confirmar venta por Bs ${money(total.value)}?`,'',{focusOk:true}).onOk(async()=>{saving.value=true;try{const {data}=await proxy.$axios.post('/ventas',{descuento:validDiscount.value,tipo_pago:paymentType.value,monto_efectivo:cashAmount.value,monto_qr:qrAmount.value,observacion:observation.value,detalles:cart.value.map(i=>({producto_id:i.id,cantidad:i.cantidad,precio_venta:i.precio_venta,...(canProfit.value?{precio_compra:Number(i.precio_compra)||0}:{})}))});proxy.$alert.success(`Venta ${data.numero} registrada`);printSale(data,proxy.$store.company,proxy.$imgBase);cart.value=[];discount.value=0;observation.value='';paymentType.value='EFECTIVO';loadProducts();focusSearch()}catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo registrar la venta')}finally{saving.value=false}}).onDismiss(focusSearch)}
watch(scannerOpen,v=>{if(v)scanStatus.value=null})
onMounted(()=>{loadProducts();proxy.$axios.get('/productos-catalogos').then(r=>categories.value=r.data.categorias)})
</script>

<style scoped>
.product-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:4px;min-height:250px}.product-card{transition:.15s;overflow:hidden}.product-card:hover{border-color:#1976d2;transform:translateY(-1px)}.product-card--empty{opacity:.55}.product-image{height:46px;background:#f5f7f6;display:flex;align-items:center;justify-content:center}.product-image img{width:100%;height:100%;object-fit:contain}.product-name{height:27px;font-size:9px;line-height:1.15}.product-card .text-primary{font-size:9px}.product-card .q-badge{font-size:7px;padding:2px}.refresh-price-btn{min-height:24px;padding:0 6px;font-size:9px}.cart-card{position:sticky;top:62px}.cart-list{max-height:38vh;overflow:auto}.line-editor{display:flex;align-items:flex-end;gap:6px;flex-wrap:nowrap;margin-top:3px}.price-label,.total-label{font-size:10px;color:#607d8b;display:flex;align-items:flex-start;flex-direction:column;gap:1px;white-space:nowrap}.price-input,.qty-input,.total-input{width:72px;height:23px;border:1px solid #cfd8dc;border-radius:4px;padding:1px 4px;font-size:12px;color:#263238;background:#fff}.qty-input{width:55px}.cost-input{color:#6d4c41}.total-input{width:76px;font-weight:700;color:#1565c0}.price-input:focus,.qty-input:focus,.total-input:focus{outline:1px solid #1976d2;border-color:#1976d2}@media(max-width:1023px){.cart-card{position:static}.product-grid{grid-template-columns:repeat(6,minmax(80px,1fr));min-height:0;overflow-x:auto}}.scan-btn{min-height:40px}.page-with-bar{padding-bottom:84px}.mobile-bar{z-index:10;box-shadow:0 -2px 8px rgba(0,0,0,.12)}@media(max-width:599px){.product-grid{grid-template-columns:repeat(3,minmax(0,1fr));overflow-x:visible;gap:6px}.product-image{height:64px}.product-name{height:30px;font-size:11px}.product-card .text-primary{font-size:11px}.product-card .q-badge{font-size:9px}.line-editor{flex-wrap:wrap}.price-input,.qty-input,.total-input{height:32px;font-size:16px;width:84px}.qty-input{width:64px}.cart-list{max-height:none}}
</style>
