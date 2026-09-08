import { ref } from 'vue';

export const adminLogoutRequested = ref(false);

export function requestAdminLogout(): void {
    adminLogoutRequested.value = true;
}
