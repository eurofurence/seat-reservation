// The 'auth' prop is shared globally on every Inertia response (see
// HandleInertiaRequests::share) but @inertiajs/core's PageProps has no ambient
// type for it - augment it so `$page.props.auth.user` is typed instead of `unknown`.
declare module '@inertiajs/core' {
  interface PageProps {
    auth?: {
      user?: { id: number; name: string; is_admin: boolean } | null
    }
  }
}
