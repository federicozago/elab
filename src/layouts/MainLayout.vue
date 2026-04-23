<template>
  <q-layout view="lHh Lpr lFf">
    <q-header elevated>
      <q-toolbar>
        <q-btn flat dense round icon="menu" aria-label="Menu" @click="toggleLeftDrawer" />

        <q-toolbar-title> Quasar App </q-toolbar-title>

        <div>Quasar v{{ $q.version }}</div>
      </q-toolbar>
    </q-header>

    <q-drawer v-model="leftDrawerOpen" show-if-above bordered>
      <q-list>
        <q-item-label header> Essential Links </q-item-label>

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
    title: 'Configurazioni',
    caption: 'Lista e modifica configurazioni',
    icon: 'school',
    link: '/creazconfigurazioni',
  },
  {
    title: 'Github',
    caption: 'github.com/quasarframework',
    icon: 'code',
    link: 'https://github.com/quasarframework',
  },
  {
    title: 'Discord Chat Channel',
    caption: 'chat.quasar.dev',
    icon: 'chat',
    link: 'https://chat.quasar.dev',
  },
  {
    title: 'Forum',
    caption: 'forum.quasar.dev',
    icon: 'record_voice_over',
    link: 'https://forum.quasar.dev',
  },
  {
    title: 'Twitter',
    caption: '@quasarframework',
    icon: 'rss_feed',
    link: 'https://twitter.quasar.dev',
  },
  {
    title: 'Facebook',
    caption: '@QuasarFramework',
    icon: 'public',
    link: 'https://facebook.quasar.dev',
  },
  {
    title: 'Quasar Awesome',
    caption: 'Community Quasar projects',
    icon: 'favorite',
    link: 'https://awesome.quasar.dev',
  },
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
