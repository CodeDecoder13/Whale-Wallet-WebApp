// How a transaction looks by category (the merchant is a fallback hint): an emoji, a tile class, a colour and a drawing key.
// First match wins, so the specific ones (car wash, coffee) sit above the general ones (car, food).
export type Look = { key: string; emoji: string; tile: string; hex: string };

const rows: [RegExp, Look][] = [
    [/laundry/, { key: 'laundry', emoji: '🧺', tile: 'bg-sky-100 dark:bg-sky-500/20', hex: '#0ea5e9' }],
    [/car ?wash/, { key: 'carwash', emoji: '🚗🫧', tile: 'bg-cyan-100 dark:bg-cyan-500/20', hex: '#06b6d4' }],
    [/credit|debit|\batm\b|\bcards?\b/, { key: 'card', emoji: '💳', tile: 'bg-blue-100 dark:bg-blue-500/20', hex: '#3b82f6' }],
    [/motor|moto|scooter|\bbike\b/, { key: 'motor', emoji: '🏍️', tile: 'bg-zinc-200 dark:bg-zinc-500/20', hex: '#ef4444' }],
    [/coffee|cafe|café|latte|milk ?tea/, { key: 'coffee', emoji: '☕', tile: 'bg-amber-100 dark:bg-amber-600/20', hex: '#b45309' }],
    [/grocer|market|supermarket/, { key: 'grocery', emoji: '🛒', tile: 'bg-lime-100 dark:bg-lime-500/20', hex: '#65a30d' }],
    [/food|lunch|dinner|breakfast|meal|snack|drink|restaurant|eat/, { key: 'food', emoji: '🍽️', tile: 'bg-orange-100 dark:bg-orange-500/20', hex: '#f97316' }],
    [/car|gas|fuel|parking|toll|vehicle/, { key: 'car', emoji: '🚗', tile: 'bg-red-100 dark:bg-red-500/20', hex: '#ef4444' }],
    [/transport|grab|taxi|jeep|bus|fare|commute/, { key: 'bus', emoji: '🚌', tile: 'bg-yellow-100 dark:bg-yellow-500/20', hex: '#eab308' }],
    [/bill|electric|water|internet|rent|utilit|load/, { key: 'bill', emoji: '💡', tile: 'bg-amber-100 dark:bg-amber-500/20', hex: '#f59e0b' }],
    [/shop|cloth|mall|gift/, { key: 'shop', emoji: '🛍️', tile: 'bg-pink-100 dark:bg-pink-500/20', hex: '#ec4899' }],
    [/health|medic|pharma|doctor|clinic|vitamin/, { key: 'health', emoji: '💊', tile: 'bg-emerald-100 dark:bg-emerald-500/20', hex: '#10b981' }],
    [/entertain|movie|game|netflix|music|fun/, { key: 'fun', emoji: '🎮', tile: 'bg-violet-100 dark:bg-violet-500/20', hex: '#8b5cf6' }],
    [/school|tuition|book|educ/, { key: 'school', emoji: '📚', tile: 'bg-indigo-100 dark:bg-indigo-500/20', hex: '#6366f1' }],
    [/pet|dog|cat/, { key: 'pet', emoji: '🐶', tile: 'bg-stone-200 dark:bg-stone-500/20', hex: '#a8a29e' }],
    [/travel|flight|hotel|trip/, { key: 'travel', emoji: '✈️', tile: 'bg-cyan-100 dark:bg-cyan-500/20', hex: '#0891b2' }],
];

const other: Look = { key: 'other', emoji: '💸', tile: 'bg-slate-100 dark:bg-slate-500/20', hex: '#64748b' };

export function categoryLook(category?: string | null, merchant?: string | null): Look {
    const text = `${category ?? ''} ${merchant ?? ''}`.toLowerCase();

    return rows.find(([re]) => re.test(text))?.[1] ?? other;
}
