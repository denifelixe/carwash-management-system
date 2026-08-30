/**
 * Helpers shared by the documents the app prints outside the SPA.
 *
 * The cashier's slip and the shift recaps are both written as self-contained
 * HTML into their own window, so every value they carry comes from user input
 * and none of it may reach the document raw. These live here rather than in one
 * of the two modules so there is a single escaper, not a copy per document.
 */

export function escapeHtml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

/** "6281800090009" the way a printed document spells it: "+62 818-0009-0009". */
export function formatWhatsapp(whatsapp: string): string {
    const digits = whatsapp.replace(/\D/g, '');

    if (!digits.startsWith('62')) {
        return whatsapp;
    }

    const national = digits.slice(2);

    return `+62 ${national.slice(0, 3)}-${national.slice(3, 7)}-${national.slice(7)}`;
}

const whatsappIcon = `<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-12.6 7.4L3 21l2.1-5.2A8.5 8.5 0 1 1 21 11.5Z"/><path d="M8.2 8.1c.5 3.2 2.4 5.1 5.7 5.7l1.5-1.5"/></svg>`;
const instagramIcon = `<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>`;

/** Branded contact rows shared by the POS slip and financial recaps. */
export function brandContacts(whatsapp: string, instagram: string): string {
    return `<div class="contacts">
    <p class="contact"><span class="contact-icon whatsapp">${whatsappIcon}</span><span>${escapeHtml(formatWhatsapp(whatsapp))}</span></p>
    <p class="contact"><span class="contact-icon instagram">${instagramIcon}</span><span>@${escapeHtml(instagram)}</span></p>
</div>`;
}

/** Uses the uploaded app photo on printed documents, with the emoji as fallback. */
export function brandMark(
    photo: string | null,
    logo: string,
    name: string,
): string {
    if (photo !== null) {
        return `<img class="logo-image" src="${escapeHtml(photo)}" alt="${escapeHtml(name)}">`;
    }

    return `<p class="logo">${escapeHtml(logo)}</p>`;
}

/**
 * Stamped on a document so the desk knows when it left the app. The zone comes
 * from the page's `filters.timezone`, never the machine, or a cashier on a
 * differently-zoned laptop stamps a time the server disagrees with.
 */
export function printedAt(timeZone: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone,
    }).format(new Date());
}
