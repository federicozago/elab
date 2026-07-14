<template>
  <q-layout view="lHh Lpr lFf">
    <q-header elevated>
      <q-toolbar>
        <q-btn flat dense round icon="menu" aria-label="Menu" @click="toggleLeftDrawer" />

        <q-toolbar-title> Elab </q-toolbar-title>

        <q-btn flat dense round icon="notifications" aria-label="Cronologia notifiche" @click="showEventsDialog = true">
          <q-badge v-if="eventCount > 0" color="red" floating>{{ eventCount }}</q-badge>
        </q-btn>

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

    <q-dialog v-model="showEventsDialog">
      <q-card style="min-width: 400px; max-width: 90vw">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Cronologia notifiche (sessione corrente)</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>

        <q-card-section style="max-height: 60vh" class="scroll">
          <q-list separator v-if="events.length > 0">
            <q-item
              v-for="event in events"
              :key="event.id"
              :class="event.type === 'positive' ? 'bg-green-2' : 'bg-red-2'"
            >
              <q-item-section>
                <q-item-label>{{ event.message }}</q-item-label>
                <q-item-label caption>{{ formatTimestamp(event.timestamp) }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
          <div v-else class="text-grey text-center q-pa-md">Nessuna notifica in questa sessione.</div>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-layout>
</template>

<script setup>
import { ref } from 'vue'
import { storeToRefs } from 'pinia'
import EssentialLink from 'components/EssentialLink.vue'
import { useNotificationHistoryStore } from 'src/stores/notificationHistoryStore'
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

const notificationHistory = useNotificationHistoryStore()
const { events, eventCount } = storeToRefs(notificationHistory)

const showEventsDialog = ref(false)

function formatTimestamp(date) {
  return new Date(date).toLocaleTimeString()
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
