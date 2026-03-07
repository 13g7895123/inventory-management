// app/composables/useApi.ts
// 統一 API 呼叫 Composable，包裝 $fetch 並自動帶入 JWT

import type { ApiResponse, PaginatedResponse } from '~/app/types/api'

export function useApi() {
  const config    = useRuntimeConfig()
  const authStore = useAuthStore()

  const baseURL = config.public.apiBase as string

  /**
   * 取得請求標頭（自動帶入 Authorization）
   */
  function getHeaders(): Record<string, string> {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept':       'application/json',
    }

    if (authStore.accessToken) {
      headers['Authorization'] = `Bearer ${authStore.accessToken}`
    }

    return headers
  }

  /**
   * 通用 GET 請求
   */
  async function get<T>(path: string, query?: Record<string, unknown>): Promise<ApiResponse<T>> {
    return $fetch<ApiResponse<T>>(`${baseURL}${path}`, {
      method:  'GET',
      headers: getHeaders(),
      query,
      onResponseError: handleError,
    })
  }

  /**
   * 通用 GET 分頁請求
   */
  async function getPaginated<T>(path: string, params?: Record<string, unknown>): Promise<PaginatedResponse<T>> {
    return $fetch<PaginatedResponse<T>>(`${baseURL}${path}`, {
      method:  'GET',
      headers: getHeaders(),
      query:   params,
      onResponseError: handleError,
    })
  }

  /**
   * 通用 POST 請求
   */
  async function post<T>(path: string, body?: unknown): Promise<ApiResponse<T>> {
    return $fetch<ApiResponse<T>>(`${baseURL}${path}`, {
      method:  'POST',
      headers: getHeaders(),
      body:    JSON.stringify(body),
      onResponseError: handleError,
    })
  }

  /**
   * 通用 PUT 請求
   */
  async function put<T>(path: string, body?: unknown): Promise<ApiResponse<T>> {
    return $fetch<ApiResponse<T>>(`${baseURL}${path}`, {
      method:  'PUT',
      headers: getHeaders(),
      body:    JSON.stringify(body),
      onResponseError: handleError,
    })
  }

  /**
   * 通用 DELETE 請求
   */
  async function del<T = null>(path: string): Promise<ApiResponse<T>> {
    return $fetch<ApiResponse<T>>(`${baseURL}${path}`, {
      method:  'DELETE',
      headers: getHeaders(),
      onResponseError: handleError,
    })
  }

  /**
   * 處理 401（Token 過期自動 refresh）
   */
  async function handleError({ response }: { response: Response }) {
    if (response.status === 401) {
      const refreshed = await authStore.refreshToken()
      if (! refreshed) {
        authStore.logout()
        await navigateTo('/login')
      }
    }
  }

  return { get, getPaginated, post, put, del }
}
