// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  ssr: false,  // SPA 模式（後台管理系統）

  devtools: { enabled: true },

  css: ['~/app/assets/css/main.css'],

  app: {
    head: {
      title: '進銷存管理系統',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
      ],
    },
  },

  modules: [
    '@pinia/nuxt',
    '@nuxtjs/tailwindcss',
    'shadcn-nuxt',
    '@nuxt/eslint',
  ],

  shadcn: {
    prefix: '',
    componentDir: './app/components/ui',
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost/api/v1',
    },
  },

  imports: {
    dirs: ['app/stores', 'app/composables'],
  },

  components: [
    { path: '~/app/components', pathPrefix: false },
  ],

  // Nuxt 3.13+ 新目錄結構：app/ 子目錄
  srcDir: '.',
  dir: {
    pages:      'app/pages',
    layouts:    'app/layouts',
    middleware: 'app/middleware',
    plugins:    'app/plugins',
    assets:     'app/assets',
    public:     'public',
  },

  typescript: {
    strict: true,
    typeCheck: false,
  },

  compatibilityDate: '2024-11-01',
})
