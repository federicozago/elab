const routes = [
  {
    path: '/',
    component: () => import('layouts/MainLayout.vue'),
    children: [
      { path: '', component: () => import('pages/IndexPage.vue') },
      {
        //path: '/scelta_elaborazione/:tipo',
        path: '/creazione_lavoro/',
        component: () => import('pages/creazione_lavoro.vue'),
        props: true,
      },
      {
        //path: '/scelta_elaborazione/:tipo',
        path: '/modifica_lavoro/',
        component: () => import('pages/creazione_lavoro.vue'),
        props: true,
      },
      {
        //path:'/creazione_config_elaborazione/:tipo',
        path: '/creazione_configurazione/',
        component: () => import('pages/creazione_configurazione.vue'),
        props: true,
      },
      {
        //path:'/creazione_config_elaborazione/:tipo',
        path: '/creazione_BaseDati/',
        component: () => import('pages/creazione_BaseDati.vue'),
        props: true,
      },
    ],
  },

  // Always leave this as last one,
  // but you can also remove it
  {
    path: '/:catchAll(.*)*',
    component: () => import('pages/ErrorNotFound.vue'),
  },
]

export default routes
