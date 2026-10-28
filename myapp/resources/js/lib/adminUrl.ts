// The admin lives at the root of its own host (admin.whale-wallet.cloud) or, locally, under /admin on the main host.
// The path says which one we are on, so every admin link is built from this instead of a hard-coded "/admin".
export const adminBase = (): string => (typeof location !== 'undefined' && location.pathname.startsWith('/admin') ? '/admin' : '');

export const adminUrl = (path = ''): string => adminBase() + path || '/';
