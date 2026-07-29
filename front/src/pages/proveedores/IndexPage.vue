<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm"><div><div class="text-h6">Proveedores</div><div class="text-caption text-grey-7">Directorio de proveedores</div></div><q-space/><q-btn color="primary" icon="add" label="Nuevo proveedor" no-caps @click="open"/></div>
    <q-card flat bordered><q-table flat dense :rows="rows" :columns="columns" row-key="id" :loading="loading"><template #body-cell-actions="p"><q-td :props="p"><q-btn flat dense round icon="edit" @click="open(p.row)"/><q-btn flat dense round color="negative" icon="delete" @click="remove(p.row)"/></q-td></template></q-table></q-card>
    <q-dialog v-model="dialog"><q-card style="width:520px;max-width:95vw"><q-form @submit="save"><q-card-section><div class="text-h6">{{ form.id ? 'Editar' : 'Nuevo' }} proveedor</div></q-card-section><q-card-section class="q-gutter-sm"><q-input v-model="form.nombre" outlined dense label="Nombre *" :rules="[v=>!!v||'Requerido']"/><q-input v-model="form.nit" outlined dense label="NIT"/><q-input v-model="form.telefono" outlined dense label="Teléfono"/><q-input v-model="form.direccion" outlined dense label="Dirección"/></q-card-section><q-card-actions align="right"><q-btn flat label="Cancelar" v-close-popup/><q-btn color="primary" label="Guardar" type="submit" :loading="saving"/></q-card-actions></q-form></q-card></q-dialog>
  </q-page>
</template>
<script setup>
import { getCurrentInstance, reactive, ref } from 'vue'
const {proxy}=getCurrentInstance(),rows=ref([]),loading=ref(false),dialog=ref(false),saving=ref(false),form=reactive({})
const columns=[{name:'actions',label:'',field:'actions'},{name:'nombre',label:'Nombre',field:'nombre',align:'left'},{name:'nit',label:'NIT',field:'nit',align:'left'},{name:'telefono',label:'Teléfono',field:'telefono',align:'left'},{name:'direccion',label:'Dirección',field:'direccion',align:'left'}]
function load(){loading.value=true;proxy.$axios.get('/proveedores').then(r=>rows.value=r.data).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar los proveedores')).finally(()=>loading.value=false)}
function open(row={}){Object.keys(form).forEach(k=>delete form[k]);Object.assign(form,row);dialog.value=true}
async function save(){saving.value=true;try{if(form.id)await proxy.$axios.put(`/proveedores/${form.id}`,form);else await proxy.$axios.post('/proveedores',form);proxy.$alert.success('Proveedor guardado');dialog.value=false;load()}catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo guardar')}finally{saving.value=false}}
function remove(row){proxy.$alert.dialog(`¿Eliminar al proveedor ${row.nombre}?`).onOk(()=>proxy.$axios.delete(`/proveedores/${row.id}`).then(()=>{proxy.$alert.success('Proveedor eliminado');load()}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo eliminar')))}
load()
</script>
