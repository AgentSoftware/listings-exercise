// Shared Tailwind class strings, so a form field or button looks the same
// everywhere it appears without copying the classes between components.

export const fieldClasses =
    'h-10 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900';

// The listings page's "Search" button.
export const primaryButtonClasses =
    'rounded-lg bg-slate-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-slate-700 disabled:opacity-50';

// The listings page's "Clear" button.
export const secondaryButtonClasses =
    'rounded-lg px-3 py-2 text-sm text-slate-500 underline-offset-2 hover:underline';

// A bordered button, as used by Pagination: "View results", "Save search".
export const outlineButtonClasses =
    'rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm transition hover:bg-slate-100 disabled:opacity-50';

// Shared visible focus ring for buttons that lack one.
export const focusRingClasses =
    'focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2';
