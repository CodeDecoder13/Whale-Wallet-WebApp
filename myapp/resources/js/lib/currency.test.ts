import { expect, test } from 'vite-plus/test';
import { formatPeso, toPesos } from './currency';

test.each([
    [125050, '₱1,250.50'],
    [0, '₱0.00'],
    [-9500, '-₱95.00'],
])('formatPeso(%i)', (centavos, text) => {
    expect(formatPeso(centavos).replace(/\s/g, '')).toBe(text);
});

test('toPesos', () => {
    expect(toPesos(125050)).toBe('1250.50');
    expect(toPesos(null)).toBe('');
});
