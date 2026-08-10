// Ziggy's `route()` helper is registered as a global via the ZiggyVue plugin (see resources/js/app.js)
// but ships without ambient types for this version. Declare it globally so TS files can call it directly.
// This file is a module (due to the `import 'vue'` below, needed for the augmentation further down),
// so the global declaration must be wrapped in `declare global` to actually be visible everywhere.
declare global {
  function route(name?: string, params?: any, absolute?: boolean): string
}

// Vue SFC templates compile a bare `route(...)` call to `_ctx.route(...)` (a component-instance
// property lookup, not a free identifier) since Ziggy registers it as a Vue global property - the
// plain global declaration above only covers <script> code, not template expressions.
// The `import 'vue'` below (needed only to make this a module, not a script) is required for
// `declare module 'vue'` to augment the real module instead of silently replacing it.
import 'vue'
declare module 'vue' {
  interface ComponentCustomProperties {
    route: typeof route
  }
}
