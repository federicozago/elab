<template>
  <q-layout view="lHh Lpr lFf">
    <q-header elevated>
      <q-toolbar>
        <q-btn flat dense round icon="menu" aria-label="Menu" @click="toggleLeftDrawer" />

        <q-toolbar-title> Elab </q-toolbar-title>

        <div>Quasar v{{ $q.version }}</div>
      </q-toolbar>
    </q-header>

    <q-drawer v-model="leftDrawerOpen" show-if-above bordered>
      <q-list>
        <q-item-label header> Opzioni </q-item-label>

        <EssentialLink v-for="link in linksList" :key="link.title" v-bind="link" />
      </q-list>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { ref } from 'vue'
import EssentialLink from 'components/EssentialLink.vue'
//import { api } from 'boot/axios.js'

const linksList = [
  {
    title: 'Home',
    caption: 'Pagina iniziale',
    icon: 'home',
    link: '/',
  },
  {
    title: 'Lavori',
    caption: 'Lista e modifica lavori',
    icon: 'work',
    link: '/modifica_lavoro',
  },
  {
    title: 'Configurazioni postali',
    caption: 'Lista e modifica configurazioni',
    icon: 'local_post_office',
    link: '/creazione_configurazione',
  },
  {
    title: 'Basi dati',
    caption: 'Lista basi dati',
    icon: 'storage',
    link: '/creazione_BaseDati',
  }
]

const leftDrawerOpen = ref(false)

function toggleLeftDrawer() {
  leftDrawerOpen.value = !leftDrawerOpen.value
}

/*api.get("test.php")
  .then(response => {console.log(response)})
  .catch(error => {
    if (error.response) {
      // La richiesta è stata effettuata e il server ha risposto con un codice di stato
      console.error('Errore di risposta:', error.response.data);
      console.error('Status:', error.response.status);
    } else if (error.request) {
      // La richiesta è stata effettuata ma non è stata ricevuta alcuna risposta
      console.error('Nessuna risposta ricevuta:', error.request);
    } else {
      // Si è verificato un errore durante l'impostazione della richiesta
      console.error('Errore:', error.message);
    }
  });*/
</script>
