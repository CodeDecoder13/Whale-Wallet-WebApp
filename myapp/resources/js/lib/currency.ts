const peso = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});

/** Amounts travel as integer centavos; this is the only place they become display text. */
export const formatPeso = (centavos: number) => peso.format(centavos / 100);

/** Centavos to the plain pesos string used in form inputs ("1250.50"). */
export const toPesos = (centavos: number | null | undefined) =>
    centavos == null ? '' : (centavos / 100).toFixed(2);
