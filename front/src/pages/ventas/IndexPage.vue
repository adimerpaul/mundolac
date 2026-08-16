<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm"><div><div class="text-subtitle1 text-weight-bold">Ventas</div><div class="text-caption text-grey-7">Resumen e historial de ventas</div></div><q-space/>
      <q-btn-dropdown dense flat color="primary" icon="download" label="Exportar" no-caps class="q-mr-xs"><q-list dense><q-item clickable v-close-popup @click="download('excel')"><q-item-section avatar><q-icon name="table_view" color="positive"/></q-item-section><q-item-section>Excel</q-item-section></q-item><q-item clickable v-close-popup @click="download('pdf')"><q-item-section avatar><q-icon name="picture_as_pdf" color="negative"/></q-item-section><q-item-section>PDF</q-item-section></q-item></q-list></q-btn-dropdown>
      <q-btn v-if="can('Crear Ventas')" dense unelevated color="positive" icon="add_shopping_cart" label="Nueva venta" no-caps to="/ventas/nueva"/>
    </div>

    <div class="row q-col-gutter-sm q-mb-sm">
      <div v-for="card in cards" :key="card.label" class="col-6 col-md-3"><q-card flat bordered :class="`summary-card bg-${card.color}-1 text-${card.color}-9`"><q-card-section class="row items-center q-pa-sm"><q-avatar :color="card.color" text-color="white" :icon="card.icon" size="36px"/><div class="q-ml-sm"><div class="text-caption">{{card.label}}</div><div class="text-h6 text-weight-bold">{{card.money?'Bs ':''}}{{card.money?money(card.value):card.value}}</div></div></q-card-section></q-card></div>
    </div>

    <q-card flat bordered>
      <q-card-section class="row q-col-gutter-sm q-pa-sm">
        <q-input v-model="search" dense outlined debounce="300" clearable placeholder="Buscar número, usuario o estado" class="col-12 col-md-4" @update:model-value="load"><template #prepend><q-icon name="search"/></template></q-input>
        <q-select v-model="user" :options="summary.usuarios" option-label="name" dense outlined clearable label="Usuario" class="col-12 col-md-3" @update:model-value="load"/>
        <q-input v-model="from" dense outlined type="date" label="Desde" class="col-6 col-md-2" @update:model-value="load"/><q-input v-model="to" dense outlined type="date" label="Hasta" class="col-6 col-md-2" @update:model-value="load"/>
      </q-card-section>
      <q-table dense flat :rows="rows" :columns="columns" row-key="id" :loading="loading" v-model:pagination="pagination" :rows-per-page-options="[10,20,50,100,0]" @request="onRequest">
        <template #body-cell-total="p"><q-td :props="p"><b>Bs {{money(p.value)}}</b></q-td></template>
        <template #body-cell-tipo_pago="p"><q-td :props="p"><q-chip dense square :color="paymentColor(p.value)" text-color="white" :icon="payIcon(p.value)">{{p.value}}</q-chip><q-icon v-if="p.row.pago_cambiado" name="published_with_changes" color="orange-8" size="18px"><q-tooltip class="text-body2">Pago cambiado de <b>{{p.row.tipo_pago_original}}</b> a <b>{{p.value}}</b><br>{{p.row.pago_cambiado_por}} · {{formatDate(p.row.pago_cambiado_en)}}<template v-if="p.row.pago_cambiado_motivo"><br>Motivo: {{p.row.pago_cambiado_motivo}}</template></q-tooltip></q-icon></q-td></template>
        <template #body-cell-estado="p"><q-td :props="p"><q-badge :color="p.value==='COMPLETADA'?'positive':'negative'" :label="p.value"/></q-td></template>
        <template #body-cell-actions="p"><q-td :props="p"><q-btn-dropdown dense flat color="primary" icon="more_vert" dropdown-icon="none"><q-list dense style="min-width:150px"><q-item clickable v-close-popup @click="showDetail(p.row)"><q-item-section avatar><q-icon name="visibility" color="primary"/></q-item-section><q-item-section>Ver detalle</q-item-section></q-item><q-item clickable v-close-popup @click="printRow(p.row)"><q-item-section avatar><q-icon name="print" color="blue-grey"/></q-item-section><q-item-section>Imprimir</q-item-section></q-item><q-separator/><q-item v-if="canChangePayment(p.row)" clickable v-close-popup class="text-orange-9" @click="openPayment(p.row)"><q-item-section avatar><q-icon name="swap_horiz"/></q-item-section><q-item-section><q-item-label>Cambiar pago</q-item-label><q-item-label caption class="text-orange-9">Solo una vez</q-item-label></q-item-section></q-item><q-item v-else-if="can('Cambiar Pago Ventas')&&p.row.pago_cambiado" clickable disable><q-item-section avatar><q-icon name="lock"/></q-item-section><q-item-section><q-item-label>Pago ya cambiado</q-item-label><q-item-label caption>No se puede repetir</q-item-label></q-item-section></q-item><q-item v-if="can('Anular Ventas')&&p.row.estado==='COMPLETADA'" clickable v-close-popup class="text-negative" @click="cancel(p.row)"><q-item-section avatar><q-icon name="cancel"/></q-item-section><q-item-section>Anular</q-item-section></q-item></q-list></q-btn-dropdown></q-td></template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialog"><q-card style="width:760px;max-width:96vw"><q-card-section class="row items-center q-py-sm"><div><div class="text-subtitle1 text-weight-bold">{{selected.numero}}</div><div class="text-caption">{{formatDate(selected.fecha)}} · {{selected.usuario_nombre}}</div></div><q-space/><q-btn flat round dense icon="print" color="primary" @click="printSelected"/><q-badge :color="selected.estado==='COMPLETADA'?'positive':'negative'" :label="selected.estado"/><q-btn flat round dense icon="close" v-close-popup/></q-card-section><q-separator/><q-table dense flat :rows="selected.detalles||[]" :columns="detailColumns" row-key="id" hide-pagination :rows-per-page-options="[0]"><template #body-cell-total="p"><q-td :props="p">Bs {{money(p.value)}}</q-td></template></q-table><q-separator/><q-card-section class="q-pa-sm"><div class="row"><span>Subtotal</span><q-space/>Bs {{money(selected.subtotal)}}</div><div class="row text-negative"><span>Descuento</span><q-space/>- Bs {{money(selected.descuento)}}</div><div class="row"><span>Efectivo</span><q-space/>Bs {{money(selected.monto_efectivo)}}</div><div class="row"><span>QR</span><q-space/>Bs {{money(selected.monto_qr)}}</div><div class="row text-h6 text-primary"><b>Total</b><q-space/><b>Bs {{money(selected.total)}}</b></div><q-banner v-if="selected.pago_cambiado" dense class="bg-orange-1 text-orange-10 q-mt-sm rounded-borders"><template #avatar><q-icon name="published_with_changes" color="orange-8"/></template>Pago cambiado de <b>{{selected.tipo_pago_original}}</b> a <b>{{selected.tipo_pago}}</b> por {{selected.pago_cambiado_por}} el {{formatDate(selected.pago_cambiado_en)}}.<template v-if="selected.pago_cambiado_motivo"><br>Motivo: {{selected.pago_cambiado_motivo}}</template></q-banner></q-card-section></q-card></q-dialog>

    <q-dialog v-model="payDialog" persistent>
      <q-card style="width:440px;max-width:96vw">
        <q-card-section class="row items-center q-py-sm">
          <q-icon name="swap_horiz" color="orange-8" size="24px" class="q-mr-sm"/>
          <div><div class="text-subtitle1 text-weight-bold">Cambiar método de pago</div><div class="text-caption text-grey-7">{{payForm.numero}} · Bs {{money(payForm.total)}}</div></div>
          <q-space/><q-btn flat round dense icon="close" :disable="paySaving" v-close-popup/>
        </q-card-section>
        <q-separator/>
        <q-banner dense class="bg-orange-1 text-orange-10">
          <template #avatar><q-icon name="warning" color="orange-8"/></template>
          Este cambio <b>solo se puede hacer una vez</b> por venta. Después quedará bloqueado y registrado con su usuario y la fecha.
        </q-banner>
        <q-card-section class="q-pa-sm">
          <div class="row items-center q-mb-sm text-caption">
            <span class="text-grey-7">Pago actual:</span>
            <q-chip dense square :color="paymentColor(payForm.actual)" text-color="white" :icon="payIcon(payForm.actual)" class="q-ml-sm">{{payForm.actual}}</q-chip>
          </div>
          <q-select v-model="payForm.tipo_pago" :options="payOptions" dense outlined label="Nuevo método de pago" @update:model-value="syncPayAmounts"/>
          <div v-if="payForm.tipo_pago==='COMBINADO'" class="row q-col-gutter-sm q-mt-xs">
            <q-input v-model.number="payForm.monto_efectivo" dense outlined type="number" step="0.01" min="0" label="Efectivo" class="col-6"/>
            <q-input v-model.number="payForm.monto_qr" dense outlined type="number" step="0.01" min="0" label="QR" class="col-6"/>
            <div class="col-12 text-caption" :class="payDiff===0?'text-positive':'text-negative'">
              Suma Bs {{money(paySum)}} de Bs {{money(payForm.total)}}
              <span v-if="payDiff!==0">· {{payDiff>0?'faltan':'sobran'}} Bs {{money(Math.abs(payDiff))}}</span>
            </div>
          </div>
          <q-input v-model="payForm.motivo" v-uppercase dense outlined maxlength="255" label="Motivo (opcional)" class="q-mt-sm"/>
        </q-card-section>
        <q-separator/>
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat dense no-caps label="Cancelar" :disable="paySaving" v-close-popup/>
          <q-btn unelevated dense no-caps color="orange-8" icon="check" label="Cambiar pago" :loading="paySaving" :disable="paySaving||payInvalid" @click="confirmPayment"/>
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import {computed,getCurrentInstance,reactive,ref} from 'vue'
import {printSale} from '../../addons/ventaPrint'
const {proxy}=getCurrentInstance(),rows=ref([]),loading=ref(false),search=ref(''),from=ref(''),to=ref(''),user=ref(null),dialog=ref(false),selected=reactive({}),summary=reactive({efectivo:0,qr:0,total:0,descuento:0,cantidad:0,usuarios:[]})
const pagination=ref({page:1,rowsPerPage:20,rowsNumber:0}),money=v=>Number(v||0).toFixed(2),can=p=>proxy.$store.hasPermission(p)
const cards=computed(()=>[{label:'Total vendido',value:summary.total,money:true,icon:'trending_up',color:'primary'},{label:'Efectivo',value:summary.efectivo,money:true,icon:'payments',color:'green'},{label:'QR',value:summary.qr,money:true,icon:'qr_code_2',color:'blue'},{label:'Ventas',value:summary.cantidad,money:false,icon:'receipt_long',color:'purple'}])
const columns=[{name:'actions',label:'',align:'left'},{name:'numero',label:'Nº venta',field:'numero',align:'left'},{name:'fecha',label:'Fecha',field:r=>formatDate(r.fecha),align:'left'},{name:'usuario',label:'Usuario',field:'usuario_nombre',align:'left'},{name:'tipo_pago',label:'Pago',field:'tipo_pago',align:'center'},{name:'detalles',label:'Productos',field:'detalles_count',align:'center'},{name:'descuento',label:'Descuento',field:'descuento',format:v=>`Bs ${money(v)}`,align:'right'},{name:'total',label:'Total',field:'total',align:'right'},{name:'estado',label:'Estado',field:'estado',align:'center'}]
const detailColumns=[{name:'codigo',label:'Código',field:'codigo',align:'left'},{name:'nombre',label:'Producto',field:'nombre',align:'left'},{name:'cantidad',label:'Cant.',field:'cantidad',align:'center'},{name:'precio',label:'Precio',field:'precio_venta',format:v=>`Bs ${money(v)}`,align:'right'},{name:'total',label:'Total',field:'total',align:'right'}]
const params=()=>({q:search.value,desde:from.value,hasta:to.value,user_id:user.value?.id}),paymentColor=v=>v==='EFECTIVO'?'green':v==='QR'?'blue':'purple'
const payIcon=v=>v==='QR'?'qr_code_2':v==='EFECTIVO'?'payments':'account_balance_wallet'
const payDialog=ref(false),paySaving=ref(false)
const payForm=reactive({id:null,numero:'',total:0,actual:'',tipo_pago:'QR',monto_efectivo:0,monto_qr:0,motivo:''})
const payOptions=computed(()=>['EFECTIVO','QR','COMBINADO'].filter(o=>o!==payForm.actual))
const paySum=computed(()=>(Number(payForm.monto_efectivo)||0)+(Number(payForm.monto_qr)||0))
const payDiff=computed(()=>Math.abs(payForm.total-paySum.value)<0.005?0:payForm.total-paySum.value)
const payInvalid=computed(()=>!payForm.tipo_pago||payForm.tipo_pago===payForm.actual||(payForm.tipo_pago==='COMBINADO'&&(payDiff.value!==0||Number(payForm.monto_efectivo)<0||Number(payForm.monto_qr)<0)))
const canChangePayment=row=>can('Cambiar Pago Ventas')&&row.estado==='COMPLETADA'&&!row.pago_cambiado
function formatDate(v){return v?new Date(v).toLocaleString('es-BO'):''}function load(){onRequest({pagination:pagination.value})}function loadSummary(){proxy.$axios.get('/ventas-resumen',{params:params()}).then(r=>Object.assign(summary,r.data))}
function onRequest({pagination:p}){loading.value=true;proxy.$axios.get('/ventas',{params:{...params(),page:p.page,per_page:p.rowsPerPage}}).then(r=>{rows.value=r.data.data;pagination.value={...p,rowsNumber:r.data.total};loadSummary()}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar las ventas')).finally(()=>loading.value=false)}
function printSelected(){printSale(selected,proxy.$store.company,proxy.$imgBase)}
function showDetail(row){proxy.$axios.get(`/ventas/${row.id}`).then(r=>{Object.assign(selected,r.data);dialog.value=true})}function printRow(row){proxy.$axios.get(`/ventas/${row.id}`).then(r=>printSale(r.data,proxy.$store.company,proxy.$imgBase))}
function cancel(row){proxy.$alert.dialog(`¿Anular la venta ${row.numero}? El stock será restaurado.`).onOk(()=>proxy.$axios.put(`/ventas/${row.id}/anular`).then(()=>{proxy.$alert.success('Venta anulada');load()}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo anular')))}
function openPayment(row){if(!canChangePayment(row))return;Object.assign(payForm,{id:row.id,numero:row.numero,total:Number(row.total||0),actual:row.tipo_pago,tipo_pago:row.tipo_pago==='EFECTIVO'?'QR':'EFECTIVO',monto_efectivo:0,monto_qr:0,motivo:''});syncPayAmounts();payDialog.value=true}
function syncPayAmounts(){const t=Number(payForm.total||0);if(payForm.tipo_pago==='EFECTIVO'){payForm.monto_efectivo=t;payForm.monto_qr=0}else if(payForm.tipo_pago==='QR'){payForm.monto_efectivo=0;payForm.monto_qr=t}else{payForm.monto_efectivo=t;payForm.monto_qr=0}}
function confirmPayment(){if(payInvalid.value||paySaving.value)return;proxy.$alert.dialog('Cambiar método de pago',`La venta ${payForm.numero} pasará de ${payForm.actual} a ${payForm.tipo_pago}. Esto solo se puede hacer una vez y no se podrá deshacer.`).onOk(submitPayment)}
function submitPayment(){if(payInvalid.value||paySaving.value)return;paySaving.value=true
  proxy.$axios.put(`/ventas/${payForm.id}/tipo-pago`,{tipo_pago:payForm.tipo_pago,monto_efectivo:Number(payForm.monto_efectivo)||0,monto_qr:Number(payForm.monto_qr)||0,motivo:payForm.motivo||null})
    .then(()=>{proxy.$alert.success('Método de pago actualizado','Esta venta ya no se puede volver a cambiar');payDialog.value=false;load()})
    .catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo cambiar el método de pago'))
    .finally(()=>{paySaving.value=false})}
async function download(type){try{const response=await proxy.$axios.get(`/ventas-exportar/${type}`,{params:params(),responseType:'blob'});const url=URL.createObjectURL(response.data),a=document.createElement('a');a.href=url;a.download=`ventas.${type==='excel'?'xlsx':'pdf'}`;a.click();URL.revokeObjectURL(url)}catch{proxy.$alert.error('No se pudo exportar el reporte')}}
load()
</script>

<style scoped>.summary-card{border-radius:10px}</style>
