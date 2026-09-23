/**
 * Voice clips recorded for the counter, served from public/notification-sounds/.
 * The server names the clip (HandleInertiaRequests::SOUND_ROUTES); a name
 * outside this list is ignored rather than turned into a request.
 */
const NOTIFICATION_SOUNDS = [
    'order-telah-berhasil-dibuat',
    'status-order-telah-berhasil-diperbarui',
    'pembayaran-berhasil',
] as const;

export type NotificationSound = (typeof NOTIFICATION_SOUNDS)[number];

let playingClip: HTMLAudioElement | null = null;

export function isNotificationSound(name: string): name is NotificationSound {
    return (NOTIFICATION_SOUNDS as readonly string[]).includes(name);
}

/**
 * Plays the clip at full volume (the counter is noisy), cutting off one still
 * playing so two quick saves do not talk over each other. Browsers only allow
 * audio after the user has interacted with the page; every caller follows a
 * click, and a refused or missing clip is skipped quietly because the toast
 * already carries the message.
 */
export function playNotificationSound(name: NotificationSound): void {
    if (typeof window === 'undefined' || typeof Audio === 'undefined') {
        return;
    }

    playingClip?.pause();
    playingClip = new Audio(`/notification-sounds/${name}.mp3`);
    playingClip.volume = 1;
    playingClip.play().catch(() => undefined);
}
