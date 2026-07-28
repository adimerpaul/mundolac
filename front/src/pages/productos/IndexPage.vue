<template>
  <q-page padding>
    <div class="row items-center q-mb-md">
      <div>
        <div class="text-h5 text-weight-bold">Productos</div>
        <div class="text-grey-7">Inventario inicial y precios</div>
      </div>
      <q-space />
      <q-btn v-if="can('Crear Productos')" color="primary" icon="add" label="Nuevo producto" no-caps @click="openForm()" />
    </div>

    <q-card flat bordered>
      <q-card-section class="row q-col-gutter-sm">
        <q-input v-model="search" outlined dense debounce="350" class="col-12 col-md-5"
                 placeholder="Buscar por código, producto o categoría" clearable @update:model-value="load">
          <template #prepend><q-icon name="search" /></template>
        </q-input>
        <q-select v-model="category" :options="catalogs.categorias" outlined dense clearable
                  label="Categoría" class="col-12 col-md-3" @update:model-value="load" />
      </q-card-section>
      <q-table flat :rows="rows" :columns="columns" row-key="id" :loading="loading"
               v-model:pagination="pagination" @request="onRequest" binary-state-sort>
        <template #body-cell-precio_compra="p"><q-td :props="p">Bs {{ money(p.value) }}</q-td></template>
        <template #body-cell-precio_venta="p"><q-td :props="p">Bs {{ money(p.value) }}</q-td></template>
        <template #body-cell-stock_inicial="p">
          <q-td :props="p"><q-badge :color="p.value > 10 ? 'positive' : 'orange'" :label="p.value" /></q-td>
        </template>
        <template #body-cell-actions="p">
          <q-td :props="p">
            <q-btn v-if="can('Editar Productos')" flat round dense icon="edit" color="primary" @click="openForm(p.row)" />
            <q-btn v-if="can('Eliminar Productos')" flat round dense icon="delete" color="negative" @click="remove(p.row)" />
          </q-td>
        </template>
      </q-table>
    </q-card>

    <q-dialog v-model="dialog" persistent>
      <q-card style="width:700px;max-width:95vw">
        <q-form @submit="save">
          <q-card-section class="row items-center">
            <div class="text-h6">{{ form.id ? 'Editar producto' : 'Nuevo producto' }}</div>
            <q-space /><q-btn flat round dense icon="close" v-close-popup />
          </q-card-section>
          <q-separator />
          <q-card-section class="row q-col-gutter-md">
            <q-input v-model="form.codigo" outlined dense label="Código *" class="col-12 col-sm-4" :rules="[required]" />
            <q-input v-model="form.nombre" outlined dense label="Producto *" class="col-12 col-sm-8" :rules="[required]" />
            <q-input v-model="form.categoria" outlined dense label="Categoría" class="col-12 col-sm-6">
              <template #append><q-icon name="category" /></template>
            </q-input>
            <q-select v-model="form.unidad" :options="unitOptions" use-input new-value-mode="add-unique"
                      outlined dense label="Unidad *" class="col-12 col-sm-6" :rules="[required]" />
            <q-input v-model.number="form.precio_compra" type="number" step="0.01" min="0"
                     outlined dense label="Precio compra *" prefix="Bs" class="col-12 col-sm-4" :rules="[nonNegative]" />
            <q-input v-model.number="form.precio_venta" type="number" step="0.01" min="0"
                     outlined dense label="Precio venta *" prefix="Bs" class="col-12 col-sm-4" :rules="[nonNegative]" />
            <q-input v-model.number="form.stock_inicial" type="number" min="0"
                     outlined dense label="Stock inicial *" class="col-12 col-sm-4" :rules="[nonNegative]" />
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat label="Cancelar" no-caps v-close-popup />
            <q-btn color="primary" label="Guardar" no-caps type="submit" :loading="saving" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { getCurrentInstance, onMounted, reactive, ref } from 'vue'
const { proxy } = getCurrentInstance()
const rows = ref([]), loading = ref(false), saving = ref(false), dialog = ref(false)
const search = ref(''), category = ref(null)
const catalogs = reactive({ categorias: [], unidades: [] })
const unitOptions = ref(['GR', 'KG', 'ML', 'LT', 'UNIDAD'])
const pagination = ref({ page: 1, rowsPerPage: 20, rowsNumber: 0 })
const empty = () => ({ id: null, codigo: '', nombre: '', categoria: 'SIN CATEGORÍA', unidad: 'UNIDAD', precio_compra: 0, precio_venta: 0, stock_inicial: 0 })
const form = reactive(empty())
const columns = [
  { name:'codigo', label:'Código', field:'codigo', align:'left' },
  { name:'nombre', label:'Producto', field:'nombre', align:'left' },
  { name:'categoria', label:'Categoría', field:'categoria', align:'left' },
  { name:'unidad', label:'Unidad', field:'unidad', align:'center' },
  { name:'precio_compra', label:'P. compra', field:'precio_compra', align:'right' },
  { name:'precio_venta', label:'P. venta', field:'precio_venta', align:'right' },
  { name:'stock_inicial', label:'Stock inicial', field:'stock_inicial', align:'center' },
  { name:'actions', label:'Acciones', align:'right' }
]
const can = p => proxy.$store.hasPermission(p)
const required = v => (v !== null && v !== '') || 'Campo requerido'
const nonNegative = v => Number(v) >= 0 || 'Debe ser mayor o igual a cero'
const money = v => Number(v || 0).toFixed(2)
function load () { onRequest({ pagination: pagination.value }) }
function onRequest ({ pagination: p }) {
  loading.value = true
  proxy.$axios.get('/productos', { params:{ q:search.value, categoria:category.value, page:p.page, per_page:p.rowsPerPage } })
    .then(({ data }) => { rows.value=data.data; pagination.value={ ...p, rowsNumber:data.total } })
    .catch(e => proxy.$alert.error(e.response?.data?.message || 'No se pudieron cargar los productos'))
    .finally(() => { loading.value=false })
}
function loadCatalogs () {
  proxy.$axios.get('/productos-catalogos').then(({data}) => {
    catalogs.categorias=data.categorias
    unitOptions.value=[...new Set([...unitOptions.value, ...data.unidades])]
  })
}
function openForm (row=null) { Object.assign(form, empty(), row || {}); dialog.value=true }
function save () {
  saving.value=true
  const request=form.id ? proxy.$axios.put(`/productos/${form.id}`, form) : proxy.$axios.post('/productos', form)
  request.then(() => { proxy.$alert.success('Producto guardado'); dialog.value=false; load(); loadCatalogs() })
    .catch(e => proxy.$alert.error(Object.values(e.response?.data?.errors || {})[0]?.[0] || e.response?.data?.message || 'No se pudo guardar'))
    .finally(() => { saving.value=false })
}
function remove (row) {
  proxy.$alert.dialog(`¿Eliminar ${row.nombre}?`).onOk(() => proxy.$axios.delete(`/productos/${row.id}`)
    .then(() => { proxy.$alert.success('Producto eliminado'); load() })
    .catch(e => proxy.$alert.error(e.response?.data?.message || 'No se pudo eliminar')))
}
onMounted(() => { load(); loadCatalogs() })
</script>
