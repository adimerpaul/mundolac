<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm"><div><div class="text-h6">Nueva compra</div><div class="text-caption text-grey-7">Registra el ingreso de mercadería por lote</div></div><q-space/><q-btn flat icon="shopping_bag" label="Ver compras" no-caps to="/compras"/></div>
    <div class="row q-col-gutter-sm">
      <div class="col-12 col-md-5">
        <q-card flat bordered>
          <q-card-section class="q-gutter-sm">
            <div class="text-subtitle2 text-weight-bold">Productos</div>
            <q-input v-model="buscar" outlined dense debounce="250" clearable label="Buscar producto" @update:model-value="loadProducts">
              <template #prepend><q-icon name="search"/></template>
            </q-input>
          </q-card-section>
          <div class="product-grid">
            <q-card v-for="p in productos" :key="p.id" flat bordered class="product-card cursor-pointer" @click="add(p)">
              <div class="product-image">
                <img v-if="p.foto" :src="photoUrl(p.foto)" :alt="p.nombre" @error="$event.target.style.display='none'"/>
                <q-icon v-else name="inventory_2" size="42px" color="grey-5"/>
              </div>
              <q-card-section class="q-pa-xs">
                <div class="text-caption text-weight-bold ellipsis-2-lines product-name">{{p.nombre}}</div>
                <div class="text-caption text-grey-7">{{p.codigo}}</div>
                <div class="row items-center"><span class="text-primary text-weight-bold">Bs {{money(p.precio_compra)}}</span><q-space/><q-badge color="positive">Stock {{p.stock_inicial}}</q-badge></div>
              </q-card-section>
            </q-card>
          </div>
        </q-card>
      </div>
      <div class="col-12 col-md-7"><q-card flat bordered>
        <q-card-section class="purchase-data q-pa-xs">
          <div class="text-caption text-weight-bold text-grey-8 q-px-xs">Datos de la compra</div>
          <div class="row q-col-gutter-xs">
            <q-select v-model="proveedor" :options="proveedores" option-label="nombre" outlined dense options-dense hide-bottom-space use-input input-debounce="200" label="Proveedor *" class="col-12 col-sm-8" @filter="filterProviders">
              <template #prepend><q-icon name="local_shipping" size="16px"/></template>
              <template #after><q-btn round dense flat size="sm" icon="person_add" to="/proveedores"/></template>
            </q-select>
            <q-input v-model="numeroFactura" outlined dense hide-bottom-space label="N.º factura" class="col-12 col-sm-4">
              <template #prepend><q-icon name="receipt_long" size="16px"/></template>
            </q-input>
          </div>
        </q-card-section>
        <q-separator/><q-card-section class="row items-center q-pa-sm"><div class="text-subtitle1 text-weight-bold">Detalle de compra</div><q-space/><q-badge color="primary">{{detalles.length}} productos</q-badge></q-card-section><q-separator/><div v-if="!detalles.length" class="q-pa-xl text-center text-grey-6"><q-icon name="inventory" size="48px"/><div>Agrega productos a la compra</div></div><q-list v-else separator><q-item v-for="d in detalles" :key="d.producto_id" class="column q-px-sm q-py-xs"><div class="row items-center full-width"><b>{{d.nombre}}</b><q-space/><q-btn round dense flat size="sm" color="negative" icon="delete" @click="remove(d)"/></div><div class="row q-col-gutter-xs full-width q-mt-xs"><q-input v-model.number="d.cantidad" dense outlined hide-bottom-space type="number" min=".001" step=".001" label="Cantidad" class="col-6 col-sm" @update:model-value="syncLineTotal(d)"/><q-input v-model.number="d.precio_unitario" dense outlined hide-bottom-space type="number" min="0" step=".0001" label="Costo unit." prefix="Bs" class="col-6 col-sm" @update:model-value="syncLineTotal(d)"/><q-input v-model.number="d.total_editable" dense outlined hide-bottom-space type="number" min="0" step=".01" label="Total" prefix="Bs" class="col-6 col-sm input-total" @blur="applyLineTotal(d)" @keyup.enter="$event.target.blur()"/><q-input v-model="d.lote" dense outlined hide-bottom-space label="Lote" class="col-6 col-sm"/><q-input v-model="d.fecha_vencimiento" dense outlined hide-bottom-space type="date" label="Vencimiento" class="col-12 col-sm"/></div></q-item></q-list><q-separator/><q-card-section class="q-gutter-sm"><q-select v-model="tipoPago" outlined dense :options="['EFECTIVO','QR','COMBINADO']" label="Tipo de pago"/><div v-if="tipoPago==='COMBINADO'" class="row q-col-gutter-sm"><q-input v-model.number="efectivo" outlined dense type="number" label="Efectivo" prefix="Bs" class="col-6"/><q-input v-model.number="qr" outlined dense type="number" label="QR" prefix="Bs" class="col-6"/></div><q-input v-model="comentario" outlined dense autogrow label="Comentario"/><div class="row text-h6 text-primary"><b>Total</b><q-space/><b>Bs {{money(total)}}</b></div><q-btn color="positive" class="full-width" icon="save" label="Registrar compra" no-caps :loading="saving" :disable="!detalles.length||!proveedor" @click="save"/></q-card-section></q-card></div>
    </div>
  </q-page>
</template>
<script setup>
import { computed, getCurrentInstance, reactive, ref, watch } from 'vue'
import { printPurchase } from '../../addons/compraPrint'
const {proxy}=getCurrentInstance(),proveedores=ref([]),allProviders=ref([]),proveedor=ref(null),productos=ref([]),detalles=ref([]),buscar=ref(''),numeroFactura=ref(''),tipoPago=ref('EFECTIVO'),efectivo=ref(0),qr=ref(0),comentario=ref(''),saving=ref(false)
const company=reactive({nombre_empresa:'Mundolac',nit:'',direccion:'',telefono:'',logo_url:''})
const total=computed(()=>detalles.value.reduce((s,d)=>s+(Number(d.cantidad)||0)*(Number(d.precio_unitario)||0),0)),money=v=>Number(v||0).toFixed(2)
const photoUrl=path=>`${proxy.$imgBase}/images/${path}`
watch([tipoPago,total],()=>{if(tipoPago.value==='EFECTIVO'){efectivo.value=total.value;qr.value=0}else if(tipoPago.value==='QR'){efectivo.value=0;qr.value=total.value}})
function loadProducts(){proxy.$axios.get('/productos',{params:{q:buscar.value,per_page:0}}).then(r=>productos.value=r.data.data)}
function loadProviders(){proxy.$axios.get('/proveedores').then(r=>{allProviders.value=r.data;proveedores.value=r.data})}
function filterProviders(val,update){update(()=>proveedores.value=allProviders.value.filter(p=>p.nombre.toLowerCase().includes(val.toLowerCase())))}
function add(p){if(detalles.value.some(d=>d.producto_id===p.id))return proxy.$alert.error('El producto ya está agregado');const price=Number(p.precio_compra)||0;detalles.value.push({producto_id:p.id,nombre:p.nombre,cantidad:1,precio_unitario:price,total_editable:Number(price.toFixed(2)),lote:'',fecha_vencimiento:''})}
function remove(d){detalles.value=detalles.value.filter(x=>x!==d)}
function syncLineTotal(d){d.total_editable=Number(((Number(d.cantidad)||0)*(Number(d.precio_unitario)||0)).toFixed(2))}
function applyLineTotal(d){const quantity=Number(d.cantidad)||0,totalValue=Math.max(0,Number(d.total_editable)||0);if(quantity<=0)return;d.total_editable=Number(totalValue.toFixed(2));d.precio_unitario=Number((totalValue/quantity).toFixed(4))}
function save(){if(detalles.value.some(d=>Number(d.cantidad)<=0||Number(d.precio_unitario)<0))return proxy.$alert.error('Revisa cantidades y costos');if(tipoPago.value==='COMBINADO'&&Math.abs(Number(efectivo.value)+Number(qr.value)-total.value)>.009)return proxy.$alert.error('Efectivo y QR deben sumar el total');proxy.$alert.dialog(`¿Registrar compra por Bs ${money(total.value)}?`).onOk(async()=>{saving.value=true;try{const {data}=await proxy.$axios.post('/compras',{proveedor_id:proveedor.value.id,numero_factura:numeroFactura.value,tipo_pago:tipoPago.value,monto_efectivo:efectivo.value,monto_qr:qr.value,comentario:comentario.value,detalles:detalles.value});proxy.$alert.success(`Compra ${data.numero} registrada`);printPurchase(data,company);proxy.$router.push('/compras')}catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo registrar')}finally{saving.value=false}})}
proxy.$axios.get('/configuracion').then(({data})=>Object.assign(company,data,{logo_url:data.logo?`${proxy.$imgBase}/images/${data.logo}`:''}))
loadProducts();loadProviders()
</script>
<style scoped>
.purchase-data :deep(.q-field--dense .q-field__control),.purchase-data :deep(.q-field--dense .q-field__marginal){height:34px;min-height:34px}.input-total :deep(.q-field__control){background:#eef7ff}
.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(135px,1fr));gap:7px;padding:8px;max-height:calc(100vh - 350px);overflow:auto}
.product-card{overflow:hidden;transition:.15s}.product-card:hover{border-color:#1976d2;transform:translateY(-1px)}
.product-image{height:90px;background:#f5f7f6;display:flex;align-items:center;justify-content:center}
.product-image img{width:100%;height:100%;object-fit:contain}.product-name{height:34px}
@media(max-width:1023px){.product-grid{max-height:none}}
</style>
