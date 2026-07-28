<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <div><div class="text-h5 text-weight-bold">Usuarios</div><div class="text-grey-7">Accesos y permisos del sistema</div></div>
      <q-space />
      <q-btn v-if="can('Crear Usuarios')" color="primary" icon="person_add" label="Nuevo usuario" no-caps @click="openForm()" />
    </div>
    <q-card flat bordered>
      <q-table flat :rows="filtered" :columns="columns" row-key="id" :loading="loading">
        <template #top>
          <q-input v-model="search" outlined dense debounce="250" placeholder="Buscar usuario" clearable style="width:320px;max-width:100%">
            <template #prepend><q-icon name="search" /></template>
          </q-input>
        </template>
        <template #body-cell-user="p">
          <q-td :props="p"><div class="row items-center no-wrap q-gutter-sm"><q-avatar color="blue-1" text-color="primary" icon="person" /><div><b>{{p.row.name}}</b><div class="text-caption text-grey-7">@{{p.row.username}}</div></div></div></q-td>
        </template>
        <template #body-cell-permissions="p"><q-td :props="p"><q-badge color="blue-grey" :label="`${p.row.permissions?.length || 0} permisos`" /></q-td></template>
        <template #body-cell-actions="p"><q-td :props="p">
          <q-btn v-if="can('Editar Usuarios')" flat round dense icon="edit" color="primary" @click="openForm(p.row)" />
          <q-btn v-if="can('Gestionar Permisos')" flat round dense icon="admin_panel_settings" color="secondary" @click="openPermissions(p.row)" />
          <q-btn v-if="can('Editar Usuarios')" flat round dense icon="lock_reset" color="orange" @click="resetPassword(p.row)" />
          <q-btn v-if="can('Eliminar Usuarios') && p.row.id !== proxy.$store.user.id" flat round dense icon="delete" color="negative" @click="remove(p.row)" />
        </q-td></template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialog" persistent><q-card style="width:620px;max-width:95vw"><q-form @submit="save">
      <q-card-section class="row items-center"><div class="text-h6">{{form.id?'Editar':'Nuevo'}} usuario</div><q-space/><q-btn flat round dense icon="close" v-close-popup/></q-card-section><q-separator/>
      <q-card-section class="row q-col-gutter-md">
        <q-input v-model="form.name" outlined dense label="Nombre completo *" class="col-12" :rules="[required]"/>
        <q-input v-model="form.username" outlined dense label="Usuario *" class="col-12 col-sm-6" :disable="!!form.id" :rules="[required]"/>
        <q-input v-model="form.ci" outlined dense label="Cédula de identidad" class="col-12 col-sm-6"/>
        <q-input v-model="form.email" type="email" outlined dense label="Correo" class="col-12 col-sm-6"/>
        <q-input v-model="form.celular" outlined dense label="Celular" class="col-12 col-sm-6"/>
        <q-input v-if="!form.id" v-model="form.password" type="password" outlined dense label="Contraseña *" hint="Mínimo 6 caracteres" class="col-12" :rules="[v=>v?.length>=6||'Mínimo 6 caracteres']"/>
      </q-card-section><q-card-actions align="right"><q-btn flat label="Cancelar" no-caps v-close-popup/><q-btn color="primary" label="Guardar" no-caps type="submit" :loading="saving"/></q-card-actions>
    </q-form></q-card></q-dialog>

    <q-dialog v-model="permissionDialog"><q-card style="width:650px;max-width:95vw">
      <q-card-section class="row items-center"><div><div class="text-h6">Permisos</div><div class="text-caption">{{selectedUser?.name}}</div></div><q-space/><q-btn flat round dense icon="close" v-close-popup/></q-card-section><q-separator/>
      <q-card-section><div v-for="group in permissionGroups" :key="group.name" class="q-mb-md"><div class="text-subtitle2 q-mb-xs">{{group.name}}</div><div class="row"><q-checkbox v-for="p in group.items" :key="p.id" v-model="selectedPermissions" :val="p.id" :label="p.name" class="col-12 col-sm-6"/></div></div></q-card-section>
      <q-card-actions align="right"><q-btn flat label="Cancelar" v-close-popup/><q-btn color="primary" label="Guardar permisos" :loading="savingPermissions" @click="savePermissions"/></q-card-actions>
    </q-card></q-dialog>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, reactive, ref } from 'vue'
const { proxy } = getCurrentInstance()
const rows=ref([]), search=ref(''), loading=ref(false), saving=ref(false), dialog=ref(false)
const permissionDialog=ref(false), permissions=ref([]), selectedPermissions=ref([]), selectedUser=ref(null), savingPermissions=ref(false)
const empty=()=>({id:null,name:'',username:'',email:'',celular:'',ci:'',password:'123456'})
const form=reactive(empty())
const columns=[{name:'user',label:'Usuario',field:'name',align:'left'},{name:'email',label:'Correo',field:'email',align:'left'},{name:'celular',label:'Celular',field:'celular',align:'left'},{name:'ci',label:'CI',field:'ci',align:'left'},{name:'permissions',label:'Permisos',align:'center'},{name:'actions',label:'Acciones',align:'right'}]
const can=p=>proxy.$store.hasPermission(p), required=v=>!!v||'Campo requerido'
const filtered=computed(()=>{const q=(search.value||'').toLowerCase();return rows.value.filter(u=>[u.name,u.username,u.email,u.ci].some(v=>(v||'').toLowerCase().includes(q)))})
const permissionGroups=computed(()=>['Usuarios','Productos'].map(name=>({name,items:permissions.value.filter(p=>p.name.includes(name))})))
function load(){loading.value=true;proxy.$axios.get('/users').then(r=>rows.value=r.data).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar los usuarios')).finally(()=>loading.value=false)}
function openForm(row=null){Object.assign(form,empty(),row||{});dialog.value=true}
function save(){saving.value=true;const data={...form};const req=form.id?proxy.$axios.put(`/users/${form.id}`,data):proxy.$axios.post('/users',data);req.then(()=>{proxy.$alert.success('Usuario guardado');dialog.value=false;load()}).catch(e=>proxy.$alert.error(Object.values(e.response?.data?.errors||{})[0]?.[0]||e.response?.data?.message||'No se pudo guardar')).finally(()=>saving.value=false)}
function openPermissions(user){selectedUser.value=user;Promise.all([proxy.$axios.get('/permissions'),proxy.$axios.get(`/users/${user.id}/permissions`)]).then(([a,b])=>{permissions.value=a.data;selectedPermissions.value=b.data;permissionDialog.value=true}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudieron cargar los permisos'))}
function savePermissions(){savingPermissions.value=true;proxy.$axios.put(`/users/${selectedUser.value.id}/permissions`,{permissions:selectedPermissions.value}).then(()=>{proxy.$alert.success('Permisos actualizados');permissionDialog.value=false;load()}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudieron guardar')).finally(()=>savingPermissions.value=false)}
function resetPassword(u){proxy.$alert.dialog(`¿Restablecer la contraseña de ${u.name} a 123456?`).onOk(()=>proxy.$axios.put(`/users/${u.id}/reset-password`).then(()=>proxy.$alert.success('Contraseña restablecida')).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo restablecer')))}
function remove(u){proxy.$alert.dialog(`¿Eliminar al usuario ${u.name}?`).onOk(()=>proxy.$axios.delete(`/users/${u.id}`).then(()=>{proxy.$alert.success('Usuario eliminado');load()}).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo eliminar')))}
onMounted(load)
</script>
