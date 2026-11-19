<template>
  <q-page class="dashboard q-pa-sm">
    <div class="hero q-mb-sm">
      <div><div class="text-h5 text-weight-bold">Hola, {{ $store.user.name || $store.user.username }}</div><div class="text-body2 hero-subtitle">Así está funcionando Mundolac</div></div>
      <q-space/>
      <div v-if="$store.hasPermission('Ver Panel')" class="filter-bar">
        <q-btn-toggle v-model="period" dense unelevated no-caps toggle-color="white" toggle-text-color="primary" color="transparent" text-color="white" class="period-toggle" :options="periodOptions" @update:model-value="load"/>
        <template v-if="period==='rango'"><input v-model="from" type="date" class="date-input" @change="load"/><span>—</span><input v-model="to" type="date" class="date-input" @change="load"/></template>
      </div>
      <q-btn v-if="$store.hasPermission('Crear Ventas')" unelevated dense color="white" text-color="primary" icon="point_of_sale" label="Nueva venta" no-caps to="/ventas/nueva" class="q-ml-sm"/>
    </div>

    <template v-if="$store.hasPermission('Ver Panel')">
      <div class="kpi-grid q-mb-sm">
        <q-card v-for="item in kpis" :key="item.label" flat bordered class="kpi-card" :class="{'kpi-highlight':item.highlight}">
          <q-card-section class="row items-center q-pa-sm">
            <q-avatar :class="`kpi-icon kpi-${item.color}`" :icon="item.icon" size="44px"/>
            <div class="q-ml-sm"><div class="text-caption text-grey-7">{{item.label}}</div><div class="text-h6 text-weight-bold">{{item.money?'Bs ':''}}{{item.money?money(item.value):item.value}}</div><div class="text-caption" :class="`text-${item.color}`">{{item.caption}}</div></div>
          </q-card-section>
        </q-card>
      </div>

      <div class="row q-col-gutter-sm q-mb-sm">
        <div class="col-12 col-lg-8"><q-card flat bordered class="chart-card"><q-card-section class="row items-center q-pb-none"><div><div class="text-subtitle1 text-weight-bold">Ventas {{groupLabel}} · {{periodLabel}}</div><div class="text-caption text-grey-6">Solo ventas completadas (sin anuladas)</div></div><q-space/><q-btn-toggle v-model="salesType" dense flat no-caps size="sm" toggle-color="primary" color="grey-7" :options="chartTypes" @update:model-value="saveChartTypes"/></q-card-section><q-card-section class="q-pa-xs"><apexchart :key="salesType" :type="salesType" height="285" :options="dailyOptions" :series="dailySeries"/></q-card-section></q-card></div>
        <div class="col-12 col-lg-4"><q-card flat bordered class="chart-card"><q-card-section class="q-pb-none"><div class="text-subtitle1 text-weight-bold">Formas de pago</div><div class="text-caption text-grey-6">Distribución del total vendido</div></q-card-section><q-card-section class="q-pa-xs"><apexchart type="donut" height="285" :options="paymentOptions" :series="paymentSeries"/></q-card-section></q-card></div>
      </div>

      <div v-if="canProfit" class="row q-col-gutter-sm q-mb-sm">
        <div class="col-12"><q-card flat bordered class="chart-card"><q-card-section class="row items-center q-pb-none"><div><div class="text-subtitle1 text-weight-bold">Ganancia {{groupLabel}} · {{periodLabel}}</div><div class="text-caption text-grey-6">Venta − costo − descuento</div></div><q-space/><div class="text-h6 text-weight-bold text-positive q-mr-sm">Bs {{money(data.indicadores.ganancia)}}</div><q-btn-toggle v-model="profitType" dense flat no-caps size="sm" toggle-color="primary" color="grey-7" :options="chartTypes" @update:model-value="saveChartTypes"/></q-card-section><q-card-section class="q-pa-xs"><apexchart :key="profitType" :type="profitType" height="260" :options="profitOptions" :series="profitSeries"/></q-card-section></q-card></div>
      </div>

      <div class="row q-col-gutter-sm">
        <div class="col-12 col-lg-7"><q-card flat bordered class="chart-card"><q-card-section class="q-pb-none"><div class="text-subtitle1 text-weight-bold">Ventas por usuario</div><div class="text-caption text-grey-6">Total vendido por cada cajero</div></q-card-section><q-card-section class="q-pa-xs"><apexchart type="bar" height="310" :options="userOptions" :series="userSeries"/></q-card-section></q-card></div>
        <div class="col-12 col-lg-5"><q-card flat bordered class="chart-card"><q-card-section class="row items-center q-py-sm"><div><div class="text-subtitle1 text-weight-bold">Productos más vendidos</div><div class="text-caption text-grey-6">Por cantidad de unidades</div></div><q-space/><q-btn dense flat icon="inventory_2" color="primary" to="/productos"/></q-card-section><q-separator/>
          <q-list separator class="top-list"><q-item v-for="(product,index) in data.productos_top" :key="product.producto_id" dense>
            <q-item-section avatar><div class="rank">{{index+1}}</div><q-avatar rounded size="42px" color="grey-2"><img v-if="product.foto" :src="photoUrl(product.foto)"/><q-icon v-else name="inventory_2" color="grey-5"/></q-avatar></q-item-section>
            <q-item-section><q-item-label lines="1" class="text-weight-medium">{{product.nombre}}</q-item-label><q-item-label caption>Bs {{money(product.total)}} vendidos</q-item-label></q-item-section>
            <q-item-section side><q-badge color="primary" :label="`${product.cantidad} uds.`"/></q-item-section>
          </q-item><q-item v-if="!data.productos_top.length"><q-item-section class="text-center text-grey-6 q-py-lg">Todavía no hay productos vendidos</q-item-section></q-item></q-list>
        </q-card></div>
      </div>
    </template>
    <q-card v-else flat bordered class="q-pa-lg text-center"><q-icon name="dashboard" size="60px" color="primary"/><div class="text-h6">Bienvenido a Mundolac</div><div class="text-grey-7">Selecciona una opción del menú lateral.</div></q-card>
  </q-page>
</template>

<script setup>
import { computed, getCurrentInstance, onMounted, reactive, ref } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
const apexchart=VueApexCharts
const {proxy}=getCurrentInstance()
const data=reactive({indicadores:{ventas:0,ganancia:0,productos:0,cantidad_ventas:0,ticket_promedio:0},diario:[],agrupado:'dia',usuarios:[],pagos:[],productos_top:[]})
const money=v=>Number(v||0).toLocaleString('es-BO',{minimumFractionDigits:2,maximumFractionDigits:2})
const photoUrl=path=>`${proxy.$imgBase}/images/${path}`
const ymd=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
const today=()=>new Date(),period=ref('hoy'),from=ref(ymd(today())),to=ref(ymd(today()))
const periodOptions=[{label:'Hoy',value:'hoy'},{label:'Ayer',value:'ayer'},{label:'Mes',value:'mes'},{label:'Rango',value:'rango'},{label:'Todo',value:'todo'}]
const canProfit=computed(()=>proxy.$store.hasPermission('Ver Ganancias'))
function rangeParams(){const d=today()
  if(period.value==='hoy')return{desde:ymd(d),hasta:ymd(d)}
  if(period.value==='ayer'){d.setDate(d.getDate()-1);return{desde:ymd(d),hasta:ymd(d)}}
  if(period.value==='mes')return{desde:ymd(new Date(d.getFullYear(),d.getMonth(),1)),hasta:ymd(d)}
  if(period.value==='rango')return{desde:from.value||undefined,hasta:to.value||undefined}
  return{}}
const periodLabel=computed(()=>{const r=rangeParams(),f=v=>v.split('-').reverse().join('/')
  if(period.value==='todo')return'últimos 7 días'
  return r.desde===r.hasta&&r.desde?f(r.desde):`${r.desde?f(r.desde):'…'} al ${r.hasta?f(r.hasta):'…'}`})
const kpis=computed(()=>[
  ...(canProfit.value&&data.indicadores.ganancia!==null?[{label:'Ganancia estimada',value:data.indicadores.ganancia,money:true,icon:'trending_up',color:'positive',caption:`Margen ${data.indicadores.ventas?(data.indicadores.ganancia/data.indicadores.ventas*100).toFixed(1):'0.0'}% · ventas − costo − desc.`,highlight:true}]:[]),
  {label:'Ventas totales',value:data.indicadores.ventas,money:true,icon:'payments',color:'primary',caption:`${data.indicadores.cantidad_ventas} ventas`},
  {label:'Productos vendidos',value:data.indicadores.productos,money:false,icon:'inventory_2',color:'deep-orange',caption:'Unidades acumuladas'},
  {label:'Ticket promedio',value:data.indicadores.ticket_promedio,money:true,icon:'receipt_long',color:'purple',caption:'Promedio por venta'}
])
const baseChart={chart:{toolbar:{show:false},fontFamily:'Roboto, sans-serif'},dataLabels:{enabled:false},grid:{borderColor:'#edf0f2'},tooltip:{theme:'light'}}
const chartTypes=[{icon:'landscape',value:'area',attrs:{title:'Montaña'}},{icon:'bar_chart',value:'bar',attrs:{title:'Histograma'}}]
const storedTypes=(()=>{try{return JSON.parse(localStorage.getItem('chartTypesMundolac'))||{}}catch{return{}}})()
const salesType=ref(storedTypes.ventas||'area'),profitType=ref(storedTypes.ganancia||'bar')
function saveChartTypes(){try{localStorage.setItem('chartTypesMundolac',JSON.stringify({ventas:salesType.value,ganancia:profitType.value}))}catch{/* sin almacenamiento */}}
const areaStyle={stroke:{curve:'smooth',width:3},fill:{type:'gradient',gradient:{shadeIntensity:1,opacityFrom:.4,opacityTo:.05}}}
const barStyle={stroke:{width:0},fill:{type:'solid',opacity:1},plotOptions:{bar:{borderRadius:4,columnWidth:'60%'}}}
const groupLabel=computed(()=>({hora:'por hora',dia:'por día',mes:'por mes'})[data.agrupado]||'')
const profitSeries=computed(()=>[{name:'Ganancia',data:data.diario.map(i=>Number(i.ganancia||0))}])
const profitOptions=computed(()=>({...baseChart,...(profitType.value==='bar'?barStyle:areaStyle),chart:{...baseChart.chart,type:profitType.value},colors:[profitType.value==='bar'?({value})=>value<0?'#c10015':'#21ba45':'#21ba45'],xaxis:{categories:data.diario.map(i=>i.label)},yaxis:{labels:{formatter:v=>`Bs ${Number(v).toFixed(0)}`}},tooltip:{y:{formatter:v=>`Bs ${money(v)}`}}}))
const dailySeries=computed(()=>[{name:'Ventas',data:data.diario.map(i=>i.total)}])
const dailyOptions=computed(()=>({...baseChart,...(salesType.value==='bar'?barStyle:areaStyle),chart:{...baseChart.chart,type:salesType.value},colors:['#1976d2'],xaxis:{categories:data.diario.map(i=>i.label)},yaxis:{labels:{formatter:v=>`Bs ${Number(v).toFixed(0)}`}},tooltip:{y:{formatter:v=>`Bs ${money(v)}`}}}))
const paymentSeries=computed(()=>data.pagos.map(i=>Number(i.total)))
const paymentOptions=computed(()=>({...baseChart,labels:data.pagos.map(i=>i.nombre),colors:['#21ba45','#2196f3','#9c27b0'],legend:{position:'bottom'},plotOptions:{pie:{donut:{size:'66%',labels:{show:true,total:{show:true,label:'Total',formatter:()=>`Bs ${money(data.indicadores.ventas)}`}}}}}}))
const userSeries=computed(()=>[{name:'Total vendido',data:data.usuarios.map(i=>Number(i.total))}])
const userOptions=computed(()=>({...baseChart,chart:{...baseChart.chart,type:'bar'},colors:['#26a69a'],plotOptions:{bar:{borderRadius:6,horizontal:true,barHeight:'58%'}},xaxis:{categories:data.usuarios.map(i=>i.nombre),labels:{formatter:v=>`Bs ${Number(v).toFixed(0)}`}}}))
function load(){if(!proxy.$store.hasPermission('Ver Panel'))return
  const r=rangeParams();if(r.desde&&r.hasta&&r.desde>r.hasta)return proxy.$alert.warning('La fecha inicial no puede ser mayor a la final')
  proxy.$axios.get('/dashboard',{params:r}).then(res=>Object.assign(data,res.data)).catch(e=>proxy.$alert.error(e.response?.data?.message||'No se pudo cargar el panel'))}
onMounted(load)
</script>

<style scoped>
.dashboard{background:linear-gradient(180deg,#f4f8fb 0,#f7f8f8 260px)}.hero{display:flex;align-items:center;padding:18px 22px;border-radius:14px;color:#fff;background:linear-gradient(120deg,#0d47a1,#1976d2 60%,#42a5f5);box-shadow:0 8px 24px rgba(21,101,192,.22)}.hero-subtitle{color:rgba(255,255,255,.82)}
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px}
.hero{flex-wrap:wrap;gap:8px;padding:12px 18px}.filter-bar{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.period-toggle{border:1px solid rgba(255,255,255,.6);border-radius:8px;overflow:hidden}.period-toggle :deep(.q-btn){padding:2px 10px;font-weight:600}
.kpi-highlight{border:2px solid #21ba45;background:#f1fbf4}.kpi-highlight .text-h6{font-size:1.5rem;color:#1b8f4d}
.date-input{border:none;border-radius:6px;padding:3px 6px;font:inherit;font-size:13px;color:#0d47a1;height:28px}.kpi-card,.chart-card{border-radius:12px;background:rgba(255,255,255,.96)}.kpi-icon{color:#fff}.kpi-primary{background:linear-gradient(135deg,#1976d2,#42a5f5)}.kpi-positive{background:linear-gradient(135deg,#1b8f4d,#4caf50)}.kpi-deep-orange{background:linear-gradient(135deg,#e65100,#ff9800)}.kpi-purple{background:linear-gradient(135deg,#6a1b9a,#ab47bc)}
.top-list{max-height:310px;overflow:auto}.rank{position:absolute;margin-left:-7px;margin-top:-5px;width:18px;height:18px;border-radius:50%;background:#1976d2;color:white;font-size:10px;display:flex;align-items:center;justify-content:center;z-index:1}
@media(max-width:600px){.hero{padding:10px}.hero>.q-btn{display:none}.filter-bar{width:100%}.kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.kpi-card .text-h6{font-size:1.05rem}}
</style>
