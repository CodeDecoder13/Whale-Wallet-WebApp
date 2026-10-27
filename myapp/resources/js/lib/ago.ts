// "just now", "5 min ago", "3 h ago", "2 d ago"; takes unix seconds or an ISO string, null when never
export function ago(when: number | string | null): string {
    if (when === null) {
        return 'never';
    }

    const ms = typeof when === 'number' ? when * 1000 : Date.parse(when);
    const s = Math.max(0, Math.round((Date.now() - ms) / 1000));

    if (s < 60) {
        return 'just now';
    }

    if (s < 3600) {
        return `${Math.floor(s / 60)} min ago`;
    }

    if (s < 86400) {
        return `${Math.floor(s / 3600)} h ago`;
    }

    return `${Math.floor(s / 86400)} d ago`;
}
