const currencyFormatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const numberFormatter = new Intl.NumberFormat('id-ID');

const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

const longDateFormatter = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
});

export function formatCurrency(value: number): string {
    return currencyFormatter.format(value);
}

export function formatNumber(value: number): string {
    return numberFormatter.format(value);
}

/** ISO "2026-08-19" as the code a number carries: "260819". */
export function formatDateCode(date: string): string {
    return date.replaceAll('-', '').slice(2);
}

/** ISO "2026-08-05" the way the modules spell a date: "5 Agu 2026". */
export function formatDate(date: string): string {
    return dateFormatter.format(new Date(`${date}T00:00:00`));
}

/** ISO "2026-09-01" spelled out in full: "01 September 2026". */
export function formatLongDate(date: string): string {
    return longDateFormatter.format(new Date(`${date}T00:00:00`));
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
        formatDate,
        formatDateCode,
        formatLongDate,
        formatShortCurrency,
        formatPercent,
    };
}
