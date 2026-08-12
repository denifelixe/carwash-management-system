<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowUpRight, CarFront, MapPin } from '@lucide/vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { entry } from '@/routes/carwash';
</script>

<template>
    <Head title="Pilih Bisnis" />

    <main
        class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-950 px-4 py-12 text-white sm:px-6"
    >
        <div
            class="pointer-events-none absolute -top-40 left-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-cyan-500/15 blur-3xl"
        ></div>
        <div
            class="pointer-events-none absolute right-0 bottom-0 h-72 w-72 rounded-full bg-sky-700/10 blur-3xl"
        ></div>

        <section class="relative w-full max-w-md" aria-labelledby="page-title">
            <header class="text-center">
                <div
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-cyan-400/10 ring-1 ring-cyan-300/20"
                >
                    <CarFront class="h-6 w-6 text-cyan-300" />
                </div>
                <p
                    class="mt-6 text-xs font-semibold tracking-[0.22em] text-cyan-300 uppercase"
                >
                    ST Webapp
                </p>
                <h1
                    id="page-title"
                    class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl"
                >
                    Pilih bisnis
                </h1>
                <p class="mt-3 text-sm leading-6 text-slate-400">
                    Pilih bisnis yang ingin Anda kelola.
                </p>
            </header>

            <div class="mt-10">
                <Link
                    :href="entry.url()"
                    aria-label="Buka ST Carwash"
                    class="group block rounded-2xl outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 focus-visible:ring-offset-4 focus-visible:ring-offset-slate-950"
                >
                    <Card
                        class="relative overflow-hidden border-white/10 bg-white/5 py-0 shadow-2xl shadow-black/30 transition duration-300 group-hover:-translate-y-1 group-hover:border-cyan-300/30 group-hover:bg-white/[0.07]"
                    >
                        <div
                            class="absolute -top-16 -right-16 h-40 w-40 rounded-full bg-cyan-400/10 blur-2xl transition duration-300 group-hover:bg-cyan-400/20"
                        ></div>

                        <CardHeader class="relative gap-5 px-6 pt-6 sm:px-7">
                            <div class="flex items-start justify-between gap-4">
                                <div
                                    class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-400 to-sky-600 text-lg font-bold tracking-tight text-slate-950 shadow-lg shadow-cyan-500/20"
                                >
                                    ST
                                </div>
                                <span
                                    class="flex h-9 w-9 items-center justify-center rounded-full bg-white/5 text-slate-400 ring-1 ring-white/10 transition group-hover:bg-cyan-400 group-hover:text-slate-950 group-hover:ring-cyan-300"
                                >
                                    <ArrowUpRight class="h-4 w-4" />
                                </span>
                            </div>

                            <div class="grid gap-2">
                                <CardTitle class="text-xl text-white">
                                    ST Carwash
                                </CardTitle>
                                <CardDescription
                                    class="flex items-center gap-1.5 text-slate-400"
                                >
                                    <MapPin class="h-3.5 w-3.5" />
                                    Bisnis cuci kendaraan
                                </CardDescription>
                            </div>
                        </CardHeader>

                        <CardContent
                            class="relative border-t border-white/10 px-6 py-4 sm:px-7"
                        >
                            <span
                                class="text-sm font-medium text-cyan-300 transition group-hover:text-cyan-200"
                            >
                                Masuk ke ST Carwash
                            </span>
                        </CardContent>
                    </Card>
                </Link>
            </div>

            <p class="mt-6 text-center text-xs text-slate-600">
                1 bisnis tersedia
            </p>
        </section>
    </main>
</template>
