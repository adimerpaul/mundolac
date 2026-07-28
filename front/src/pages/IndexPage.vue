<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-col-gutter-md">
      <div class="col-12">
        <q-card flat bordered class="q-pa-lg">
          <div class="row items-center q-gutter-md">
            <q-icon name="storefront" color="primary" size="56px" />
            <div>
              <div class="text-h5 text-weight-bold">Bienvenido a Mundolac</div>
              <div class="text-body2 text-grey-7">
                Hola {{ $store.user.name || $store.user.username }}, selecciona un módulo del menú lateral para comenzar.
              </div>
            </div>
          </div>
        </q-card>
      </div>

      <div class="col-12 col-sm-6" v-if="$store.hasPermission('Ver Usuarios')">
        <q-card flat bordered class="q-pa-md cursor-pointer" @click="$router.push('/usuarios')">
          <div class="row items-center q-gutter-sm">
            <q-icon name="people" color="teal" size="32px" />
            <div>
              <div class="text-subtitle1 text-weight-bold">Usuarios</div>
              <div class="text-caption text-grey-7">Cuentas con acceso al sistema</div>
            </div>
          </div>
        </q-card>
      </div>

      <div class="col-12 col-sm-6" v-if="$store.hasPermission('Ver Productos')">
        <q-card flat bordered class="q-pa-md cursor-pointer" @click="$router.push('/productos')">
          <div class="row items-center q-gutter-sm">
            <q-icon name="inventory_2" color="deep-orange" size="32px" />
            <div>
              <div class="text-subtitle1 text-weight-bold">Productos</div>
              <div class="text-caption text-grey-7">Catálogo de productos</div>
            </div>
          </div>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
//
</script>
