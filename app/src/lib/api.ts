import { isNative } from '@/lib/platform'
import * as tokenStorage from '@/lib/tokenStorage'

// The one wrapper around fetch. Callers pass a path such as /api/me and get
// JSON back. They never know whether the request carried a session cookie
// (web) or a bearer token (native); that decision is made here.
//
// The base URL comes from the environment and is never derived from
// window.location: the app is served from app.klaroly.com or from
// capacitor://localhost, and neither is where the API lives.

const baseUrl: string = import.meta.env.VITE_API_URL

type Method = 'GET' | 'POST' | 'PUT' | 'DELETE'

interface RequestOptions {
  method?: Method
  body?: unknown
}

interface ValidationBody {
  message?: string
  errors?: Record<string, string[]>
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

  // Field name to first message, for a 422 in Laravel's {message, errors}
  // shape. Any other response gives an empty map, so a screen can always
  // read it without digging through the body.
  validationErrors(): Record<string, string> {
    if (this.status !== 422 || typeof this.body !== 'object' || this.body === null) {
      return {}
    }

    const errors = (this.body as ValidationBody).errors ?? {}
    const first: Record<string, string> = {}

    for (const [field, messages] of Object.entries(errors)) {
      if (messages.length > 0) {
        first[field] = messages[0]
      }
    }

    return first
  }
}

type UnauthenticatedHandler = () => void

let unauthenticatedHandler: UnauthenticatedHandler | null = null

// The auth store registers one handler here so that a 401 from any request
// marks the person as signed out. It is called after the ApiError is built
// and before it is thrown; the router guard does the redirect.
export function onUnauthenticated(handler: UnauthenticatedHandler): void {
  unauthenticatedHandler = handler
}

// Web builds must fetch the CSRF cookie before the first request that changes
// state. Harmless to call more than once.
async function ensureCsrfCookie(): Promise<void> {
  await fetch(`${baseUrl}/sanctum/csrf-cookie`, {
    headers: { Accept: 'application/json' },
    credentials: 'include',
  })
}

export async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const method = options.method ?? 'GET'
  const changesState = method !== 'GET'

  if (!isNative && changesState && readCookie('XSRF-TOKEN') === null) {
    await ensureCsrfCookie()
  }

  let response = await send(path, method, options.body)

  // A 419 means the CSRF token the browser sent no longer matches the
  // session, for example after the session expired. Fetch a fresh cookie
  // and try once more. Nothing else is ever retried.
  if (!isNative && changesState && response.status === 419) {
    await ensureCsrfCookie()
    response = await send(path, method, options.body)
  }

  const body: unknown = response.status === 204 ? null : await parseJson(response)

  if (!response.ok) {
    const error = new ApiError(response.status, body)

    if (response.status === 401 && unauthenticatedHandler) {
      unauthenticatedHandler()
    }

    throw error
  }

  return body as T
}

async function send(path: string, method: Method, body: unknown): Promise<Response> {
  const headers = new Headers({ Accept: 'application/json' })

  if (body !== undefined) {
    headers.set('Content-Type', 'application/json')
  }

  if (isNative) {
    const token = tokenStorage.get()

    if (token) {
      headers.set('Authorization', `Bearer ${token}`)
    }
  } else {
    // Read fresh on every request: the token rotates after login and
    // register.
    const csrfToken = readCookie('XSRF-TOKEN')

    if (csrfToken) {
      headers.set('X-XSRF-TOKEN', csrfToken)
    }
  }

  return fetch(`${baseUrl}${path}`, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
    // Web sends the session cookie. Native has no cookie to send.
    credentials: isNative ? 'omit' : 'include',
  })
}

export const api = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, body?: unknown) => request<T>(path, { method: 'POST', body }),
  put: <T>(path: string, body?: unknown) => request<T>(path, { method: 'PUT', body }),
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
