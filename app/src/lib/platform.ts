// The single place in the codebase that may ask which platform it is running
// on. Everything else imports these booleans and never inspects the platform
// itself.
//
// Until Capacitor is added, "native" means the mobile build target. When
// Capacitor arrives, replace the checks below with Capacitor.getPlatform() and
// nothing else in the codebase needs to change.

const userAgent = typeof navigator === 'undefined' ? '' : navigator.userAgent

export const isNative: boolean = import.meta.env.VITE_TARGET === 'mobile'

export const isIOS: boolean = isNative && /iPhone|iPad|iPod/.test(userAgent)

export const isAndroid: boolean = isNative && /Android/.test(userAgent)

export const isWeb: boolean = !isNative
