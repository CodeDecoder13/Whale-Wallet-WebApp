// ponytail: static list, no npm package tracks PH banks with brand colours; add a row to support a new one
export type BankPreset = { name: string; type: string; color: string };

export const banks: BankPreset[] = [
    { name: 'GCash', type: 'ewallet', color: '#007CFF' },
    { name: 'Maya', type: 'ewallet', color: '#0B8A4D' },
    { name: 'GrabPay', type: 'ewallet', color: '#00B14F' },
    { name: 'ShopeePay', type: 'ewallet', color: '#EE4D2D' },
    { name: 'BDO', type: 'bank', color: '#0A3A8C' },
    { name: 'BPI', type: 'bank', color: '#B11116' },
    { name: 'Metrobank', type: 'bank', color: '#1C3F94' },
    { name: 'Landbank', type: 'bank', color: '#00793E' },
    { name: 'PNB', type: 'bank', color: '#003D79' },
    { name: 'Security Bank', type: 'bank', color: '#6FAE2E' },
    { name: 'UnionBank', type: 'bank', color: '#F47B20' },
    { name: 'RCBC', type: 'bank', color: '#0070BA' },
    { name: 'China Bank', type: 'bank', color: '#C8102E' },
    { name: 'EastWest', type: 'bank', color: '#6A2C91' },
    { name: 'PSBank', type: 'bank', color: '#00843D' },
    { name: 'Chinabank Savings', type: 'bank', color: '#E03C31' },
    { name: 'CIMB', type: 'bank', color: '#7E1416' },
    { name: 'GoTyme', type: 'bank', color: '#00C2A8' },
    { name: 'Maribank', type: 'bank', color: '#F26B21' },
    { name: 'Cash', type: 'cash', color: '#475569' },
];

/** The preset colour for a card name ("BDO Savings" is BDO), or null for anything custom. */
export const bankColor = (name: string): string | null => {
    const n = name.toLowerCase();

    return banks.find((b) => n.includes(b.name.toLowerCase()))?.color ?? null;
};

/** Card background: a darker-to-base gradient of the preset colour. */
export const cardGradient = (color: string) =>
    `linear-gradient(135deg, color-mix(in srgb, ${color} 70%, black), ${color})`;
