import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

// Numero massimo di eventi conservati in memoria per la sessione corrente.
const MAX_EVENTS = 50

export const useNotificationHistoryStore = defineStore('notificationHistory', () => {
  // Elenco eventi della sessione corrente. Si azzera automaticamente al refresh
  // della pagina perche' non e' persistito su storage.
  // Il piu' recente e' sempre in testa (index 0).
  const events = ref([])

  function addEvent(type, message) {
    events.value.unshift({
      id: Date.now() + '-' + Math.random().toString(36).slice(2),
      type, // 'positive' | 'negative'
      message,
      timestamp: new Date(),
    })

    // Manteniamo solo i MAX_EVENTS piu' recenti (i piu' vecchi vengono scartati).
    if (events.value.length > MAX_EVENTS) {
      events.value.splice(MAX_EVENTS)
    }
  }

  function clearEvents() {
    events.value = []
  }

  const eventCount = computed(() => events.value.length)

  return {
    events,
    eventCount,
    addEvent,
    clearEvents,
  }
})
