<template>
  <q-page class="q-pa-sm">
    <div class="row items-center q-mb-sm">
      <div>
        <div class="text-subtitle1 text-weight-bold">Reporte de productos vendidos</div>
        <div class="text-caption text-grey-7">Ranking entre fechas por cantidad, ingresos, ganancia y precios</div>
      </div>
      <q-space/>
      <q-btn dense unelevated color="positive" icon="table_view" label="Excel" no-caps :loading="downloading" @click="download"/>
    </div>

    <!-- ANÁLISIS RÁPIDO -->
    <q-card flat bordered class="q-mb-sm">
      <q-card-section class="q-pa-sm">
        <div class="text-caption text-weight-bold text-grey-8 q-mb-xs">ANÁLISIS RÁPIDO</div>
        <div class="row q-gutter-xs">
          <q-chip v-for="p in presets" :key="p.label" clickable dense :icon="p.icon"
                  :color="isPreset(p) ? 'primary' : 'grey-3'" :text-color="isPreset(p) ? 'white' : 'grey-9'"
                  @click="applyPreset(p)">{{ p.label }}</q-chip>
        </div>
      </q-card-section>
      <q-separator/>
      <q-card-section class="q-pa-sm">
        <div class="text-caption text-weight-bold text-grey-8 q-mb-xs">PERIODO</div>
        <div class="row q-col-gutter-sm items-center">
          <div class="col-12 col-md-5 row q-gutter-xs">
            <q-chip v-for="r in ranges" :key="r.label" clickable dense
                    :color="activeRange === r.label ? 'teal' : 'grey-3'" :text-color="activeRange === r.label ? 'white' : 'grey-9'"
                    @click="applyRange(r)">{{ r.label }}</q-chip>
          </div>
          <q-input v-model="filters.desde" dense outlined type="date" label="Desde" class="col-6 col-md-2" @update:model-value="activeRange = ''; load()"/>
          <q-input v-model="filters.hasta" dense outlined type="date" label="Hasta" class="col-6 col-md-2" @update:model-value="activeRange = ''; load()"/>
          <q-select v-model="filters.usuario" :options="usuarios" option-label="name" dense outlined clearable
                    label="Vendedor" class="col-12 col-md-3" @update:model-value="load"/>
        </div>
      </q-card-section>
      <q-separator/>
      <q-card-section class="q-pa-sm">
        <div class="row q-col-gutter-sm items-center">
          <q-input v-model="filters.q" dense outlined debounce="400" clearable placeholder="Buscar producto, código o código de barras"
                   class="col-12 col-md-4" @update:model-value="load"><template #prepend><q-icon name="search"/></template></q-input>
          <q-select v-model="filters.categoria" :options="categorias" option-label="nombre" dense outlined clearable
                    label="Categoría" class="col-12 col-md-3" @update:model-value="load"/>
          <q-select v-model="orden" :options="ordenes" option-label="label" option-value="value" emit-value map-options
                    dense outlined label="Ordenar por" class="col-8 col-md-3" @update:model-value="load"/>
          <div class="col-4 col-md-2">
            <q-btn-toggle v-model="direccion" dense unelevated no-caps spread toggle-color="primary" class="order-toggle"
                          :options="[{ value: 'desc', slot: 'desc' }, { value: 'asc', slot: 'asc' }]" @update:model-value="load">
              <template #desc><q-icon name="south"/><q-tooltip>Mayor a menor</q-tooltip></template>
              <template #asc><q-icon name="north"/><q-tooltip>Menor a mayor</q-tooltip></template>
            </q-btn-toggle>
          </div>
        </div>
        <q-expansion-item dense dense-toggle icon="tune" label="Filtros avanzados" header-class="text-primary text-caption q-pl-none q-mt-xs">
          <div class="row q-col-gutter-sm q-pt-sm">
            <q-input v-model.number="filters.cantidad_min" dense outlined type="number" min="0" label="Cantidad mínima" class="col-6 col-md-2" @update:model-value="loadDebounced"/>
            <q-input v-model.number="filters.cantidad_max" dense outlined type="number" min="0" label="Cantidad máxima" class="col-6 col-md-2" @update:model-value="loadDebounced"/>
            <q-input v-model.number="filters.precio_compra_min" dense outlined type="number" min="0" step="0.01" label="Precio compra desde" class="col-6 col-md-2" @update:model-value="loadDebounced"/>
            <q-input v-model.number="filters.precio_compra_max" dense outlined type="number" min="0" step="0.01" label="Precio compra hasta" class="col-6 col-md-2" @update:model-value="loadDebounced"/>
            <q-input v-model.number="filters.precio_venta_min" dense outlined type="number" min="0" step="0.01" label="Precio venta desde" class="col-6 col-md-2" @update:model-value="loadDebounced"/>
            <q-input v-model.number="filters.precio_venta_max" dense outlined type="number" min="0" step="0.01" label="Precio venta hasta" class="col-6 col-md-2" @update:model-value="loadDebounced"/>
            <div class="col-12 row items-center q-gutter-md">
              <q-toggle v-model="filters.incluir_sin_ventas" dense color="primary" label="Incluir productos sin ventas en el periodo" @update:model-value="load"/>
              <q-btn flat dense no-caps color="grey-8" icon="restart_alt" label="Limpiar filtros" @click="reset"/>
            </div>
          </div>
        </q-expansion-item>
      </q-card-section>
    </q-card>

    <!-- INDICADORES -->
    <div class="row q-col-gutter-sm q-mb-sm">
      <div v-for="card in cards" :key="card.label" class="col-6 col-md-3">
        <q-card flat bordered :class="`summary-card bg-${card.color}-1 text-${card.color}-9`">
          <q-card-section class="row items-center q-pa-sm">
            <q-avatar :color="card.color" text-color="white" :icon="card.icon" size="36px"/>
            <div class="q-ml-sm">
              <div class="text-caption">{{ card.label }}</div>
              <div class="text-h6 text-weight-bold">{{ card.money ? 'Bs ' : '' }}{{ card.money ? money(card.value) : number(card.value) }}</div>
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- GRÁFICO -->
    <q-card v-if="grafico.length" flat bordered class="q-mb-sm">
      <q-card-section class="q-pb-none">
        <div class="text-subtitle2 text-weight-bold">Top 10 · {{ chartLabel }}</div>
        <div class="text-caption text-grey-6">{{ periodoTexto }}</div>
      </q-card-section>
      <q-card-section class="q-pa-xs">
        <apexchart type="bar" :height="Math.max(220, grafico.length * 34)" :options="chartOptions" :series="chartSeries"/>
      </q-card-section>
    </q-card>

    <!-- TABLA -->
    <q-card flat bordered>
      <q-table dense flat :rows="rows" :columns="columns" row-key="id" :loading="loading"
               v-model:pagination="pagination" :rows-per-page-options="[10, 20, 50, 100, 0]" @request="onRequest">
        <template #body-cell-puesto="p">
          <q-td :props="p"><q-badge rounded :color="rankColor(p.rowIndex)" class="q-px-sm">{{ posicion(p.rowIndex) }}</q-badge></q-td>
        </template>
        <template #body-cell-nombre="p">
          <q-td :props="p">
            <div class="row items-center no-wrap">
              <q-avatar rounded size="34px" class="q-mr-sm bg-grey-2">
                <img v-if="p.row.foto" :src="`${$imgBase}/images/${p.row.foto}`" style="object-fit:cover" @error="$event.target.style.display = 'none'"/>
                <q-icon v-else name="inventory_2" color="grey-6" size="18px"/>
              </q-avatar>
              <div style="min-width:0">
                <div class="text-weight-medium ellipsis" style="max-width:260px">{{ p.row.nombre }}</div>
                <div class="text-caption text-grey-7">{{ p.row.codigo }} · {{ p.row.unidad }}</div>
              </div>
            </div>
          </q-td>
        </template>
        <template #body-cell-categoria="p">
          <q-td :props="p"><q-chip v-if="p.value" dense square :color="p.row.categoria_color || 'blue-grey'" text-color="white">{{ p.value }}</q-chip></q-td>
        </template>
        <template #body-cell-cantidad="p">
          <q-td :props="p"><b>{{ number(p.value) }}</b><div class="text-caption text-grey-7">{{ number(p.row.ventas) }} venta(s)</div></q-td>
        </template>
        <template #body-cell-margen="p">
          <q-td :props="p"><span :class="Number(p.value) >= 0 ? 'text-positive' : 'text-negative'">Bs {{ money(p.value) }}</span></q-td>
        </template>
        <template #body-cell-ganancia="p">
          <q-td :props="p"><b :class="Number(p.value) >= 0 ? 'text-positive' : 'text-negative'">Bs {{ money(p.value) }}</b></q-td>
        </template>
        <template #body-cell-stock="p">
          <q-td :props="p"><q-badge :color="Number(p.value) > 0 ? 'grey-7' : 'negative'">{{ number(p.value) }}</q-badge></q-td>
        </template>
        <template #body-cell-ultima_venta="p">
          <q-td :props="p">{{ p.value ? formatDate(p.value) : 'Sin ventas' }}</q-td>
        </template>
        <template #no-data>
          <div class="full-width column items-center q-pa-md text-grey-7">
            <q-icon name="query_stats" size="34px"/>
            <div class="q-mt-xs">No hay ventas con los filtros seleccionados</div>
          </div>
        </template>
      </q-table>
    </q-card>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, reactive, ref } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
const apexchart = VueApexCharts
const { proxy } = getCurrentInstance()

const rows = ref([]), grafico = ref([]), categorias = ref([]), usuarios = ref([])
const loading = ref(false), downloading = ref(false), activeRange = ref('Este mes')
const orden = ref('cantidad'), direccion = ref('desc')
const resumen = reactive({ productos: 0, cantidad: 0, total: 0, ganancia: 0, descuento: 0 })
const pagination = ref({ page: 1, rowsPerPage: 20, rowsNumber: 0, sortBy: 'cantidad', descending: true })
const filters = reactive({ q: '', desde: '', hasta: '', usuario: null, categoria: null, incluir_sin_ventas: false,
  cantidad_min: null, cantidad_max: null, precio_compra_min: null, precio_compra_max: null, precio_venta_min: null, precio_venta_max: null })

const money = v => Number(v || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const number = v => Number(v || 0).toLocaleString('es-BO')
const formatDate = v => v ? new Date(v).toLocaleString('es-BO') : ''
const iso = d => d.toISOString().substring(0, 10)

const ordenes = [
  { value: 'cantidad', label: 'Cantidad vendida' },
  { value: 'total', label: 'Total vendido (Bs)' },
  { value: 'ganancia', label: 'Ganancia (Bs)' },
  { value: 'ventas', label: 'Número de ventas' },
  { value: 'precio_venta', label: 'Precio de venta' },
  { value: 'precio_compra', label: 'Precio de compra' },
  { value: 'margen', label: 'Margen unitario' },
  { value: 'stock', label: 'Stock actual' },
  { value: 'ultima_venta', label: 'Última venta' },
  { value: 'nombre', label: 'Nombre del producto' }
]

const presets = [
  { label: 'Más vendidos', icon: 'local_fire_department', orden: 'cantidad', direccion: 'desc', sinVentas: false },
  { label: 'Menos vendidos', icon: 'trending_down', orden: 'cantidad', direccion: 'asc', sinVentas: true },
  { label: 'Mayor ingreso', icon: 'payments', orden: 'total', direccion: 'desc', sinVentas: false },
  { label: 'Mayor ganancia', icon: 'savings', orden: 'ganancia', direccion: 'desc', sinVentas: false },
  { label: 'Venta más cara', icon: 'arrow_upward', orden: 'precio_venta', direccion: 'desc', sinVentas: false },
  { label: 'Venta más barata', icon: 'arrow_downward', orden: 'precio_venta', direccion: 'asc', sinVentas: false },
  { label: 'Compra más cara', icon: 'shopping_cart', orden: 'precio_compra', direccion: 'desc', sinVentas: false },
  { label: 'Compra más barata', icon: 'shopping_cart_checkout', orden: 'precio_compra', direccion: 'asc', sinVentas: false }
]

const today = () => new Date()
const ranges = [
  { label: 'Hoy', from: () => today(), to: () => today() },
  { label: '7 días', from: () => new Date(Date.now() - 6 * 864e5), to: () => today() },
  { label: '30 días', from: () => new Date(Date.now() - 29 * 864e5), to: () => today() },
  { label: 'Este mes', from: () => new Date(today().getFullYear(), today().getMonth(), 1), to: () => today() },
  { label: 'Este año', from: () => new Date(today().getFullYear(), 0, 1), to: () => today() },
  { label: 'Todo', from: () => null, to: () => null }
]

const columns = [
  { name: 'puesto', label: '#', align: 'center' },
  { name: 'nombre', label: 'Producto', field: 'nombre', align: 'left', sortable: true },
  { name: 'categoria', label: 'Categoría', field: 'categoria', align: 'left' },
  { name: 'cantidad', label: 'Cantidad', field: 'cantidad', align: 'right', sortable: true },
  { name: 'precio_compra', label: 'P. compra', field: 'precio_compra', format: v => `Bs ${money(v)}`, align: 'right', sortable: true },
  { name: 'precio_venta', label: 'P. venta', field: 'precio_venta', format: v => `Bs ${money(v)}`, align: 'right', sortable: true },
  { name: 'margen', label: 'Margen', field: 'margen', align: 'right', sortable: true },
  { name: 'descuento', label: 'Descuento', field: 'descuento', format: v => `Bs ${money(v)}`, align: 'right' },
  { name: 'total', label: 'Total vendido', field: 'total', format: v => `Bs ${money(v)}`, align: 'right', sortable: true },
  { name: 'ganancia', label: 'Ganancia', field: 'ganancia', align: 'right', sortable: true },
  { name: 'stock', label: 'Stock', field: 'stock_inicial', align: 'center', sortable: true },
  { name: 'ultima_venta', label: 'Última venta', field: 'ultima_venta', align: 'left', sortable: true }
]

const cards = computed(() => [
  { label: 'Productos', value: resumen.productos, money: false, icon: 'inventory_2', color: 'primary' },
  { label: 'Unidades vendidas', value: resumen.cantidad, money: false, icon: 'local_shipping', color: 'deep-orange' },
  { label: 'Total vendido', value: resumen.total, money: true, icon: 'payments', color: 'green' },
  { label: 'Ganancia', value: resumen.ganancia, money: true, icon: 'trending_up', color: 'purple' }
])

const chartMetric = computed(() => ['total', 'ganancia'].includes(orden.value) ? orden.value : 'cantidad')
const chartLabel = computed(() => chartMetric.value === 'total' ? 'Total vendido' : chartMetric.value === 'ganancia' ? 'Ganancia' : 'Cantidad vendida')
const dayText = v => v ? v.substring(0, 10).split('-').reverse().join('/') : ''
const periodoTexto = computed(() => filters.desde || filters.hasta
  ? `Del ${dayText(filters.desde) || 'inicio'} al ${dayText(filters.hasta) || 'hoy'}`
  : 'Todo el historial de ventas')
const chartSeries = computed(() => [{ name: chartLabel.value, data: grafico.value.map(i => Number(i[chartMetric.value])) }])
const chartOptions = computed(() => ({
  chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'Roboto, sans-serif' },
  colors: ['#1976d2'], dataLabels: { enabled: false }, grid: { borderColor: '#edf0f2' },
  plotOptions: { bar: { borderRadius: 6, horizontal: true, barHeight: '62%' } },
  xaxis: { categories: grafico.value.map(i => i.nombre), labels: { formatter: v => chartMetric.value === 'cantidad' ? number(v) : `Bs ${Number(v).toFixed(0)}` } },
  yaxis: { labels: { maxWidth: 200 } },
  tooltip: { theme: 'light', y: { formatter: v => chartMetric.value === 'cantidad' ? number(v) : `Bs ${money(v)}` } }
}))

const posicion = index => (pagination.value.page - 1) * (pagination.value.rowsPerPage || 0) + index + 1
const rankColor = index => posicion(index) === 1 ? 'amber-8' : posicion(index) === 2 ? 'blue-grey-5' : posicion(index) === 3 ? 'brown-5' : 'grey-6'
const isPreset = p => orden.value === p.orden && direccion.value === p.direccion

function params () {
  return {
    q: filters.q || undefined,
    desde: filters.desde || undefined,
    hasta: filters.hasta || undefined,
    user_id: filters.usuario?.id,
    categoria_id: filters.categoria?.id,
    incluir_sin_ventas: filters.incluir_sin_ventas ? 1 : 0,
    cantidad_min: filters.cantidad_min ?? undefined,
    cantidad_max: filters.cantidad_max ?? undefined,
    precio_compra_min: filters.precio_compra_min ?? undefined,
    precio_compra_max: filters.precio_compra_max ?? undefined,
    precio_venta_min: filters.precio_venta_min ?? undefined,
    precio_venta_max: filters.precio_venta_max ?? undefined,
    orden: orden.value,
    direccion: direccion.value
  }
}

function onRequest ({ pagination: p }) {
  if (p.sortBy && (p.sortBy !== pagination.value.sortBy || p.descending !== pagination.value.descending)) {
    orden.value = p.sortBy
    direccion.value = p.descending ? 'desc' : 'asc'
  }
  loading.value = true
  proxy.$axios.get('/reportes/productos-vendidos', { params: { ...params(), page: p.page, per_page: p.rowsPerPage } })
    .then(r => {
      rows.value = r.data.productos.data
      grafico.value = r.data.grafico
      categorias.value = r.data.categorias
      usuarios.value = r.data.usuarios
      Object.assign(resumen, r.data.resumen)
      pagination.value = { ...p, sortBy: orden.value, descending: direccion.value === 'desc', rowsNumber: r.data.productos.total }
    })
    .catch(e => proxy.$alert.error(e.response?.data?.message || 'No se pudo cargar el reporte'))
    .finally(() => { loading.value = false })
}

function load () { onRequest({ pagination: { ...pagination.value, page: 1, sortBy: orden.value, descending: direccion.value === 'desc' } }) }

let timer = null
function loadDebounced () { clearTimeout(timer); timer = setTimeout(load, 500) }

function applyPreset (preset) {
  orden.value = preset.orden
  direccion.value = preset.direccion
  filters.incluir_sin_ventas = preset.sinVentas
  load()
}

function applyRange (range) {
  activeRange.value = range.label
  const from = range.from(), to = range.to()
  filters.desde = from ? iso(from) : ''
  filters.hasta = to ? iso(to) : ''
  load()
}

function reset () {
  Object.assign(filters, { q: '', usuario: null, categoria: null, incluir_sin_ventas: false, cantidad_min: null, cantidad_max: null,
    precio_compra_min: null, precio_compra_max: null, precio_venta_min: null, precio_venta_max: null })
  orden.value = 'cantidad'
  direccion.value = 'desc'
  applyRange(ranges.find(r => r.label === 'Este mes'))
}

async function download () {
  downloading.value = true
  try {
    const response = await proxy.$axios.get('/reportes/productos-vendidos/excel', { params: params(), responseType: 'blob' })
    const url = URL.createObjectURL(response.data), a = document.createElement('a')
    a.href = url
    a.download = 'productos_vendidos.xlsx'
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    proxy.$alert.error('No se pudo exportar el reporte')
  } finally {
    downloading.value = false
  }
}

applyRange(ranges.find(r => r.label === 'Este mes'))
</script>

<style scoped>
.summary-card { border-radius: 10px; }
.order-toggle { border: 1px solid #c9d2cd; border-radius: 4px; height: 40px; }
</style>
