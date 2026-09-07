import type { RouteLocationNormalizedLoaded } from 'vue-router'

// The numeric id in a route's params, or null when there is nothing worth
// looking up. Written once for the four screens that read one, so the rule
// that an id is a positive whole number is one rule rather than four.
export function routeId(route: RouteLocationNormalizedLoaded): number | null {
  const id = Number(route.params.id)

  return Number.isFinite(id) && id > 0 ? id : null
}
