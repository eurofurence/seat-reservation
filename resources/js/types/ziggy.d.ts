// Ziggy's `route()` helper is registered as a global via the ZiggyVue plugin (see resources/js/app.js)
// but ships without ambient types for this version. Declare it globally so TS files can call it directly.
declare function route(name?: string, params?: any, absolute?: boolean): string
