<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm"><div><div class="text-h6">{{ vencido?'Productos vencidos':'Productos por vencer' }}</div><div class="text-caption text-grey-7">Control de lotes con existencia disponible</div></div><q-space/><q-input v-if="!vencido" v-model.number="dias" dense outlined type="number" min="1" max="365" label="Próximos días" style="width:150px" @update:model-value="load"/></div>
    <q-card flat bordered><q-table flat dense :rows="rows" :columns="columns" row-key="id" :loading="loading">
      <template #body-cell-fecha="p"><q-td :props="p"><div class="date-chip" :class="dateClass(p.row)"><q-icon :name="vencido?'event_busy':'event'" size="17px"/><div><div class="date-chip__value">{{formatDate(p.value)}}</div><div class="date-chip__caption">{{dateCaption(p.row)}}</div></div></div></q-td></template>
      <template #body-cell-dias="p"><q-td :props="p"><q-badge rounded :color="vencido?'negative':Number(p.value)<=7?'orange':'amber-8'" class="q-px-sm q-py-xs">{{daysText(p.value)}}</q-badge></q-td></template>
    </q-table></q-card>
  </q-page>
</template>
<script setup>
import { computed, getCurrentInstance, ref, watch } from 'vue'
const props=defineProps({estado:{type:String,required:true}}),{proxy}=getCurrentInstance(),rows=ref([]),loading=ref(false),dias=ref(30),vencido=computed(()=>props.estado==='vencido')
const columns=[{name:'codigo',label:'Código',field:r=>r.producto?.codigo,align:'left'},{name:'producto',label:'Producto',field:r=>r.producto?.nombre,align:'left'},{name:'lote',label:'Lote',field:'lote',align:'left'},{name:'fecha',label:'Fecha de vencimiento',field:'fecha_vencimiento',align:'left'},{name:'dias',label:'Estado',field:'dias_vencimiento',align:'center'},{name:'cantidad',label:'Existencia',field:'cantidad_disponible',align:'right'}]
function formatDate(value){if(!value)return 'Sin fecha';const [year,month,day]=String(value).substring(0,10).split('-');return `${day}/${month}/${year}`}
function dateCaption(row){const days=Number(row.dias_vencimiento);if(vencido.value)return 'Fecha vencida';if(days===0)return 'Vence hoy';if(days===1)return 'Vence mañana';return 'Próximo vencimiento'}
function daysText(value){const days=Math.abs(Number(value));if(Number(value)===0)return 'Vence hoy';if(vencido.value)return `Vencido hace ${days} ${days===1?'día':'días'}`;return `Faltan ${days} ${days===1?'día':'días'}`}
function dateClass(row){if(vencido.value)return 'date-chip--expired';return Number(row.dias_vencimiento)<=7?'date-chip--urgent':'date-chip--warning'}
function load(){loading.value=true;proxy.$axios.get('/vencimientos',{params:{estado:props.estado,dias:dias.value}}).then(r=>rows.value=r.data).finally(()=>loading.value=false)}
watch(()=>props.estado,load);load()
</script>
<style scoped>
.date-chip{display:inline-flex;align-items:center;gap:7px;min-width:130px;padding:5px 9px;border-radius:8px}.date-chip__value{font-size:13px;font-weight:700;line-height:1.1}.date-chip__caption{margin-top:2px;font-size:9px;line-height:1;text-transform:uppercase;letter-spacing:.04em}.date-chip--expired{color:#b71c1c;background:#ffebee;border:1px solid #ffcdd2}.date-chip--urgent{color:#e65100;background:#fff3e0;border:1px solid #ffe0b2}.date-chip--warning{color:#795548;background:#fff8e1;border:1px solid #ffecb3}
</style>
