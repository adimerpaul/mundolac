<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm"><div><div class="text-h6">{{ vencido?'Productos vencidos':'Productos por vencer' }}</div><div class="text-caption text-grey-7">Control de lotes con existencia disponible</div></div><q-space/><q-input v-if="!vencido" v-model.number="dias" dense outlined type="number" min="1" max="365" label="Próximos días" style="width:150px" @update:model-value="load"/></div>
    <q-card flat bordered><q-table flat dense :rows="rows" :columns="columns" row-key="id" :loading="loading"><template #body-cell-fecha="p"><q-td :props="p"><q-badge :color="vencido?'negative':p.row.dias_vencimiento<=7?'orange':'warning'">{{p.value}}</q-badge></q-td></template><template #body-cell-dias="p"><q-td :props="p" :class="vencido?'text-negative':'text-orange-9'">{{vencido?`${Math.abs(p.value)} días vencido`:`${p.value} días`}}</q-td></template></q-table></q-card>
  </q-page>
</template>
<script setup>
import { computed, getCurrentInstance, ref, watch } from 'vue'
const props=defineProps({estado:{type:String,required:true}}),{proxy}=getCurrentInstance(),rows=ref([]),loading=ref(false),dias=ref(30),vencido=computed(()=>props.estado==='vencido')
const columns=[{name:'codigo',label:'Código',field:r=>r.producto?.codigo,align:'left'},{name:'producto',label:'Producto',field:r=>r.producto?.nombre,align:'left'},{name:'lote',label:'Lote',field:'lote',align:'left'},{name:'fecha',label:'Vencimiento',field:'fecha_vencimiento',align:'center'},{name:'dias',label:'Estado',field:'dias_vencimiento',align:'center'},{name:'cantidad',label:'Existencia',field:'cantidad_disponible',align:'right'}]
function load(){loading.value=true;proxy.$axios.get('/vencimientos',{params:{estado:props.estado,dias:dias.value}}).then(r=>rows.value=r.data).finally(()=>loading.value=false)}
watch(()=>props.estado,load);load()
</script>
