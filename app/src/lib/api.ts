import { isNative } from '@/lib/platform'

// The one wrapper around fetch. Callers pass a path such as /api/user and
// get JSON back. They never know whether the request carried a session
// cookie (web) or a bearer token (native); that decision is made here.
//
// The base URL comes from the environment and is never derived from
// window.location: the app is served from app.klaroly.com or from
// capacitor://localhost, and neither is where the API lives.

const baseUrl: string = import.meta.env.VITE_API_URL

let bearerToken: string | null = null

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

interface RequestOptions {
  method?: Method
  body?: unknown
}

export class ApiError extends Error {
  readonly status: number
  readonly body: unknown

  constructor(status: number, body: unknown) {
    super(`API request failed with status ${status}`)
    this.name = 'ApiError'
    this.status = status
    this.body = body
  }
}

// Native builds authenticate with a bearer token. It is held in memory for
// now; secure persistent storage arrives with Capacitor.
export function setBearerToken(token: string | null): void {
  bearerToken = token
}

// Web builds must fetch the CSRF cookie once before the first request that
// changes state (for example, signing in). Harmless to call more than once.
export async function ensureCsrfCookie(): Promise<void> {
  if (isNative) {
    return
  }

  await fetch(`${baseUrl}/sanctum/csrf-cookie`, { credentials: 'include' })
}

export async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const headers = new Headers({ Accept: 'application/json' })

  if (options.body !== undefined) {
    headers.set('Content-Type', 'application/json')
  }

  if (isNative) {
    if (bearerToken) {
      headers.set('Authorization', `Bearer ${bearerToken}`)
    }
  } else {
    const csrfToken = readCookie('XSRF-TOKEN')

    if (csrfToken) {
      headers.set('X-XSRF-TOKEN', csrfToken)
    }
  }

  const response = await fetch(`${baseUrl}${path}`, {
    method: options.method ?? 'GET',
    headers,
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
    // Web sends the session cookie. Native has no cookie to send.
    credentials: isNative ? 'omit' : 'include',
  })

  const body: unknown = response.status === 204 ? null : await parseJson(response)

  if (!response.ok) {
    throw new ApiError(response.status, body)
  }

  return body as T
}

export const api = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, body?: unknown) => request<T>(path, { method: 'POST', body }),
  put: <T>(path: string, body?: unknown) => request<T>(path, { method: 'PUT', body }),
  patch: <T>(path: string, body?: unknown) => request<T>(path, { method: 'PATCH', body }),
  delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
}

async function parseJson(response: Response): Promise<unknown> {
  const text = await response.text()

  if (text === '') {
    return null
  }

  return JSON.parse(text)
}

function readCookie(name: string): string | null {
  const prefix = `${name}=`
  const match = document.cookie.split('; ').find((cookie) => cookie.startsWith(prefix))

  if (!match) {
    return null
  }

  return decodeURIComponent(match.slice(prefix.length))
}
