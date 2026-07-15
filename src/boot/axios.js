import { defineBoot } from '#q-app/wrappers'
import axios from 'axios'

// Be careful when using SSR for cross-request state pollution
// due to creating a Singleton instance here;
// If any client changes this (global) instance, it might be a
// good idea to move this instance creation inside of the
// "export default () => {}" function below (which runs individually
// for each client)
// baseURL '/api/' sia in dev che in produzione:
// - in produzione la SPA e il backend sono serviti dalla stessa origine, con il
//   backend sotto /api (vedi Dockerfile / apache-vhost.conf);
// - in sviluppo il dev-server Quasar (porta 9000) inoltra /api al backend Docker
//   su :9001 tramite il proxy in quasar.config.js (nessuna chiamata cross-origin,
//   quindi nessun problema di CORS).
const api = axios.create({
  baseURL: '/api/',
})

export default defineBoot(({ app }) => {
  // for use inside Vue files (Options API) through this.$axios and this.$api

  app.config.globalProperties.$axios = axios
  // ^ ^ ^ this will allow you to use this.$axios (for Vue Options API form)
  //       so you won't necessarily have to import axios in each vue file

  app.config.globalProperties.$api = api
  // ^ ^ ^ this will allow you to use this.$api (for Vue Options API form)
  //       so you can easily perform requests against your app's API
})

export { api }
