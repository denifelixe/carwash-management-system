const currencyFormatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const numberFormatter = new Intl.NumberFormat('id-ID');

export function formatCurrency(value: number): string {
    return currencyFormatter.format(value);
}

export function formatNumber(value: number): string {
    return numberFormatter.format(value);
}

/** Compact rupiah for dense cards and chart axes: "Rp 1,2 jt", "Rp 450 rb". */
export function formatShortCurrency(value: number): string {
    if (Math.abs(value) >= 1_000_000) {
        return `Rp ${(value / 1_000_000).toFixed(1).replace('.', ',')} jt`;
    }

    return `Rp ${Math.round(value / 1_000)} rb`;
}

export function formatPercent(value: number): string {
    return `${value.toFixed(1).replace('.', ',')}%`;
}

export function useCarwashFormat() {
    return {
        formatCurrency,
        formatNumber,
        formatShortCurrency,
        formatPercent,
    };
}
