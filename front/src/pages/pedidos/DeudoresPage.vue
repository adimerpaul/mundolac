<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div><div class="text-h6">Deudores</div><div class="text-caption text-grey-7">Pedidos entregados con saldo pendiente de cobro</div></div>
      <q-space/>
      <q-btn flat icon="assignment" label="Ver pedidos" no-caps to="/pedidos"/>
    </div>

    <div class="row q-col-gutter-sm q-mb-sm">
      <div class="col-12 col-md-4">
        <q-card flat bordered class="bg-orange-1 text-orange-10"><q-card-section class="row items-center q-pa-sm"><q-avatar color="orange" text-color="white" icon="account_balance_wallet"/><div class="q-ml-sm"><div class="text-caption">Total por cobrar</div><div class="text-h6 text-weight-bold">Bs {{money(data.total)}}</div><div class="text-caption">{{data.deudas.length}} deudas · {{data.clientes.length}} clientes</div></div></q-card-section></q-card>
      </div>
      <div class="col-12 col-md-8">
        <q-card flat bordered>
          <q-card-section class="q-pa-sm q-pb-none row items-center"><div class="text-caption text-weight-bold text-grey-8">Clientes que más deben</div><q-space/><q-btn v-if="search" flat dense size="sm" no-caps icon="filter_alt_off" label="Ver todos" @click="search='';load()"/></q-card-section>
          <q-table flat dense :rows="data.clientes" :columns="clientColumns" row-key="cliente" :pagination="{rowsPerPage:5,sortBy:'total',descending:true}" :rows-per-page-options="[5,10,0]" no-data-label="Sin deudores" @row-click="(e,row)=>{search=row.cliente;load()}">
            <template #body-cell-rank="p"><q-td :props="p"><q-avatar size="22px" :color="p.rowIndex===0?'negative':'orange'" text-color="white" class="text-caption">{{p.rowIndex+1}}</q-avatar></q-td></template>
            <template #body-cell-total="p"><q-td :props="p"><b class="text-orange-10">Bs {{money(p.value)}}</b></q-td></template>
            <template #body-cell-desde="p"><q-td :props="p">{{dateOnly(p.value)}} <q-badge :color="p.row.dias>15?'negative':p.row.dias>7?'orange':'blue-grey'" class="q-ml-xs">{{p.row.dias}} días</q-badge></q-td></template>
          </q-table>
        </q-card>
      </div>
    </div>

    <q-card flat bordered>
      <q-card-section class="q-pa-sm"><q-input v-model="search" outlined dense debounce="300" clearable label="Buscar cliente, pedido o venta" style="max-width:420px" @update:model-value="load"><template #prepend><q-icon name="search"/></template></q-input></q-card-section>
      <q-table flat dense :rows="data.deudas" :columns="columns" row-key="id" :loading="loading" :pagination="{rowsPerPage:20}">
        <template #body-cell-cliente="p"><q-td :props="p"><b>{{p.row.pedido?.cliente_nombre||'—'}}</b><div class="text-caption text-grey-7">{{p.row.pedido?.direccion_entrega}}</div></q-td></template>
        <template #body-cell-dias="p"><q-td :props="p"><q-badge :color="p.value>15?'negative':p.value>7?'orange':'blue-grey'">{{p.value}} días</q-badge></q-td></template>
        <template #body-cell-pagado="p"><q-td :props="p"><span :class="Number(p.value)>0?'text-positive':'text-grey-5'">Bs {{money(p.value)}}</span><q-btn v-if="p.row.pagos?.length" flat dense round size="sm" icon="history" color="primary" @click="openHistory(p.row)"><q-tooltip>Ver pagos</q-tooltip></q-btn></q-td></template>
        <template #body-cell-saldo="p"><q-td :props="p"><b class="text-orange-10">Bs {{money(p.value)}}</b></q-td></template>
        <template #body-cell-actions="p"><q-td :props="p"><q-btn v-if="$store.hasPermission('Crear Ventas')" dense unelevated color="positive" icon="price_check" label="Cobrar" no-caps @click="openCollect(p.row)"/></q-td></template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialog" persistent><q-card style="width:440px;max-width:96vw">
      <q-card-section class="q-pb-sm"><div class="text-h6">Cobrar {{form.pedido}}</div><div class="text-caption text-grey-7">{{form.cliente}} · venta {{form.numero}}</div></q-card-section>
      <q-card-section class="q-gutter-sm q-pt-none">
        <div class="row text-caption text-grey-8"><span>Total Bs {{money(form.total)}} · Pagado Bs {{money(form.pagado)}}</span><q-space/><b class="text-orange-10">Saldo Bs {{money(form.saldo)}}</b></div>
        <q-input v-model.number="form.monto" outlined dense type="number" min="0.01" step=".01" :max="form.saldo" label="Monto a cobrar" prefix="Bs" :hint="Number(form.monto)<form.saldo?`Abono parcial · quedará debiendo Bs ${money(form.saldo-Number(form.monto||0))}`:'Paga todo el saldo'" @update:model-value="splitAmounts">
          <template #append><q-btn flat dense no-caps size="sm" label="Todo" @click="form.monto=form.saldo;splitAmounts()"/></template>
        </q-input>
        <q-option-group v-model="form.tipo_pago" inline :options="[{label:'Efectivo',value:'EFECTIVO'},{label:'QR',value:'QR'},{label:'Combinado',value:'COMBINADO'}]" @update:model-value="splitAmounts"/>
        <div v-if="form.tipo_pago==='COMBINADO'" class="row q-col-gutter-sm"><q-input v-model.number="form.monto_efectivo" outlined dense type="number" min="0" step=".01" label="Efectivo" prefix="Bs" class="col-6" @update:model-value="form.monto_qr=Math.max(0,Number((Number(form.monto||0)-Number(form.monto_efectivo||0)).toFixed(2)))"/><q-input v-model.number="form.monto_qr" outlined dense type="number" min="0" step=".01" label="QR" prefix="Bs" class="col-6"/></div>
        <q-input v-model="form.observacion" outlined dense autogrow label="Comentario (opcional)"/>
        <div class="text-caption text-grey-7">El pago entra a la caja de hoy.</div>
      </q-card-section>
      <q-card-actions align="right"><q-btn flat label="Cancelar" v-close-popup/><q-btn color="positive" icon="price_check" label="Registrar cobro" :loading="saving" :disable="invalid" @click="submit"/></q-card-actions>
    </q-card></q-dialog>

    <q-dialog v-model="historyDialog"><q-card style="width:560px;max-width:96vw">
      <q-card-section class="row items-center q-py-sm"><div><div class="text-subtitle1 text-weight-bold">Pagos de {{history.numero}}</div><div class="text-caption text-grey-7">{{history.pedido?.cliente_nombre}} · Total Bs {{money(history.total)}} · Saldo Bs {{money(history.saldo)}}</div></div><q-space/><q-btn flat round dense icon="close" v-close-popup/></q-card-section>
      <q-separator/>
      <q-list dense separator>
        <q-item v-for="pg in history.pagos||[]" :key="pg.id">
          <q-item-section avatar><q-icon name="payments" color="positive"/></q-item-section>
          <q-item-section><q-item-label><b>{{pg.numero}}</b> · Bs {{money(pg.monto)}} · {{pg.tipo_pago}}</q-item-label><q-item-label caption>{{dateTime(pg.fecha)}} · {{pg.usuario_nombre}}<template v-if="pg.observacion"> · {{pg.observacion}}</template></q-item-label></q-item-section>
          <q-item-section v-if="$store.hasPermission('Anular Ventas')" side><q-btn flat dense round size="sm" icon="cancel" color="negative" @click="cancelPago(pg)"><q-tooltip>Anular cobro</q-tooltip></q-btn></q-item-section>
        </q-item>
      </q-list>
    </q-card></q-dialog>
  </q-page>
</template>
<script setup>
import { computed, getCurrentInstance, reactive, ref } from 'vue'
const {proxy}=getCurrentInstance(),loading=ref(false),saving=ref(false),dialog=ref(false),historyDialog=ref(false),search=ref('')
const data=reactive({deudas:[],total:0,clientes:[]})
const form=reactive({id:null,numero:'',pedido:'',cliente:'',total:0,pagado:0,saldo:0,monto:0,tipo_pago:'EFECTIVO',monto_efectivo:0,monto_qr:0,observacion:''})
const history=reactive({})
const money=v=>Number(v||0).toFixed(2)
const days=v=>Math.max(0,Math.floor((Date.now()-new Date(v).getTime())/86400000))
const dateOnly=v=>v?new Date(v).toLocaleDateString('es-BO'):''
const dateTime=v=>v?new Date(v).toLocaleString('es-BO',{dateStyle:'short',timeStyle:'short'}):''
const columns=[{name:'pedido',label:'Pedido',field:r=>r.pedido?.numero||'—',align:'left'},{name:'venta',label:'Venta',field:'numero',align:'left'},{name:'cliente',label:'Cliente',field:r=>r.pedido?.cliente_nombre,align:'left'},{name:'fecha',label:'Entregado',field:r=>dateOnly(r.fecha),align:'left'},{name:'dias',label:'Antigüedad',field:r=>days(r.fecha),align:'center'},{name:'usuario',label:'Entregó',field:'usuario_nombre',align:'left'},{name:'total',label:'Total',field:'total',format:v=>`Bs ${money(v)}`,align:'right'},{name:'pagado',label:'Pagado',field:'monto_pagado',align:'right'},{name:'saldo',label:'Saldo',field:'saldo',align:'right'},{name:'actions',label:'',field:'id',align:'right'}]
const clientColumns=[{name:'rank',label:'#',field:'cliente',align:'center'},{name:'cliente',label:'Cliente',field:'cliente',align:'left'},{name:'cantidad',label:'Deudas',field:'cantidad',align:'center'},{name:'desde',label:'Debe desde',field:'desde',align:'left',sortable:true},{name:'total',label:'Saldo',field:'total',align:'right',sortable:true}]
const invalid=computed(()=>{const m=Number(form.monto||0);return !(m>0)||m-form.saldo>0.009||(form.tipo_pago==='COMBINADO'&&Math.abs(Number(form.monto_efectivo||0)+Number(form.monto_qr||0)-m)>0.009)})
function load(){loading.value=true;proxy.$axios.get('/pedidos-deudores',{params:{q:search.value}}).then(r=>Object.assign(data,r.data)).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar los deudores')).finally(()=>loading.value=false)}
function splitAmounts(){const m=Number(form.monto||0);form.monto_efectivo=Number((m/2).toFixed(2));form.monto_qr=Number((m-form.monto_efectivo).toFixed(2))}
function openCollect(row){const saldo=Number(row.saldo||0);Object.assign(form,{id:row.id,numero:row.numero,pedido:row.pedido?.numero||row.numero,cliente:row.pedido?.cliente_nombre||'',total:Number(row.total||0),pagado:Number(row.monto_pagado||0),saldo,monto:saldo,tipo_pago:'EFECTIVO',observacion:''});splitAmounts();dialog.value=true}
function submit(){if(invalid.value||saving.value)return;saving.value=true;proxy.$axios.post(`/ventas/${form.id}/pagos`,{monto:form.monto,tipo_pago:form.tipo_pago,monto_efectivo:form.monto_efectivo,monto_qr:form.monto_qr,observacion:form.observacion}).then(({data:pago})=>{proxy.$alert.success(pago.venta?.fecha_pago?`Cobro ${pago.numero} registrado · deuda saldada`:`Abono ${pago.numero} registrado`);dialog.value=false;load()}).catch(e=>proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo registrar el cobro')).finally(()=>saving.value=false)}
function openHistory(row){Object.keys(history).forEach(k=>delete history[k]);Object.assign(history,row);historyDialog.value=true}
function cancelPago(pg){proxy.$alert.dialog(`¿Anular el cobro ${pg.numero} de Bs ${money(pg.monto)}? El monto vuelve a quedar como deuda.`).onOk(()=>proxy.$axios.put(`/pagos/${pg.id}/anular`).then(()=>{proxy.$alert.success('Cobro anulado');historyDialog.value=false;load()}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo anular el cobro')))}
load()
</script>
