<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-xs"><div><div class="text-subtitle1 text-weight-bold">Ventas</div><div class="text-caption text-grey-7">Resumen e historial de ventas</div></div><q-space/>
      <q-btn-dropdown dense flat color="primary" icon="download" label="Exportar" no-caps class="q-mr-xs"><q-list dense><q-item clickable v-close-popup @click="download('excel')"><q-item-section avatar><q-icon name="table_view" color="positive"/></q-item-section><q-item-section>Excel</q-item-section></q-item><q-item clickable v-close-popup @click="download('pdf')"><q-item-section avatar><q-icon name="picture_as_pdf" color="negative"/></q-item-section><q-item-section>PDF</q-item-section></q-item></q-list></q-btn-dropdown>
      <template v-if="can('Registrar Ingresos Egresos')"><q-btn dense flat color="teal" icon="add_circle" label="Ingreso" no-caps class="q-mr-xs" @click="openMovement('INGRESO')"/><q-btn dense flat color="negative" icon="remove_circle" label="Egreso" no-caps class="q-mr-xs" @click="openMovement('EGRESO')"/></template>
      <q-btn v-if="can('Crear Ventas')" dense unelevated color="positive" icon="add_shopping_cart" label="Nueva venta" no-caps to="/ventas/nueva"/>
    </div>

<<<<<<< HEAD
    <div class="row q-col-gutter-sm q-mb-sm">
      <div v-for="card in cards" :key="card.label" class="col-6 col-md"><q-card flat bordered :class="`summary-card bg-${card.color}-1 text-${card.color}-9`"><q-card-section class="row items-center q-pa-sm"><q-avatar :color="card.color" text-color="white" :icon="card.icon" size="36px"/><div class="q-ml-sm"><div class="text-caption">{{card.label}}</div><div class="text-h6 text-weight-bold">{{card.money?'Bs ':''}}{{card.money?money(card.value):card.value}}</div></div></q-card-section></q-card></div>
=======
    <div class="row q-col-gutter-xs q-mb-xs">
      <div v-for="card in cards" :key="card.label" class="col-6 col-sm-4 col-md"><q-card flat bordered :class="`summary-card bg-${card.color}-1 text-${card.color}-9`"><q-card-section class="row items-center q-pa-xs"><q-avatar :color="card.color" text-color="white" :icon="card.icon" size="28px"/><div class="q-ml-sm"><div class="text-caption card-label">{{card.label}}</div><div class="text-subtitle1 text-weight-bold">{{card.money?'Bs ':''}}{{card.money?money(card.value):card.value}}</div><div v-if="card.sub" class="text-caption card-label">{{card.sub}}</div></div></q-card-section></q-card></div>
>>>>>>> 94250f62ade6398272c93fee17291b33aada27b2
    </div>

    <q-card flat bordered>
      <q-card-section class="row items-center q-gutter-xs q-pa-xs filtros">
        <q-input v-model="search" dense outlined debounce="300" clearable placeholder="Buscar número, usuario o estado" class="col-grow campo-busqueda" @update:model-value="load"><template #prepend><q-icon name="search" size="18px"/></template></q-input>
        <q-select v-model="tipo" :options="['VENTA','INGRESO','EGRESO']" dense outlined clearable label="Tipo" style="min-width:110px" @update:model-value="load"/>
        <q-select v-model="user" :options="summary.usuarios" option-label="name" dense outlined clearable label="Usuario" class="campo-usuario" @update:model-value="load"/>
        <q-input v-model="from" dense outlined stack-label type="date" label="Desde" class="campo-fecha" @update:model-value="load"/>
        <q-input v-model="to" dense outlined stack-label type="date" label="Hasta" class="campo-fecha" @update:model-value="load"/>
        <q-input v-model="horaFrom" dense outlined stack-label type="time" label="Hora desde" class="campo-hora" @update:model-value="load"/>
        <q-input v-model="horaTo" dense outlined stack-label type="time" label="Hora hasta" class="campo-hora" @update:model-value="load"/>
        <q-btn dense flat round icon="restart_alt" color="grey-7" size="sm" @click="resetFilters"><q-tooltip>Restablecer filtros (hoy 00:01 – 23:59)</q-tooltip></q-btn>
      </q-card-section>
      <q-table dense flat :rows="rows" :columns="columns" row-key="id" :loading="loading" v-model:pagination="pagination" :rows-per-page-options="[10,20,50,100,0]" @request="onRequest">
        <template #body-cell-total="p"><q-td :props="p"><b :class="p.row.tipo==='EGRESO'?'text-negative':''">{{p.row.tipo==='EGRESO'?'- ':''}}Bs {{money(p.value)}}</b></q-td></template>
        <template #body-cell-tipo="p"><q-td :props="p"><q-badge :color="tipoColor(p.value)" :label="p.value"/></q-td></template>
        <template #body-cell-tipo_pago="p"><q-td :props="p"><q-chip dense square :color="paymentColor(p.value)" text-color="white" :icon="payIcon(p.value)">{{p.value}}</q-chip><q-icon v-if="p.row.pago_cambiado" name="published_with_changes" color="orange-8" size="18px"><q-tooltip class="text-body2">Pago cambiado de <b>{{p.row.tipo_pago_original}}</b> a <b>{{p.value}}</b><br>{{p.row.pago_cambiado_por}} · {{formatDate(p.row.pago_cambiado_en)}}<template v-if="p.row.pago_cambiado_motivo"><br>Motivo: {{p.row.pago_cambiado_motivo}}</template></q-tooltip></q-icon></q-td></template>
<<<<<<< HEAD
        <template #body-cell-detalles="p"><q-td :props="p" class="productos-cell"><div v-for="d in p.row.detalles||[]" :key="d.id" class="producto-item"><span class="producto-cant">{{Number(d.cantidad)}}×</span>{{d.nombre}}</div></q-td></template>
        <template #body-cell-estado="p"><q-td :props="p"><q-badge :color="p.value==='COMPLETADA'?'positive':'negative'" :label="p.value"/></q-td></template>
        <template #body-cell-actions="p"><q-td :props="p"><q-btn-dropdown dense flat color="primary" icon="more_vert" dropdown-icon="none"><q-list dense style="min-width:150px"><q-item clickable v-close-popup @click="showDetail(p.row)"><q-item-section avatar><q-icon name="visibility" color="primary"/></q-item-section><q-item-section>Ver detalle</q-item-section></q-item><q-item clickable v-close-popup @click="printRow(p.row)"><q-item-section avatar><q-icon name="print" color="blue-grey"/></q-item-section><q-item-section>Imprimir</q-item-section></q-item><q-separator/><q-item v-if="canChangePayment(p.row)" clickable v-close-popup class="text-orange-9" @click="openPayment(p.row)"><q-item-section avatar><q-icon name="swap_horiz"/></q-item-section><q-item-section><q-item-label>Cambiar pago</q-item-label><q-item-label caption class="text-orange-9">Solo una vez</q-item-label></q-item-section></q-item><q-item v-else-if="can('Cambiar Pago Ventas')&&p.row.pago_cambiado" clickable disable><q-item-section avatar><q-icon name="lock"/></q-item-section><q-item-section><q-item-label>Pago ya cambiado</q-item-label><q-item-label caption>No se puede repetir</q-item-label></q-item-section></q-item><q-item v-if="can('Anular Ventas')&&p.row.estado==='COMPLETADA'" clickable v-close-popup class="text-negative" @click="cancel(p.row)"><q-item-section avatar><q-icon name="cancel"/></q-item-section><q-item-section>Anular</q-item-section></q-item></q-list></q-btn-dropdown></q-td></template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialog"><q-card style="width:760px;max-width:96vw"><q-card-section class="row items-center q-py-sm"><div><div class="text-subtitle1 text-weight-bold">{{selected.numero}}</div><div class="text-caption">{{formatDate(selected.fecha)}} · {{selected.usuario_nombre}}</div></div><q-space/><q-btn flat round dense icon="print" color="primary" @click="printSelected"/><q-badge :color="selected.estado==='COMPLETADA'?'positive':'negative'" :label="selected.estado"/><q-btn flat round dense icon="close" v-close-popup/></q-card-section><q-separator/><q-table flat class="detalle-table" :rows="selected.detalles||[]" :columns="detailColumns" row-key="id" hide-pagination :rows-per-page-options="[0]"><template #body-cell-total="p"><q-td :props="p">Bs {{money(p.value)}}</q-td></template></q-table><q-separator/><q-card-section class="q-pa-sm"><div class="row"><span>Subtotal</span><q-space/>Bs {{money(selected.subtotal)}}</div><div class="row text-negative"><span>Descuento</span><q-space/>- Bs {{money(selected.descuento)}}</div><div class="row"><span>Efectivo</span><q-space/>Bs {{money(selected.monto_efectivo)}}</div><div class="row"><span>QR</span><q-space/>Bs {{money(selected.monto_qr)}}</div><div class="row text-h6 text-primary"><b>Total</b><q-space/><b>Bs {{money(selected.total)}}</b></div><q-banner v-if="selected.pago_cambiado" dense class="bg-orange-1 text-orange-10 q-mt-sm rounded-borders"><template #avatar><q-icon name="published_with_changes" color="orange-8"/></template>Pago cambiado de <b>{{selected.tipo_pago_original}}</b> a <b>{{selected.tipo_pago}}</b> por {{selected.pago_cambiado_por}} el {{formatDate(selected.pago_cambiado_en)}}.<template v-if="selected.pago_cambiado_motivo"><br>Motivo: {{selected.pago_cambiado_motivo}}</template></q-banner></q-card-section></q-card></q-dialog>
=======
        <template #body-cell-detalles="p"><q-td :props="p" class="productos-cell"><div v-if="p.row.tipo!=='VENTA'" class="text-grey-8 movimiento-obs">{{p.row.observacion}}</div><div v-for="d in (p.row.detalles||[])" :key="d.id" class="producto-linea"><span class="text-weight-bold text-grey-9">{{qty(d)}}</span> <span class="text-primary">{{d.nombre}}</span></div><span v-if="p.row.tipo==='VENTA'&&!(p.row.detalles||[]).length" class="text-grey-6">—</span></q-td></template>
        <template #body-cell-estado="p"><q-td :props="p"><q-badge :color="p.value==='COMPLETADA'?'positive':'negative'" :label="p.value"/><div v-if="p.row.tipo==='VENTA'&&!p.row.fecha_pago&&p.value==='COMPLETADA'"><q-badge color="orange" :label="`DEBE Bs ${money(p.row.saldo)}`" class="q-mt-xs"/></div><div v-else-if="p.row.tipo_pago==='CREDITO'&&p.value==='COMPLETADA'" class="text-caption text-positive">Deuda cobrada</div></q-td></template>
        <template #body-cell-pedido="p"><q-td :props="p"><template v-if="p.row.pedido"><b>{{p.row.pedido.numero}}</b><div class="text-caption text-grey-7">{{p.row.pedido.cliente_nombre}}</div></template><span v-else class="text-grey-5">{{p.row.tipo==='VENTA'?'Directa':'—'}}</span></q-td></template>
        <template #body-cell-actions="p"><q-td :props="p"><q-btn-dropdown dense flat color="primary" icon="more_vert" dropdown-icon="none"><q-list dense style="min-width:150px"><q-item clickable v-close-popup @click="showDetail(p.row)"><q-item-section avatar><q-icon name="visibility" color="primary"/></q-item-section><q-item-section>Ver detalle</q-item-section></q-item><q-item clickable v-close-popup @click="printRow(p.row)"><q-item-section avatar><q-icon name="print" color="blue-grey"/></q-item-section><q-item-section>Imprimir</q-item-section></q-item><q-separator/><q-item v-if="canCollect(p.row)" clickable v-close-popup class="text-positive" @click="openCollect(p.row)"><q-item-section avatar><q-icon name="price_check"/></q-item-section><q-item-section>Registrar cobro</q-item-section></q-item><q-item v-if="canChangePayment(p.row)" clickable v-close-popup class="text-orange-9" @click="openPayment(p.row)"><q-item-section avatar><q-icon name="swap_horiz"/></q-item-section><q-item-section><q-item-label>Cambiar pago</q-item-label><q-item-label caption class="text-orange-9">Solo una vez</q-item-label></q-item-section></q-item><q-item v-else-if="can('Cambiar Pago Ventas')&&p.row.pago_cambiado" clickable disable><q-item-section avatar><q-icon name="lock"/></q-item-section><q-item-section><q-item-label>Pago ya cambiado</q-item-label><q-item-label caption>No se puede repetir</q-item-label></q-item-section></q-item><q-item v-if="can('Anular Ventas')&&p.row.estado==='COMPLETADA'" clickable v-close-popup class="text-negative" @click="cancel(p.row)"><q-item-section avatar><q-icon name="cancel"/></q-item-section><q-item-section>Anular</q-item-section></q-item></q-list></q-btn-dropdown></q-td></template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialog"><q-card style="width:760px;max-width:96vw"><q-card-section class="row items-center q-py-sm"><div><div class="text-subtitle1 text-weight-bold">{{selected.numero}}</div><div class="text-caption">{{formatDate(selected.fecha)}} · {{selected.usuario_nombre}}<template v-if="selected.pedido"> · Pedido {{selected.pedido.numero}} ({{selected.pedido.cliente_nombre}})</template></div><div v-if="selected.observacion" class="text-caption text-grey-8">{{selected.observacion}}</div><div v-if="selected.tipo==='VENTA'" class="text-caption">{{selected.fecha_pago?`Pagado: ${formatDate(selected.fecha_pago)}`:`Debe Bs ${money(selected.saldo)} (pagado Bs ${money(selected.monto_pagado)})`}}</div></div><q-space/><q-btn flat round dense icon="print" color="primary" @click="printSelected"/><q-badge :color="selected.estado==='COMPLETADA'?'positive':'negative'" :label="selected.estado"/><q-btn flat round dense icon="close" v-close-popup/></q-card-section><q-separator/><q-table dense flat :rows="selected.detalles||[]" :columns="detailColumns" row-key="id" hide-pagination :rows-per-page-options="[0]"><template #body-cell-total="p"><q-td :props="p">Bs {{money(p.value)}}</q-td></template></q-table><q-separator/><q-card-section class="q-pa-sm"><div class="row"><span>Subtotal</span><q-space/>Bs {{money(selected.subtotal)}}</div><div class="row text-negative"><span>Descuento</span><q-space/>- Bs {{money(selected.descuento)}}</div><div class="row"><span>Efectivo</span><q-space/>Bs {{money(selected.monto_efectivo)}}</div><div class="row"><span>QR</span><q-space/>Bs {{money(selected.monto_qr)}}</div><div class="row text-h6 text-primary"><b>Total</b><q-space/><b>Bs {{money(selected.total)}}</b></div><template v-if="selected.pagos?.length"><div class="text-caption text-weight-bold q-mt-sm">Pagos recibidos</div><div v-for="pg in selected.pagos" :key="pg.id" class="row items-center text-caption" :class="pg.estado==='ANULADO'?'text-grey-5':''"><q-icon name="payments" size="14px" class="q-mr-xs"/>{{pg.numero}} · {{pg.tipo==='COBRO'?'Cobro':'Contado'}} · {{formatDate(pg.fecha)}} · {{pg.tipo_pago}} · {{pg.usuario_nombre}}<span v-if="pg.estado==='ANULADO'">&nbsp;(anulado)</span><q-space/>Bs {{money(pg.monto)}}<q-btn v-if="pg.tipo==='COBRO'&&pg.estado==='COMPLETADO'&&can('Anular Ventas')" flat dense round size="xs" icon="cancel" color="negative" class="q-ml-xs" @click="cancelPago(pg)"><q-tooltip>Anular cobro</q-tooltip></q-btn></div></template><q-banner v-if="selected.pago_cambiado" dense class="bg-orange-1 text-orange-10 q-mt-sm rounded-borders"><template #avatar><q-icon name="published_with_changes" color="orange-8"/></template>Pago cambiado de <b>{{selected.tipo_pago_original}}</b> a <b>{{selected.tipo_pago}}</b> por {{selected.pago_cambiado_por}} el {{formatDate(selected.pago_cambiado_en)}}.<template v-if="selected.pago_cambiado_motivo"><br>Motivo: {{selected.pago_cambiado_motivo}}</template></q-banner></q-card-section></q-card></q-dialog>
>>>>>>> 94250f62ade6398272c93fee17291b33aada27b2

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
    <q-dialog v-model="movDialog" persistent><q-card style="width:440px;max-width:96vw">
      <q-card-section class="q-pb-sm"><div class="text-h6" :class="mov.tipo==='EGRESO'?'text-negative':'text-teal'">Registrar {{mov.tipo==='EGRESO'?'egreso':'ingreso'}}</div><div class="text-caption text-grey-7">{{mov.tipo==='EGRESO'?'Salida de dinero de caja (gastos, pagos, retiros)':'Entrada de dinero a caja que no es una venta'}}</div></q-card-section>
      <q-card-section class="q-gutter-sm q-pt-none">
        <q-input v-model.number="mov.monto" outlined dense type="number" min="0.01" step=".01" label="Monto" prefix="Bs" autofocus @update:model-value="splitMov"/>
        <q-option-group v-model="mov.tipo_pago" inline :options="[{label:'Efectivo',value:'EFECTIVO'},{label:'QR',value:'QR'},{label:'Combinado',value:'COMBINADO'}]" @update:model-value="splitMov"/>
        <div v-if="mov.tipo_pago==='COMBINADO'" class="row q-col-gutter-sm"><q-input v-model.number="mov.monto_efectivo" outlined dense type="number" min="0" step=".01" label="Efectivo" prefix="Bs" class="col-6" @update:model-value="mov.monto_qr=Math.max(0,Number((Number(mov.monto||0)-Number(mov.monto_efectivo||0)).toFixed(2)))"/><q-input v-model.number="mov.monto_qr" outlined dense type="number" min="0" step=".01" label="QR" prefix="Bs" class="col-6"/></div>
        <q-input v-model="mov.observacion" outlined dense autogrow label="Concepto / comentario *"/>
      </q-card-section>
      <q-card-actions align="right"><q-btn flat label="Cancelar" v-close-popup/><q-btn :color="mov.tipo==='EGRESO'?'negative':'teal'" icon="save" label="Registrar" :loading="movSaving" :disable="movInvalid" @click="submitMovement"/></q-card-actions>
    </q-card></q-dialog>
    <q-dialog v-model="collectDialog" persistent><q-card style="width:420px;max-width:96vw">
      <q-card-section class="q-pb-sm"><div class="text-h6">Registrar cobro {{collect.numero}}</div><div class="text-caption text-grey-7">Total Bs {{money(collect.total)}} · saldo Bs {{money(collect.saldo)}} · entra a la caja de hoy</div></q-card-section>
      <q-card-section class="q-gutter-sm q-pt-none">
        <q-input v-model.number="collect.monto" outlined dense type="number" min="0.01" step=".01" label="Monto a cobrar" prefix="Bs" :hint="Number(collect.monto)<collect.saldo?`Abono parcial · quedará Bs ${money(collect.saldo-Number(collect.monto||0))}`:'Paga todo el saldo'" @update:model-value="splitCollect"/>
        <q-option-group v-model="collect.tipo_pago" inline :options="[{label:'Efectivo',value:'EFECTIVO'},{label:'QR',value:'QR'},{label:'Combinado',value:'COMBINADO'}]"/>
        <div v-if="collect.tipo_pago==='COMBINADO'" class="row q-col-gutter-sm"><q-input v-model.number="collect.monto_efectivo" outlined dense type="number" min="0" step=".01" label="Efectivo" prefix="Bs" class="col-6" @update:model-value="collect.monto_qr=Math.max(0,Number((Number(collect.monto||0)-Number(collect.monto_efectivo||0)).toFixed(2)))"/><q-input v-model.number="collect.monto_qr" outlined dense type="number" min="0" step=".01" label="QR" prefix="Bs" class="col-6"/></div>
      </q-card-section>
      <q-card-actions align="right"><q-btn flat label="Cancelar" v-close-popup/><q-btn color="positive" icon="price_check" label="Registrar cobro" :loading="collecting" :disable="collectInvalid" @click="submitCollect"/></q-card-actions>
    </q-card></q-dialog>
  </q-page>
</template>

<script setup>
import {computed,getCurrentInstance,reactive,ref} from 'vue'
import {printSale} from '../../addons/ventaPrint'
const today=()=>{const d=new Date();return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`}
<<<<<<< HEAD
const {proxy}=getCurrentInstance(),rows=ref([]),loading=ref(false),search=ref(''),from=ref(today()),to=ref(today()),user=ref(null),dialog=ref(false),selected=reactive({}),summary=reactive({efectivo:0,qr:0,total:0,descuento:0,cantidad:0,usuarios:[]})
const pagination=ref({page:1,rowsPerPage:20,rowsNumber:0}),money=v=>Number(v||0).toFixed(2),can=p=>proxy.$store.hasPermission(p)
const cards=computed(()=>[{label:'Total vendido',value:summary.total,money:true,icon:'trending_up',color:'primary'},...(can('Ver Ganancias')&&summary.ganancia!==null&&summary.ganancia!==undefined?[{label:'Ganancia',value:summary.ganancia,money:true,icon:'savings',color:'teal'}]:[]),{label:'Efectivo',value:summary.efectivo,money:true,icon:'payments',color:'green'},{label:'QR',value:summary.qr,money:true,icon:'qr_code_2',color:'blue'},{label:'Ventas',value:summary.cantidad,money:false,icon:'receipt_long',color:'purple'}])
const columns=[{name:'actions',label:'',align:'left'},{name:'numero',label:'Nº venta',field:'numero',align:'left'},{name:'fecha',label:'Fecha',field:r=>formatDate(r.fecha),align:'left'},{name:'usuario',label:'Usuario',field:'usuario_nombre',align:'left'},{name:'tipo_pago',label:'Pago',field:'tipo_pago',align:'center'},{name:'detalles',label:'Productos vendidos',field:'detalles_count',align:'left'},{name:'descuento',label:'Descuento',field:'descuento',format:v=>`Bs ${money(v)}`,align:'right'},{name:'total',label:'Total',field:'total',align:'right'},...(can('Ver Ganancias')?[{name:'ganancia',label:'Ganancia',field:'ganancia',format:v=>`Bs ${money(v)}`,align:'right',classes:'text-teal-8 text-weight-medium'}]:[]),{name:'estado',label:'Estado',field:'estado',align:'center'}]
=======
const {proxy}=getCurrentInstance(),rows=ref([]),loading=ref(false),search=ref(''),from=ref(today()),to=ref(today()),horaFrom=ref('00:01'),horaTo=ref('23:59'),user=ref(null),dialog=ref(false),selected=reactive({}),summary=reactive({ventas:0,credito:0,cobros:0,ingresos:0,egresos:0,efectivo:0,qr:0,total:0,descuento:0,cantidad:0,usuarios:[]}),tipo=ref(null)
const pagination=ref({page:1,rowsPerPage:20,rowsNumber:0}),money=v=>Number(v||0).toFixed(2),can=p=>proxy.$store.hasPermission(p)
const cards=computed(()=>[{label:'Vendido',value:summary.ventas,money:true,icon:'trending_up',color:'primary',sub:`${summary.cantidad} ventas${Number(summary.credito)?` · por cobrar Bs ${money(summary.credito)}`:''}`},{label:'Cobros de deudas',value:summary.cobros,money:true,icon:'price_check',color:'orange'},{label:'Ingresos',value:summary.ingresos,money:true,icon:'add_circle',color:'teal'},{label:'Egresos',value:summary.egresos,money:true,icon:'remove_circle',color:'red'},{label:'Efectivo (caja)',value:summary.efectivo,money:true,icon:'payments',color:'green'},{label:'QR (caja)',value:summary.qr,money:true,icon:'qr_code_2',color:'blue'},{label:'Total caja',value:summary.total,money:true,icon:'account_balance',color:'purple',sub:'Pagos recibidos + ingresos − egresos'}])
const columns=[{name:'actions',label:'',align:'left'},{name:'numero',label:'Nº venta',field:'numero',align:'left'},{name:'fecha',label:'Fecha',field:r=>formatDate(r.fecha),align:'left'},{name:'tipo',label:'Tipo',field:'tipo',align:'center'},{name:'usuario',label:'Usuario',field:'usuario_nombre',align:'left'},{name:'pedido',label:'Pedido',field:r=>r.pedido?.numero,align:'left'},{name:'tipo_pago',label:'Pago',field:'tipo_pago',align:'center'},{name:'detalles',label:'Productos',field:'detalles',align:'left'},{name:'descuento',label:'Descuento',field:'descuento',format:v=>`Bs ${money(v)}`,align:'right'},{name:'total',label:'Total',field:'total',align:'right'},{name:'estado',label:'Estado',field:'estado',align:'center'}]
>>>>>>> 94250f62ade6398272c93fee17291b33aada27b2
const detailColumns=[{name:'codigo',label:'Código',field:'codigo',align:'left'},{name:'nombre',label:'Producto',field:'nombre',align:'left'},{name:'cantidad',label:'Cant.',field:'cantidad',align:'center'},{name:'precio',label:'Precio',field:'precio_venta',format:v=>`Bs ${money(v)}`,align:'right'},{name:'total',label:'Total',field:'total',align:'right'}]
const params=()=>({q:search.value,desde:from.value,hasta:to.value,hora_desde:horaFrom.value,hora_hasta:horaTo.value,user_id:user.value?.id,tipo:tipo.value})
const qty=d=>`${Number(d.cantidad)}${d.unidad&&d.unidad!=='UNIDAD'?d.unidad.toLowerCase():''}`
const paymentColor=v=>v==='EFECTIVO'?'green':v==='QR'?'blue':v==='CREDITO'?'orange':'purple'
const tipoColor=v=>({VENTA:'primary',INGRESO:'teal',EGRESO:'negative'}[v]||'grey')
const payIcon=v=>v==='QR'?'qr_code_2':v==='EFECTIVO'?'payments':v==='CREDITO'?'schedule':'account_balance_wallet'
const payDialog=ref(false),paySaving=ref(false)
const payForm=reactive({id:null,numero:'',total:0,actual:'',tipo_pago:'QR',monto_efectivo:0,monto_qr:0,motivo:''})
const payOptions=computed(()=>['EFECTIVO','QR','COMBINADO'].filter(o=>o!==payForm.actual))
const paySum=computed(()=>(Number(payForm.monto_efectivo)||0)+(Number(payForm.monto_qr)||0))
const payDiff=computed(()=>Math.abs(payForm.total-paySum.value)<0.005?0:payForm.total-paySum.value)
const payInvalid=computed(()=>!payForm.tipo_pago||payForm.tipo_pago===payForm.actual||(payForm.tipo_pago==='COMBINADO'&&(payDiff.value!==0||Number(payForm.monto_efectivo)<0||Number(payForm.monto_qr)<0)))
// Ventas con deuda (pedidos entregados): fecha_pago vacía hasta cobrar todo el saldo.
const collectDialog=ref(false),collecting=ref(false),collect=reactive({id:null,numero:'',total:0,saldo:0,monto:0,tipo_pago:'EFECTIVO',monto_efectivo:0,monto_qr:0})
const canCollect=row=>can('Crear Ventas')&&row.tipo==='VENTA'&&row.estado==='COMPLETADA'&&!row.fecha_pago
const collectInvalid=computed(()=>{const m=Number(collect.monto||0);return !(m>0)||m-collect.saldo>0.009||(collect.tipo_pago==='COMBINADO'&&Math.abs(Number(collect.monto_efectivo||0)+Number(collect.monto_qr||0)-m)>0.009)})
function splitCollect(){const m=Number(collect.monto||0);collect.monto_efectivo=Number((m/2).toFixed(2));collect.monto_qr=Number((m-collect.monto_efectivo).toFixed(2))}
function openCollect(row){const saldo=Number(row.saldo||0);Object.assign(collect,{id:row.id,numero:row.numero,total:Number(row.total||0),saldo,monto:saldo,tipo_pago:'EFECTIVO'});splitCollect();collectDialog.value=true}
function submitCollect(){if(collectInvalid.value||collecting.value)return;collecting.value=true;proxy.$axios.post(`/ventas/${collect.id}/pagos`,{monto:collect.monto,tipo_pago:collect.tipo_pago,monto_efectivo:collect.monto_efectivo,monto_qr:collect.monto_qr}).then(({data})=>{proxy.$alert.success(`Cobro ${data.numero} de ${collect.numero} registrado`);collectDialog.value=false;load()}).catch(e=>proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo registrar el cobro')).finally(()=>collecting.value=false)}
function cancelPago(pg){proxy.$alert.dialog(`¿Anular el cobro ${pg.numero} de Bs ${money(pg.monto)}? El monto vuelve a quedar como deuda.`).onOk(()=>proxy.$axios.put(`/pagos/${pg.id}/anular`).then(()=>{proxy.$alert.success('Cobro anulado');dialog.value=false;load()}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo anular el cobro')))}
const canChangePayment=row=>can('Cambiar Pago Ventas')&&row.estado==='COMPLETADA'&&!row.pago_cambiado&&row.tipo_pago!=='CREDITO'
// Ingresos y egresos manuales de caja.
const movDialog=ref(false),movSaving=ref(false),mov=reactive({tipo:'INGRESO',monto:null,tipo_pago:'EFECTIVO',monto_efectivo:0,monto_qr:0,observacion:''})
const movInvalid=computed(()=>!(Number(mov.monto)>0)||!String(mov.observacion||'').trim()||(mov.tipo_pago==='COMBINADO'&&Math.abs(Number(mov.monto_efectivo||0)+Number(mov.monto_qr||0)-Number(mov.monto||0))>0.009))
function splitMov(){const t=Number(mov.monto||0);mov.monto_efectivo=Number((t/2).toFixed(2));mov.monto_qr=Number((t-mov.monto_efectivo).toFixed(2))}
function openMovement(t){Object.assign(mov,{tipo:t,monto:null,tipo_pago:'EFECTIVO',monto_efectivo:0,monto_qr:0,observacion:''});movDialog.value=true}
function submitMovement(){if(movInvalid.value||movSaving.value)return;movSaving.value=true;proxy.$axios.post('/ventas-movimientos',{...mov}).then(({data})=>{proxy.$alert.success(`${data.tipo==='EGRESO'?'Egreso':'Ingreso'} ${data.numero} registrado`);movDialog.value=false;load()}).catch(e=>proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo registrar')).finally(()=>movSaving.value=false)}
function resetFilters(){search.value='';tipo.value=null;user.value=null;from.value=today();to.value=today();horaFrom.value='00:01';horaTo.value='23:59';load()}
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

<style scoped>
<<<<<<< HEAD
.summary-card{border-radius:10px}
.productos-cell{min-width:200px;white-space:normal;padding-top:2px!important;padding-bottom:2px!important}
.producto-item{font-size:11px;line-height:1.25;color:#546e7a}
.producto-cant{font-weight:700;color:#0d47a1;margin-right:2px}
.detalle-table :deep(td),.detalle-table :deep(th){font-size:15px}
=======
.summary-card{border-radius:8px}.movimiento-obs{max-width:260px;white-space:normal;font-size:11px}
.card-label{line-height:1.1}
/* Los inputs nativos date/time necesitan ancho fijo: en columnas de grilla el
   selector del navegador recorta la hora y se ve incompleta. */
.filtros{row-gap:4px}
.campo-busqueda{min-width:180px}
.campo-usuario{width:150px}
.campo-fecha{width:150px}
.campo-hora{width:130px}
.filtros :deep(.q-field__control){height:36px}
.filtros :deep(.q-field__native){font-size:12px}
.filtros :deep(.q-field__label){font-size:11px}
.productos-cell{white-space:normal;max-width:360px;padding-top:3px;padding-bottom:3px}
.producto-linea{font-size:11px;line-height:1.25}
:deep(.q-table th),:deep(.q-table td){font-size:12px;padding:3px 8px}
:deep(.q-table th){white-space:nowrap}
@media(max-width:599px){.campo-usuario,.campo-fecha,.campo-hora{width:calc(50% - 6px)}}
>>>>>>> 94250f62ade6398272c93fee17291b33aada27b2
</style>
