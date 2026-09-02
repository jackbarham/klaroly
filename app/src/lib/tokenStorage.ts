// Where the native build keeps its bearer token.
//
// On a device the token must survive a relaunch, so this file is replaced by
// Capacitor's secure storage when Capacitor is added. It keeps the same three
// functions, and nothing else in the codebase changes when it is swapped.
// Until then the token lives in memory, a mobile build forgets its login on
// reload, and that is accepted.

let token: string | null = null

export function get(): string | null {
  return token
}

export function set(value: string): void {
  token = value
}

export function clear(): void {
  token = null
}
