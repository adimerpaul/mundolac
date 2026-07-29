<template>
  <q-page class="map-page">
    <div class="map-toolbar">
      <div><div class="text-subtitle1 text-weight-bold">Mapa de clientes</div><div class="text-caption text-grey-7">{{clients.length}} clientes ubicados</div></div><q-space/>
      <q-select v-model="layer" :options="layers" option-label="label" outlined dense options-dense emit-value map-options label="Capa" style="width:190px" @update:model-value="changeLayer"/>
      <q-btn flat icon="list" label="Lista" no-caps to="/clientes"/>
    </div>
    <div ref="mapElement" class="clients-map"/>
    <q-card v-if="selected" class="client-map-card">
      <q-card-section class="row items-center q-pa-sm"><q-avatar rounded size="52px" color="blue-1" text-color="primary"><img v-if="selected.foto" :src="photoUrl(selected.foto)"/><q-icon v-else name="person"/></q-avatar><div class="q-ml-sm"><b>{{selected.nombre}}</b><div class="text-caption">{{selected.direccion||'Sin dirección'}}</div><div class="text-caption text-grey-7">{{selected.celular||selected.telefono||''}}</div></div><q-space/><q-btn flat round icon="close" @click="selected=null"/></q-card-section>
    </q-card>
  </q-page>
</template>
<script setup>
import { getCurrentInstance, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
const {proxy}=getCurrentInstance(),route=useRoute(),mapElement=ref(null),clients=ref([]),selected=ref(null),layer=ref('calles')
const layers=[{label:'Mapa de calles',value:'calles'},{label:'Vista satelital',value:'satelite'},{label:'OpenStreetMap',value:'osm'}]
const urls={calles:'https://mt1.google.com/vt/lyrs=r&x={x}&y={y}&z={z}',satelite:'https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',osm:'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'}
let map=null,tile=null,markers=[]
const photoUrl=path=>`${proxy.$imgBase}/images/${path}`
function changeLayer(){if(tile)tile.remove();tile=L.tileLayer(urls[layer.value],{maxZoom:20,attribution:layer.value==='osm'?'OpenStreetMap':'Google Maps'}).addTo(map)}
function render(){markers.forEach(m=>m.remove());markers=[];const bounds=[];clients.value.forEach(client=>{if(client.latitud===null||client.longitud===null)return;const pos=[Number(client.latitud),Number(client.longitud)],icon=L.divIcon({className:'client-marker-wrap',html:'<div class="client-marker"><span class="material-icons">person</span></div>',iconSize:[34,42],iconAnchor:[17,42]});const marker=L.marker(pos,{icon}).addTo(map).bindTooltip(client.nombre);marker.on('click',()=>selected.value=client);markers.push(marker);bounds.push(pos);if(String(route.query.cliente)===String(client.id)){selected.value=client;map.setView(pos,18)}});if(bounds.length&&!route.query.cliente)map.fitBounds(bounds,{padding:[45,45],maxZoom:15})}
onMounted(()=>{map=L.map(mapElement.value,{zoomControl:true}).setView([-16.5005,-68.1354],13);changeLayer();proxy.$axios.get('/clientes',{params:{per_page:0,activo:1}}).then(r=>{clients.value=r.data.data;render()})})
onBeforeUnmount(()=>map?.remove())
</script>
<style>.map-page{position:relative;height:calc(100vh - 54px);overflow:hidden}.map-toolbar{height:58px;display:flex;align-items:center;gap:8px;padding:6px 12px;background:#fff;border-bottom:1px solid #dfe6eb}.clients-map{height:calc(100% - 58px);width:100%}.client-map-card{position:absolute;left:16px;bottom:20px;width:390px;max-width:calc(100% - 32px);z-index:500;border-radius:12px}.client-marker-wrap{background:transparent;border:0}.client-marker{width:34px;height:34px;display:flex;align-items:center;justify-content:center;border:3px solid #fff;border-radius:50% 50% 50% 0;transform:rotate(-45deg);color:#fff;background:#1565c0;box-shadow:0 3px 8px rgba(0,0,0,.35)}.client-marker span{font-size:19px;transform:rotate(45deg)}@media(max-width:600px){.map-toolbar .q-btn .q-btn__content span:not(.q-icon){display:none}}</style>
