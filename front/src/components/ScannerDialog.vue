<template>
  <q-dialog :model-value="modelValue" :maximized="$q.screen.lt.sm" transition-show="slide-up" transition-hide="slide-down" @update:model-value="v=>emit('update:modelValue',v)" @show="start" @hide="stop">
    <q-card class="scanner-card column no-wrap bg-black text-white">
      <q-bar class="bg-black text-white q-py-lg">
        <q-icon name="qr_code_scanner"/>
        <div class="text-subtitle1 text-weight-bold">{{title}}</div>
        <q-space/>
        <q-btn v-if="torchSupported" dense flat round :icon="torchOn?'flash_off':'flash_on'" @click="toggleTorch"/>
        <q-btn v-if="cameras.length>1" dense flat round icon="cameraswitch" @click="switchCamera"/>
        <q-btn dense flat round icon="close" v-close-popup/>
      </q-bar>
      <div class="scanner-view col">
        <div :id="elementId" class="scanner-reader"/>
        <div v-if="starting" class="scanner-overlay"><q-spinner size="42px"/><div class="q-mt-sm">Abriendo cámara…</div></div>
        <div v-else-if="error" class="scanner-overlay q-pa-md text-center"><q-icon name="no_photography" size="48px" color="negative"/><div class="q-mt-sm">{{error}}</div><q-btn class="q-mt-md" unelevated no-caps color="primary" icon="refresh" label="Reintentar" @click="restart"/></div>
      </div>
      <div class="bg-white text-dark">
        <slot/>
      </div>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { onBeforeUnmount, ref } from 'vue'
import { Html5Qrcode, Html5QrcodeSupportedFormats as F } from 'html5-qrcode'

const props = defineProps({ modelValue: Boolean, title: { type: String, default: 'Escanear producto' }, cooldown: { type: Number, default: 1500 } })
const emit = defineEmits(['update:modelValue', 'detected'])

const elementId = `scanner-${Math.random().toString(36).slice(2)}`
const starting = ref(false), error = ref(''), cameras = ref([]), cameraIndex = ref(-1), torchSupported = ref(false), torchOn = ref(false)
let scanner = null, lastCode = '', lastTime = 0

const formats = [F.QR_CODE, F.EAN_13, F.EAN_8, F.UPC_A, F.UPC_E, F.CODE_128, F.CODE_39, F.CODE_93, F.ITF, F.CODABAR, F.DATA_MATRIX]

function qrbox (w, h) {
  const width = Math.floor(Math.min(w * 0.85, 420))
  return { width, height: Math.floor(Math.min(h * 0.8, width * 0.7)) }
}

function onScan (text) {
  const code = String(text || '').trim()
  const now = Date.now()
  if (!code || (code === lastCode && now - lastTime < props.cooldown) || now - lastTime < 600) return
  lastCode = code
  lastTime = now
  emit('detected', code)
}

async function start () {
  error.value = ''
  starting.value = true
  torchOn.value = false
  try {
    if (!window.isSecureContext) throw new Error('La cámara solo funciona en HTTPS')
    scanner = new Html5Qrcode(elementId, { formatsToSupport: formats, verbose: false, experimentalFeatures: { useBarCodeDetectorIfSupported: true } })
    const camera = cameraIndex.value >= 0 && cameras.value[cameraIndex.value] ? cameras.value[cameraIndex.value].id : { facingMode: 'environment' }
    await scanner.start(camera, { fps: 12, qrbox, aspectRatio: 1.333 }, onScan, () => {})
    if (!cameras.value.length) Html5Qrcode.getCameras().then(list => { cameras.value = list || [] }).catch(() => {})
    try { torchSupported.value = !!scanner.getRunningTrackCameraCapabilities().torchFeature().isSupported() } catch { torchSupported.value = false }
  } catch (e) {
    const msg = String(e?.message || e || '')
    error.value = /NotAllowed|Permission/i.test(msg) ? 'Permiso de cámara denegado. Habilítalo en la configuración del navegador.'
      : /NotFound|Requested device not found/i.test(msg) ? 'No se encontró ninguna cámara.'
        : msg || 'No se pudo abrir la cámara'
    await stop()
  } finally {
    starting.value = false
  }
}

async function stop () {
  const s = scanner
  scanner = null
  if (!s) return
  try { if (s.isScanning) await s.stop() } catch { /* ya detenido */ }
  try { s.clear() } catch { /* sin elemento */ }
}

async function restart () { await stop(); await start() }

async function switchCamera () {
  if (!cameras.value.length) return
  cameraIndex.value = (cameraIndex.value + 1) % cameras.value.length
  await restart()
}

async function toggleTorch () {
  try { await scanner?.getRunningTrackCameraCapabilities().torchFeature().apply(!torchOn.value); torchOn.value = !torchOn.value } catch { torchSupported.value = false }
}

onBeforeUnmount(stop)
</script>

<style scoped>
.scanner-card{width:100%;max-width:520px}
.scanner-view{position:relative;min-height:300px;background:#000;display:flex;align-items:center;justify-content:center;overflow:hidden}
.scanner-reader{width:100%}
.scanner-reader :deep(video){width:100%!important;height:auto!important;display:block}
.scanner-overlay{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(0,0,0,.85)}
@media(max-width:599px){.scanner-card{max-width:none}}
</style>
