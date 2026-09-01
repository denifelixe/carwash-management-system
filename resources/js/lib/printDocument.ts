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

/**
 * Toolbar glyphs. They ship as markup rather than a font so the printed
 * documents stay self-contained, with nothing left to fetch.
 */
export const toolbarIcons = {
    print: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 8V3h10v5"/><path d="M7 18H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="7" y="14" width="10" height="7" rx="1.5"/></svg>',
    download:
        '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M4 20h16"/></svg>',
    link: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9.8 14.2 4.4-4.4"/><path d="M11.3 6.6 13 4.9a4.1 4.1 0 0 1 5.8 5.8l-1.7 1.7"/><path d="m12.7 17.4-1.7 1.7a4.1 4.1 0 0 1-5.8-5.8l1.7-1.7"/></svg>',
    check: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>',
    close: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.5 6.5 17.5 17.5"/><path d="M17.5 6.5 6.5 17.5"/></svg>',
};

/** One segment of the toolbar: a glyph over its label, keyed by a data attribute. */
export function toolbarButton(
    attribute: string,
    icon: string,
    label: string,
    modifier = '',
    disabled = false,
): string {
    const className = modifier === '' ? '' : ` class="${modifier}"`;

    return `<button type="button"${className} ${attribute}${disabled ? ' disabled' : ''} title="${escapeHtml(label)}">${icon}<span>${escapeHtml(label)}</span></button>`;
}

/** Confirms an action that leaves nothing behind on the document itself. */
export function toastMarkup(): string {
    return (
        '<div class="toast" role="status" aria-live="polite" data-print-toast>' +
        `${toolbarIcons.check}<span data-print-toast-message></span></div>`
    );
}

/**
 * The segmented bar both documents are topped with, and the toast beside it.
 * The width is left to the caller because it is the paper's, not the bar's.
 */
export function toolbarStyles(): string {
    return `.toolbar {
    background: #ffffff;
    border: 1px solid rgba(15, 23, 42, 0.07);
    border-radius: 14px;
    box-shadow:
        0 1px 2px rgba(15, 23, 42, 0.05),
        0 10px 26px -12px rgba(15, 23, 42, 0.28);
    display: flex;
    gap: 2px;
    margin: 0 auto 14px;
    padding: 5px;
}
.toolbar button {
    align-items: center;
    background: transparent;
    border: 0;
    border-radius: 10px;
    color: #475569;
    cursor: pointer;
    display: flex;
    flex: 1;
    flex-direction: column;
    font-family: ui-sans-serif, system-ui, 'Segoe UI', sans-serif;
    font-size: 9.5px;
    font-weight: 600;
    gap: 4px;
    letter-spacing: 0.01em;
    line-height: 1;
    min-width: 0;
    padding: 7px 2px 6px;
    transition: background-color 140ms ease, color 140ms ease;
}
.toolbar button svg {
    fill: none;
    height: 15px;
    stroke: currentColor;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 1.7;
    width: 15px;
}
.toolbar button span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    width: 100%;
}
.toolbar button:hover { background: #f1f5f9; color: #0f172a; }
.toolbar button:active { background: #e2e8f0; }
.toolbar button:focus-visible { outline: 2px solid #0f172a; outline-offset: -1px; }
.toolbar button.primary { background: #0f172a; color: #ffffff; }
.toolbar button.primary:hover { background: #1e293b; }
.toolbar button.primary:active { background: #334155; }
.toolbar button.copied, .toolbar button.copied:hover { color: #059669; }
.toolbar button:disabled { background: transparent; color: #cbd5e1; cursor: not-allowed; }
/* Feedback for actions that leave no trace on the page, e.g. the copied link. */
.toast {
    align-items: center;
    background: #0f172a;
    border-radius: 10px;
    box-shadow: 0 10px 26px -10px rgba(15, 23, 42, 0.45);
    color: #ffffff;
    display: flex;
    font-family: ui-sans-serif, system-ui, 'Segoe UI', sans-serif;
    font-size: 11px;
    font-weight: 600;
    gap: 7px;
    opacity: 0;
    padding: 9px 12px;
    pointer-events: none;
    position: fixed;
    right: 14px;
    top: 14px;
    transform: translateY(-8px);
    transition: opacity 180ms ease, transform 180ms ease;
    z-index: 10;
}
.toast.visible { opacity: 1; transform: translateY(0); }
.toast.error { background: #b91c1c; }
.toast.error svg { stroke: #fecaca; }
.toast svg {
    fill: none;
    flex: 0 0 auto;
    height: 14px;
    stroke: #4ade80;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 2.2;
    width: 14px;
}`;
}

/**
 * Puts a link on the clipboard, reporting whether it landed so the
 * document can say so either way.
 *
 * `navigator.clipboard` only exists on a secure origin, and an outlet served
 * over plain http has none, so the selection-based copy stands behind it rather
 * than the button silently doing nothing.
 */
export async function copyToClipboard(
    documentWindow: Window,
    url: string,
): Promise<boolean> {
    if (documentWindow.isSecureContext && documentWindow.navigator.clipboard) {
        try {
            await documentWindow.navigator.clipboard.writeText(url);

            return true;
        } catch {
            /* A denied permission or an unfocused window falls through. */
        }
    }

    const field = documentWindow.document.createElement('textarea');

    field.value = url;
    field.setAttribute('readonly', '');
    field.style.left = '-9999px';
    field.style.position = 'fixed';
    documentWindow.document.body.append(field);
    field.select();

    try {
        return documentWindow.document.execCommand('copy');
    } catch {
        return false;
    } finally {
        field.remove();
    }
}

/**
 * Turns one of the static toolbar glyphs into nodes, so a button can swap its
 * icon. Only the constants above are ever passed in, so no user value reaches
 * innerHTML here.
 */
export function parseIcon(documentWindow: Window, icon: string): ChildNode[] {
    const template = documentWindow.document.createElement('template');

    template.innerHTML = icon;

    return [...template.content.childNodes];
}

/**
 * Wires a toast into a written document. Returns the shower, so a module that
 * has no toast in its markup simply never calls one.
 */
export function documentToaster(
    documentWindow: Window,
    duration = 2400,
): (message: string, tone?: 'success' | 'error') => void {
    let timer = 0;

    return (message, tone = 'success') => {
        const toast =
            documentWindow.document.querySelector<HTMLElement>(
                '[data-print-toast]',
            );
        const label = toast?.querySelector('[data-print-toast-message]');

        if (!toast || !label) {
            return;
        }

        label.textContent = message;
        toast.classList.toggle('error', tone === 'error');
        toast.classList.add('visible');

        documentWindow.clearTimeout(timer);
        timer = documentWindow.setTimeout(() => {
            toast.classList.remove('visible');
        }, duration);
    };
}
