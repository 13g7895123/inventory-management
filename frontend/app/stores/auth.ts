// app/stores/auth.ts
import { defineStore } from 'pinia'
import type { AuthTokens, AuthUser, LoginPayload, ApiResponse } from '~/app/types/api'

const ACCESS_TOKEN_KEY  = 'ims_access_token'
const REFRESH_TOKEN_KEY = 'ims_refresh_token'

interface AuthState {
  accessToken:  string | null
  refreshToken: string | null
  user:         AuthUser | null
  loading:      boolean
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    accessToken:  import.meta.client ? localStorage.getItem(ACCESS_TOKEN_KEY)  : null,
    refreshToken: import.meta.client ? localStorage.getItem(REFRESH_TOKEN_KEY) : null,
    user:         null,
    loading:      false,
  }),

  getters: {
    isAuthenticated: (state) => !! state.accessToken,

    hasPermission: (state) => (permission: string): boolean => {
      return state.user?.permissions?.includes(permission) ?? false
    },
  },

  actions: {
    async login(payload: LoginPayload): Promise<void> {
      this.loading = true

      try {
        const config  = useRuntimeConfig()
        const baseURL = config.public.apiBase as string

        const res = await $fetch<ApiResponse<AuthTokens>>(`${baseURL}/auth/login`, {
          method: 'POST',
          body:   JSON.stringify(payload),
          headers: { 'Content-Type': 'application/json' },
        })

        if (! res.success || ! res.data) {
          throw new Error(res.message || '登入失敗')
        }

        this._persistTokens(res.data)
        await this.fetchMe()
      } finally {
        this.loading = false
      }
    },

    async fetchMe(): Promise<void> {
      if (! this.accessToken) return

      const config  = useRuntimeConfig()
      const baseURL = config.public.apiBase as string

      const res = await $fetch<ApiResponse<AuthUser>>(`${baseURL}/auth/me`, {
        headers: { Authorization: `Bearer ${this.accessToken}` },
      })

      if (res.success && res.data) {
        this.user = res.data
      }
    },

    async refreshToken(): Promise<boolean> {
      if (! this.refreshToken) return false

      try {
        const config  = useRuntimeConfig()
        const baseURL = config.public.apiBase as string

        const res = await $fetch<ApiResponse<AuthTokens>>(`${baseURL}/auth/refresh`, {
          method: 'POST',
          body:   JSON.stringify({ refresh_token: this.refreshToken }),
          headers: { 'Content-Type': 'application/json' },
        })

        if (res.success && res.data) {
          this._persistTokens(res.data)
          return true
        }
      } catch {
        // refresh 失敗直接登出
      }

      return false
    },

    async logout(): Promise<void> {
      this.accessToken  = null
      this.refreshToken = null
      this.user         = null

      if (import.meta.client) {
        localStorage.removeItem(ACCESS_TOKEN_KEY)
        localStorage.removeItem(REFRESH_TOKEN_KEY)
      }
    },

    _persistTokens(tokens: AuthTokens): void {
      this.accessToken  = tokens.access_token
      this.refreshToken = tokens.refresh_token

      if (import.meta.client) {
        localStorage.setItem(ACCESS_TOKEN_KEY,  tokens.access_token)
        localStorage.setItem(REFRESH_TOKEN_KEY, tokens.refresh_token)
      }
    },
  },
})
