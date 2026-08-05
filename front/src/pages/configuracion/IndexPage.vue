<template>
  <q-page class="settings-page q-pa-md">
    <div class="settings-header q-mb-md">
      <div class="settings-header__icon"><q-icon name="tune" size="30px"/></div>
      <div>
        <div class="text-h5 text-weight-bold">Configuración</div>
        <div class="text-body2 settings-header__caption">Personaliza la identidad y los datos generales de Mundolac</div>
      </div>
    </div>

    <q-form @submit="save">
      <div class="row q-col-gutter-md">
        <div class="col-12 col-md-5">
          <q-card flat bordered class="settings-card full-height">
            <q-card-section>
              <div class="section-title"><q-icon name="image" color="primary"/><span>Identidad visual</span></div>
              <div class="text-caption text-grey-7 q-mb-md">Este logotipo identificará a la empresa dentro del sistema.</div>

              <div class="logo-preview">
                <img v-if="previewUrl" :src="previewUrl" alt="Vista previa del logotipo"/>
                <div v-else class="logo-placeholder">
                  <q-icon name="add_photo_alternate" size="54px"/>
                  <div class="text-weight-medium">Sin logotipo</div>
                  <div class="text-caption">Selecciona una imagen</div>
                </div>
                <q-badge v-if="logo" class="preview-badge" color="positive" rounded>
                  <q-icon name="check" class="q-mr-xs"/>Nueva imagen
                </q-badge>
              </div>

              <q-file
                v-model="logo"
                outlined
                accept=".jpg,.jpeg,.png,.webp,image/*"
                max-file-size="8388608"
                label="Seleccionar logotipo"
                class="q-mt-md"
                clearable
                @rejected="fileRejected"
              >
                <template #prepend><q-icon name="cloud_upload" color="primary"/></template>
                <template #append><q-icon v-if="logo" name="visibility" color="positive"/></template>
              </q-file>
              <div class="upload-help"><q-icon name="info" size="16px"/> JPG, PNG o WebP. Tamaño máximo: 8 MB.</div>
            </q-card-section>
          </q-card>
        </div>

        <div class="col-12 col-md-7">
          <q-card flat bordered class="settings-card">
            <q-card-section>
              <div class="section-title"><q-icon name="business" color="primary"/><span>Información de la empresa</span></div>
              <div class="text-caption text-grey-7 q-mb-md">Estos datos se utilizarán en comprobantes y reportes.</div>

              <div class="row q-col-gutter-md">
                <q-input v-model="form.nombre_empresa" outlined label="Nombre de la empresa *" class="col-12" :rules="[v=>!!v||'El nombre es requerido']">
                  <template #prepend><q-icon name="storefront"/></template>
                </q-input>
                <q-input v-model="form.nit" outlined label="NIT" class="col-12 col-sm-6">
                  <template #prepend><q-icon name="badge"/></template>
                </q-input>
                <q-input v-model="form.telefono" outlined label="Teléfono" class="col-12 col-sm-6">
                  <template #prepend><q-icon name="phone"/></template>
                </q-input>
                <q-input v-model="form.direccion" outlined label="Dirección" class="col-12">
                  <template #prepend><q-icon name="location_on"/></template>
                </q-input>
              </div>
            </q-card-section>

            <q-separator/>
            <q-card-section>
              <div class="section-title"><q-icon name="language" color="primary"/><span>Página web</span></div>
              <div class="text-caption text-grey-7 q-mb-md">Configura el título público y el único número de WhatsApp que recibirá los pedidos de la página web.</div>
              <div class="row q-col-gutter-md">
                <q-input v-model="form.titulo_web" outlined label="Título principal de la página *" class="col-12" :rules="[v=>!!v||'El título es requerido']">
                  <template #prepend><q-icon name="title"/></template>
                </q-input>
                <q-input v-model="form.whatsapp" outlined label="Número de WhatsApp *" hint="Incluye el código de país. Ejemplo: +591 73809946" class="col-12" :rules="[v=>!!v||'El número de WhatsApp es requerido',v=>/^\+?[0-9 ]+$/.test(v)||'Use solamente números, espacios y el signo +']">
                  <template #prepend><q-icon name="chat" color="positive"/></template>
                </q-input>
              </div>
            </q-card-section>

            <q-separator/>
            <q-card-section class="company-summary">
              <q-avatar rounded size="52px" color="blue-1" text-color="primary">
                <img v-if="previewUrl" :src="previewUrl" class="summary-logo"/>
                <q-icon v-else name="storefront"/>
              </q-avatar>
              <div class="q-ml-md">
                <div class="text-subtitle1 text-weight-bold">{{ form.nombre_empresa || 'Mundolac' }}</div>
                <div class="text-caption text-grey-7">{{ summaryLine }}</div>
              </div>
            </q-card-section>

            <q-card-actions align="right" class="q-pa-md">
              <q-btn flat no-caps label="Descartar cambios" color="grey-7" :disable="saving" @click="load"/>
              <q-btn unelevated no-caps color="primary" icon="save" label="Guardar configuración" type="submit" :loading="saving"/>
            </q-card-actions>
          </q-card>
        </div>
      </div>
    </q-form>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onBeforeUnmount, reactive, ref, watch } from 'vue'

const { proxy } = getCurrentInstance()
const form = reactive({ nombre_empresa: 'Mundolac', nit: '', telefono: '', direccion: '', logo: null, titulo_web: 'Productos para tu negocio al mejor precio', whatsapp: '+591 73809946' })
const logo = ref(null)
const localPreview = ref('')
const saving = ref(false)

const savedLogoUrl = computed(() => form.logo ? `${proxy.$imgBase}/images/${form.logo}` : '')
const previewUrl = computed(() => localPreview.value || savedLogoUrl.value)
const summaryLine = computed(() => [form.nit ? `NIT ${form.nit}` : '', form.telefono, form.direccion].filter(Boolean).join(' · ') || 'Completa los datos de tu empresa')

watch(logo, file => {
  if (localPreview.value) URL.revokeObjectURL(localPreview.value)
  localPreview.value = file ? URL.createObjectURL(file) : ''
})

function load () {
  logo.value = null
  proxy.$axios.get('/configuracion').then(({ data }) => {
    Object.assign(form, data)
    proxy.$store.setCompany(data)
  })
}

function fileRejected () {
  proxy.$alert.error('La imagen debe ser JPG, PNG o WebP y pesar menos de 8 MB')
}

async function save () {
  saving.value = true
  try {
    const { data } = await proxy.$axios.put('/configuracion', form)
    Object.assign(form, data)
    proxy.$store.setCompany(data)
    if (logo.value) {
      const body = new FormData()
      body.append('logo', logo.value)
      const response = await proxy.$axios.post('/configuracion/logo', body)
      Object.assign(form, response.data)
      proxy.$store.setCompany(response.data)
      logo.value = null
    }
    proxy.$alert.success('Configuración guardada correctamente')
  } catch (error) {
    proxy.$alert.error(Object.values(error.response?.data?.errors || {})[0]?.[0] || error.response?.data?.message || 'No se pudo guardar la configuración')
  } finally {
    saving.value = false
  }
}

onBeforeUnmount(() => {
  if (localPreview.value) URL.revokeObjectURL(localPreview.value)
})

load()
</script>

<style scoped>
.settings-page{max-width:1180px;margin:0 auto}.settings-header{display:flex;align-items:center;gap:14px;padding:18px 20px;border-radius:14px;color:#fff;background:linear-gradient(135deg,#1565c0,#0d47a1);box-shadow:0 8px 22px rgba(13,71,161,.18)}.settings-header__icon{width:54px;height:54px;display:flex;align-items:center;justify-content:center;border-radius:14px;background:rgba(255,255,255,.16)}.settings-header__caption{color:rgba(255,255,255,.82)}.settings-card{border-radius:14px;border-color:#dce5ed;overflow:hidden;box-shadow:0 4px 16px rgba(32,64,96,.05)}.section-title{display:flex;align-items:center;gap:8px;margin-bottom:3px;font-size:16px;font-weight:700;color:#263238}.logo-preview{position:relative;min-height:250px;display:flex;align-items:center;justify-content:center;padding:20px;border:2px dashed #b8c9d8;border-radius:12px;background:linear-gradient(145deg,#f8fbfd,#eef4f8);overflow:hidden}.logo-preview img{max-width:100%;max-height:220px;object-fit:contain;filter:drop-shadow(0 7px 10px rgba(0,0,0,.12))}.logo-placeholder{text-align:center;color:#90a4ae}.preview-badge{position:absolute;top:10px;right:10px;padding:6px 9px}.upload-help{display:flex;align-items:center;gap:5px;margin-top:8px;color:#78909c;font-size:11px}.company-summary{display:flex;align-items:center;background:#f8fafc}.summary-logo{width:100%;height:100%;object-fit:contain}@media(max-width:599px){.settings-page{padding:8px}.settings-header{padding:14px}.settings-header__icon{width:44px;height:44px}.logo-preview{min-height:200px}}
</style>
