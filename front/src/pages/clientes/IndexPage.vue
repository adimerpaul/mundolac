<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div><div class="text-h6">Clientes</div><div class="text-caption text-grey-7">Datos comerciales y ubicación de clientes</div></div>
      <q-space/><q-btn flat color="primary" icon="map" label="Ver todos en mapa" no-caps to="/clientes/mapa" class="q-mr-xs"/>
      <q-btn v-if="canManage" color="primary" icon="person_add" label="Nuevo cliente" no-caps @click="openForm()"/>
    </div>
    <q-card flat bordered>
      <q-card-section class="row q-col-gutter-sm q-pa-sm">
        <q-input v-model="search" outlined dense debounce="300" clearable label="Buscar nombre, NIT, teléfono o dirección" class="col-12 col-md-6" @update:model-value="load"><template #prepend><q-icon name="search"/></template></q-input>
      </q-card-section>
      <q-table flat dense :rows="rows" :columns="columns" row-key="id" :loading="loading" v-model:pagination="pagination" @request="request">
        <template #body-cell-cliente="p"><q-td :props="p"><div class="row items-center no-wrap"><q-avatar rounded size="42px" color="blue-1" text-color="primary"><img v-if="p.row.foto" :src="photoUrl(p.row.foto)"/><q-icon v-else name="person"/></q-avatar><div class="q-ml-sm"><b>{{p.row.nombre}}</b><div class="text-caption text-grey-7">{{p.row.nit?`NIT ${p.row.nit}`:'Sin NIT'}}</div></div></div></q-td></template>
        <template #body-cell-contacto="p"><q-td :props="p"><div>{{p.row.celular||p.row.telefono||'—'}}</div><div class="text-caption text-grey-7">{{p.row.email}}</div></q-td></template>
        <template #body-cell-ubicacion="p"><q-td :props="p"><div>{{p.row.direccion||'Sin dirección'}}</div><div class="text-caption text-grey-7">{{p.row.zona}}</div></q-td></template>
        <template #body-cell-estado="p"><q-td :props="p"><q-badge :color="p.value?'positive':'grey'" :label="p.value?'ACTIVO':'INACTIVO'"/></q-td></template>
        <template #body-cell-actions="p"><q-td :props="p"><q-btn-dropdown dense flat color="primary" icon="more_vert" dropdown-icon="none"><q-list dense style="min-width:180px"><q-item v-if="hasLocation(p.row)" clickable v-close-popup @click="showOnMap(p.row)"><q-item-section avatar><q-icon name="location_on" color="red"/></q-item-section><q-item-section>Ver en mapa</q-item-section></q-item><q-item v-if="p.row.celular" clickable v-close-popup tag="a" :href="`https://wa.me/591${cleanPhone(p.row.celular)}`" target="_blank"><q-item-section avatar><q-icon name="chat" color="positive"/></q-item-section><q-item-section>WhatsApp</q-item-section></q-item><q-separator/><q-item v-if="canManage" clickable v-close-popup @click="openForm(p.row)"><q-item-section avatar><q-icon name="edit" color="primary"/></q-item-section><q-item-section>Editar</q-item-section></q-item><q-item v-if="canManage" clickable v-close-popup class="text-negative" @click="remove(p.row)"><q-item-section avatar><q-icon name="delete"/></q-item-section><q-item-section>Eliminar</q-item-section></q-item></q-list></q-btn-dropdown></q-td></template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialog" persistent><q-card style="width:850px;max-width:96vw"><q-form @submit="save"><q-card-section class="row items-center q-py-sm"><div class="text-h6">{{form.id?'Editar':'Nuevo'}} cliente</div><q-space/><q-toggle v-model="form.activo" label="Activo" color="positive"/><q-btn round flat icon="close" v-close-popup/></q-card-section><q-separator/>
      <q-card-section class="row q-col-gutter-sm">
        <div class="col-12 col-sm-3"><div class="client-photo"><img v-if="previewUrl" :src="previewUrl"/><q-icon v-else name="person" size="70px" color="grey-5"/></div><q-file v-model="photo" dense outlined accept="image/*" label="Fotografía" class="q-mt-xs"><template #prepend><q-icon name="photo_camera"/></template></q-file></div>
        <div class="col-12 col-sm-9 row q-col-gutter-sm">
          <q-input v-model="form.nombre" dense outlined label="Nombre o razón social *" class="col-12" :rules="[v=>!!v||'Requerido']"/>
          <q-input v-model="form.nit" dense outlined label="NIT" class="col-12 col-sm-4"/><q-input v-model="form.telefono" dense outlined label="Teléfono" class="col-6 col-sm-4"/><q-input v-model="form.celular" dense outlined label="Celular" class="col-6 col-sm-4"/>
          <q-input v-model="form.email" dense outlined type="email" label="Correo electrónico" class="col-12 col-sm-6"/><q-input v-model="form.zona" dense outlined label="Zona" class="col-12 col-sm-6"/>
          <q-input v-model="form.direccion" dense outlined label="Dirección" class="col-12"/>
          <q-input v-model="form.referencia" dense outlined label="Referencia de ubicación" class="col-12 col-sm-8"/><div class="col-12 col-sm-4"><q-btn outline color="primary" icon="add_location_alt" label="Elegir en mapa" no-caps class="full-width" @click="mapDialog=true"/></div>
          <q-input v-model.number="form.latitud" dense outlined type="number" step=".0000001" label="Latitud" class="col-6"/><q-input v-model.number="form.longitud" dense outlined type="number" step=".0000001" label="Longitud" class="col-6"/>
          <q-input v-model="form.observacion" dense outlined autogrow label="Observaciones" class="col-12"/>
        </div>
      </q-card-section><q-separator/><q-card-actions align="right"><q-btn flat label="Cancelar" v-close-popup/><q-btn color="primary" type="submit" icon="save" label="Guardar cliente" :loading="saving"/></q-card-actions></q-form></q-card></q-dialog>

    <q-dialog v-model="mapDialog" @show="initPicker" @hide="destroyPicker"><q-card style="width:900px;max-width:96vw"><q-card-section class="row items-center q-py-sm"><div><b>Seleccionar ubicación</b><div class="text-caption">Haz clic sobre el mapa para marcar el cliente</div></div><q-space/><q-btn flat round icon="close" v-close-popup/></q-card-section><div ref="pickerElement" class="picker-map"/><q-card-actions align="right"><q-btn color="primary" icon="check" label="Usar esta ubicación" v-close-popup/></q-card-actions></q-card></q-dialog>
  </q-page>
</template>
<script setup>
import { computed, getCurrentInstance, onBeforeUnmount, reactive, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
const {proxy}=getCurrentInstance(),rows=ref([]),loading=ref(false),search=ref(''),dialog=ref(false),mapDialog=ref(false),saving=ref(false),photo=ref(null),localPreview=ref(''),pickerElement=ref(null)
const pagination=ref({page:1,rowsPerPage:20,rowsNumber:0}),form=reactive({}),canManage=computed(()=>proxy.$store.hasPermission('Gestionar Clientes'))
let pickerMap=null,pickerMarker=null
const columns=[{name:'actions',label:'',field:'id'},{name:'cliente',label:'Cliente',field:'nombre',align:'left'},{name:'contacto',label:'Contacto',field:'celular',align:'left'},{name:'ubicacion',label:'Ubicación',field:'direccion',align:'left'},{name:'estado',label:'Estado',field:'activo',align:'center'}]
const photoUrl=path=>`${proxy.$imgBase}/images/${path}`,previewUrl=computed(()=>localPreview.value||(form.foto?photoUrl(form.foto):'')),cleanPhone=v=>String(v||'').replace(/\D/g,''),hasLocation=r=>r.latitud!==null&&r.longitud!==null
watch(photo,file=>{if(localPreview.value)URL.revokeObjectURL(localPreview.value);localPreview.value=file?URL.createObjectURL(file):''})
function load(){request({pagination:pagination.value})}function request({pagination:p}){loading.value=true;proxy.$axios.get('/clientes',{params:{q:search.value,page:p.page,per_page:p.rowsPerPage}}).then(r=>{rows.value=r.data.data;pagination.value={...p,rowsNumber:r.data.total}}).finally(()=>loading.value=false)}
function openForm(row={}){Object.keys(form).forEach(k=>delete form[k]);Object.assign(form,{nombre:'',nit:'',telefono:'',celular:'',email:'',direccion:'',zona:'',latitud:null,longitud:null,foto:null,referencia:'',observacion:'',activo:true},row);photo.value=null;dialog.value=true}
async function save(){saving.value=true;try{const payload={...form};const response=form.id?await proxy.$axios.put(`/clientes/${form.id}`,payload):await proxy.$axios.post('/clientes',payload);let client=response.data;if(photo.value){const fd=new FormData();fd.append('foto',photo.value);client=(await proxy.$axios.post(`/clientes/${client.id}/foto`,fd)).data}proxy.$alert.success('Cliente guardado');dialog.value=false;load()}catch(e){proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo guardar')}finally{saving.value=false}}
function remove(row){proxy.$alert.dialog(`¿Eliminar al cliente ${row.nombre}?`).onOk(()=>proxy.$axios.delete(`/clientes/${row.id}`).then(()=>{proxy.$alert.success('Cliente eliminado');load()}))}
function showOnMap(row){proxy.$router.push({path:'/clientes/mapa',query:{cliente:row.id}})}
function initPicker(){const center=hasLocation(form)?[Number(form.latitud),Number(form.longitud)]:[-16.5005,-68.1354];pickerMap=L.map(pickerElement.value).setView(center,hasLocation(form)?17:13);L.tileLayer('https://mt1.google.com/vt/lyrs=r&x={x}&y={y}&z={z}',{maxZoom:20,attribution:'Google Maps'}).addTo(pickerMap);if(hasLocation(form))pickerMarker=L.marker(center).addTo(pickerMap);pickerMap.on('click',e=>{form.latitud=Number(e.latlng.lat.toFixed(7));form.longitud=Number(e.latlng.lng.toFixed(7));if(pickerMarker)pickerMarker.setLatLng(e.latlng);else pickerMarker=L.marker(e.latlng).addTo(pickerMap)})}
function destroyPicker(){pickerMap?.remove();pickerMap=null;pickerMarker=null}onBeforeUnmount(()=>{destroyPicker();if(localPreview.value)URL.revokeObjectURL(localPreview.value)});load()
</script>
<style scoped>.client-photo{height:180px;border:1px dashed #b0bec5;border-radius:10px;background:#f5f7f8;display:flex;align-items:center;justify-content:center;overflow:hidden}.client-photo img{width:100%;height:100%;object-fit:cover}.picker-map{height:520px;max-height:68vh}</style>
