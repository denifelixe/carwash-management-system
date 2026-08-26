<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Car, Clock3, Gift } from '@lucide/vue';
</script>

<template>
    <Head title="Member dashboard" />

    <div class="space-y-8">
        <div>
            <h1 class="text-2xl font-semibold">Member dashboard</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Ringkasan akun dan aktivitas car wash Anda.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <section class="rounded-lg border bg-card p-5">
                <Car class="size-5 text-emerald-600" />
                <h2 class="mt-4 text-sm font-medium">Kendaraan</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Belum ada kendaraan tersimpan.
                </p>
            </section>

            <section class="rounded-lg border bg-card p-5">
                <Clock3 class="size-5 text-blue-600" />
                <h2 class="mt-4 text-sm font-medium">Cucian terakhir</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Belum ada riwayat transaksi.
                </p>
            </section>

            <section class="rounded-lg border bg-card p-5">
                <Gift class="size-5 text-rose-600" />
                <h2 class="mt-4 text-sm font-medium">Reward</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Belum ada reward aktif.
                </p>
            </section>
        </div>
    </div>
</template>
