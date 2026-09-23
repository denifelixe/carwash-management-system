export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
    /** Voice clip played with the toast; null keeps the toast silent. */
    sound?: string | null;
};
