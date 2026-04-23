<template>
  <q-page class="q-pa-md">
    <BaseSelect v-model="tipoSpedizione" label="Tipo di spedizione" :options="tipiSpedizioni" />
    <Creazione_configurazione v-model="configurazione" :tipoSpedizione="tipoSpedizione" />
  </q-page>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import Creazione_configurazione from 'components/creazione_configurazione.vue'
import BaseSelect from 'components/forms/BaseSelect.vue'
import { api } from 'boot/axios.js'

const tipiSpedizioni = ref([])
const tipoSpedizione = ref(null)
const configurazione = ref(null)

onMounted(() => {
  api.post('preleva_tipi_spedizione.php').then((response) => {
    tipiSpedizioni.value = response.data.spedizioni
  })
})

</script>

<style scoped></style>
