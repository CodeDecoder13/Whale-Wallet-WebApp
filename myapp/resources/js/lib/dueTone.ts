// Due tone: red today or late, orange within 3 days, yellow within a week, green beyond.
export function dueTone(due: string | null, done = false) {
    if (!due || done) {
        return { days: null, c: '#94a3b8', text: done ? 'Completed' : 'No date' };
    }

    const t = new Date();
    const today = new Date(t.getFullYear(), t.getMonth(), t.getDate());
    const days = Math.round(
        (new Date(`${due.slice(0, 10)}T00:00`).getTime() - today.getTime()) /
            864e5,
    );

    return days <= 0
        ? { days, c: '#ef4444', text: days === 0 ? 'Due today' : `${-days}d overdue` }
        : days <= 3
          ? { days, c: '#f97316', text: `Due in ${days}d` }
          : days <= 7
            ? { days, c: '#eab308', text: `Due in ${days}d` }
            : { days, c: '#22c55e', text: `Due in ${days}d` };
}
