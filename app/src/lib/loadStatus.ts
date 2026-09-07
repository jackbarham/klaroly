// The four states a store's one request can be in, written once. Every store
// that fetches a payload declares its status with this, so the failed banner
// a screen draws is asking the same question on every screen.
export type LoadStatus = 'idle' | 'loading' | 'ready' | 'failed'
