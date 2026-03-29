<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

interface SlipPayload {
    payer: string | null;
    payee: string | null;
    purpose: string | null;
    amount: string | null;
    currency: string | null;
    payerAccount: string | null;
    recipientAccount: string | null;
    paymentCode: string | null;
    debtorModel: string | null;
    debtorReferenceNumber: string | null;
    creditorModel: string | null;
    creditorReferenceNumber: string | null;
    ipsQrData: string | null;
}

interface SlipResult {
    slip: SlipPayload;
    qrDataUrl: string | null;
}

const MIN_PROCESSING_MS = 2500;

const processing = ref(false);
const confirmed = ref(false);
const results = ref<SlipResult[]>([]);
const pdfError = ref<string | null>(null);
const slipCollapsed = ref<Record<number, boolean>>({});
const copiedIndex = ref<number | null>(null);

function sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function toggleSlip(index: number): void {
    slipCollapsed.value[index] = !slipCollapsed.value[index];
}

async function copyIpsString(text: string, index: number): Promise<void> {
    await navigator.clipboard.writeText(text);
    copiedIndex.value = index;
    setTimeout(() => {
        copiedIndex.value = null;
    }, 2000);
}

function downloadQr(dataUrl: string, index: number): void {
    const a = document.createElement('a');
    a.href = dataUrl;
    a.download = `ips-qr-slip-${index + 1}.png`;
    a.click();
}

function referenceDisplay(slip: SlipPayload): string | null {
    const model = slip.creditorModel ?? slip.debtorModel;
    const ref = slip.creditorReferenceNumber ?? slip.debtorReferenceNumber;

    if (!model && !ref) {
        return null;
    }

    return [model, ref].filter(Boolean).join(' ');
}

async function buildResults(slips: SlipPayload[]): Promise<SlipResult[]> {
    return Promise.all(
        slips.map(async (slip) => {
            const qrDataUrl = slip.ipsQrData
                ? await QRCode.toDataURL(slip.ipsQrData, { width: 200, margin: 1, errorCorrectionLevel: 'M' })
                : null;

            return { slip, qrDataUrl };
        }),
    );
}

async function submitPdf(event: Event): Promise<void> {
    const form = event.target as HTMLFormElement;
    const fileInput = form.elements.namedItem('pdf') as HTMLInputElement;
    const file = fileInput?.files?.[0];

    pdfError.value = null;
    results.value = [];
    confirmed.value = false;
    slipCollapsed.value = {};

    if (!file) {
        pdfError.value = 'Please upload a PDF payment slip.';

        return;
    }

    processing.value = true;

    const formData = new FormData();
    formData.append('pdf', file);

    try {
        const [response] = await Promise.all([
            fetch('/parse', {
                method: 'POST',
                headers: { 'X-XSRF-TOKEN': xsrfToken() },
                body: formData,
            }),
            sleep(MIN_PROCESSING_MS),
        ]);

        if (response.status === 422) {
            const body = await response.json();
            pdfError.value = body?.errors?.pdf?.[0] ?? 'Validation failed.';

            return;
        }

        if (!response.ok) {
            pdfError.value = 'Something went wrong. Please try again.';

            return;
        }

        const body = await response.json();
        results.value = await buildResults(body.data ?? []);
    } catch {
        pdfError.value = 'Something went wrong. Please try again.';
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-white via-white to-red-50/50 text-zinc-900 dark:from-zinc-950 dark:via-zinc-950 dark:to-red-950/20 dark:text-white">
        <Head title="IPS QR Generator" />

        <header class="border-b border-zinc-800 bg-zinc-950">
            <div class="mx-auto flex max-w-4xl items-center px-6 py-4">
                <div class="flex items-center gap-2.5 text-white">
                    <AppLogo class="size-5" />
                    <span class="text-sm font-semibold tracking-tight">IPS QR</span>
                </div>
            </div>
        </header>

        <main class="mx-auto flex max-w-4xl flex-col gap-16 px-6 py-20">

            <section class="flex flex-col gap-10">
                <div class="flex flex-col gap-8 sm:flex-row sm:gap-10">
                    <div class="shrink-0 sm:aspect-square sm:self-stretch">
                        <AppLogo class="size-28 text-red-600 sm:h-full sm:w-full" />
                    </div>

                    <div class="flex flex-col gap-4 border-b border-zinc-200 pb-10 dark:border-zinc-800">
                        <h1 class="text-5xl font-black tracking-tight sm:text-6xl">
                            <span class="bg-gradient-to-r from-red-600 to-zinc-900 bg-clip-text text-transparent dark:from-red-500 dark:to-zinc-100">
                                IPS QR Generator
                            </span>
                        </h1>
                        <p class="max-w-lg text-lg leading-relaxed text-zinc-500 dark:text-zinc-400">
                            Upload a digital Serbian payment slip PDF and get a standards-compliant IPS QR code
                            in seconds. Data is extracted automatically, validated against the NBS format,
                            and ready to scan with any banking app.
                        </p>
                    </div>
                </div>

                <form class="flex max-w-xs flex-col gap-4" @submit.prevent="submitPdf">
                    <div class="flex flex-col gap-2">
                        <Label for="pdf" class="text-xs font-medium uppercase tracking-wide text-zinc-400">
                            PDF file
                        </Label>
                        <Input
                            id="pdf"
                            name="pdf"
                            type="file"
                            accept="application/pdf"
                            required
                            :disabled="processing"
                            class="cursor-pointer"
                        />
                        <InputError :message="pdfError ?? undefined" />
                    </div>

                    <Button
                        type="submit"
                        :disabled="processing"
                        class="w-fit bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-600/50"
                    >
                        <Spinner v-if="processing" />
                        {{ processing ? 'Parsing…' : 'Generate QR code' }}
                    </Button>
                </form>
            </section>

            <section v-if="processing" class="flex flex-col gap-4">
                <div
                    v-for="i in 2"
                    :key="i"
                    class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800"
                >
                    <div class="flex items-center justify-between bg-zinc-950 px-5 py-3">
                        <div class="h-2.5 w-12 animate-pulse rounded-full bg-zinc-700" />
                        <div class="h-2.5 w-16 animate-pulse rounded-full bg-zinc-700" />
                    </div>
                    <div class="flex flex-col md:flex-row">
                        <div class="flex flex-1 flex-col gap-5 p-5">
                            <div class="grid grid-cols-2 gap-x-8 gap-y-4">
                                <div v-for="j in 6" :key="j" class="flex flex-col gap-1.5">
                                    <div class="h-2 w-12 animate-pulse rounded-full bg-zinc-200 dark:bg-zinc-700" />
                                    <div class="h-2.5 w-28 animate-pulse rounded-full bg-zinc-200 dark:bg-zinc-700" />
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-center border-t border-zinc-100 p-5 dark:border-zinc-800 md:w-52 md:border-l md:border-t-0">
                            <div class="size-36 animate-pulse rounded bg-zinc-200 dark:bg-zinc-700" />
                        </div>
                    </div>
                </div>
            </section>

            <section v-else-if="results.length > 0 && !confirmed" class="flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <span class="shrink-0 text-xs font-semibold uppercase tracking-widest text-red-600">
                        {{ results.length }} {{ results.length === 1 ? 'slip' : 'slips' }} parsed
                    </span>
                    <div class="h-px flex-1 bg-red-200 dark:bg-red-900/40" />
                </div>

                <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="border-l-4 border-l-red-600 p-6">
                        <p class="font-semibold text-zinc-900 dark:text-white">Verify before scanning</p>
                        <p class="mt-1.5 max-w-lg text-sm text-zinc-500 dark:text-zinc-400">
                            This data was automatically extracted from your PDF. Before using any QR code,
                            confirm the recipient name, account number and amount match your original payment slip exactly.
                        </p>
                        <Button
                            class="mt-5 bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-600/50"
                            @click="confirmed = true"
                        >
                            Show QR codes
                        </Button>
                    </div>
                </div>
            </section>

            <section v-else-if="results.length > 0 && confirmed" class="flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <span class="shrink-0 text-xs font-semibold uppercase tracking-widest text-red-600">
                        {{ results.length }} {{ results.length === 1 ? 'slip' : 'slips' }} parsed
                    </span>
                    <div class="h-px flex-1 bg-red-200 dark:bg-red-900/40" />
                </div>

                <article
                    v-for="({ slip, qrDataUrl }, index) in results"
                    :key="index"
                    class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900"
                >
                    <div class="flex items-center justify-between bg-zinc-950 px-5 py-3">
                        <span class="text-xs font-semibold uppercase tracking-widest text-zinc-400">
                            Slip {{ index + 1 }}
                        </span>

                        <div class="flex items-center gap-3">
                            <span
                                v-if="slip.ipsQrData"
                                class="text-xs font-semibold text-red-500"
                            >
                                ● QR ready
                            </span>
                            <span v-else class="text-xs text-amber-400">
                                ● QR unavailable
                            </span>

                            <button
                                type="button"
                                class="flex size-6 cursor-pointer items-center justify-center rounded text-zinc-500 transition-colors hover:text-white"
                                :aria-label="slipCollapsed[index] ? 'Expand slip' : 'Collapse slip'"
                                @click="toggleSlip(index)"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    class="size-4 transition-transform duration-200"
                                    :class="{ '-rotate-180': slipCollapsed[index] }"
                                >
                                    <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div v-show="!slipCollapsed[index]" class="flex flex-col md:flex-row">
                        <dl class="flex flex-1 flex-col gap-5 p-5">
                            <!-- Payment info -->
                            <div>
                                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-zinc-400">Payment info</p>
                                <div class="grid grid-cols-2 gap-x-8 gap-y-4">
                                    <div v-if="slip.payer" class="flex flex-col gap-1">
                                        <dt class="text-xs text-zinc-400">Payer</dt>
                                        <dd class="text-sm font-medium text-zinc-900 dark:text-white">{{ slip.payer }}</dd>
                                    </div>
                                    <div v-if="slip.payee" class="flex flex-col gap-1">
                                        <dt class="text-xs text-zinc-400">Payee</dt>
                                        <dd class="text-sm font-medium text-zinc-900 dark:text-white">{{ slip.payee }}</dd>
                                    </div>
                                    <div v-if="slip.amount" class="flex flex-col gap-1">
                                        <dt class="text-xs text-zinc-400">Amount</dt>
                                        <dd class="text-sm font-bold text-red-600">{{ slip.currency?.toUpperCase() }} {{ slip.amount }}</dd>
                                    </div>
                                    <div v-if="slip.purpose" class="flex flex-col gap-1">
                                        <dt class="text-xs text-zinc-400">Purpose</dt>
                                        <dd class="text-sm text-zinc-900 dark:text-white">{{ slip.purpose }}</dd>
                                    </div>
                                </div>
                            </div>

                            <!-- Technical details -->
                            <div class="border-t border-zinc-100 pt-4 dark:border-zinc-800">
                                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-zinc-400">Technical details</p>
                                <div class="grid grid-cols-2 gap-x-8 gap-y-4">
                                    <div v-if="slip.recipientAccount" class="flex flex-col gap-1">
                                        <dt class="text-xs text-zinc-400">Recipient account</dt>
                                        <dd class="font-mono text-xs text-zinc-900 dark:text-white">{{ slip.recipientAccount }}</dd>
                                    </div>
                                    <div v-if="slip.payerAccount" class="flex flex-col gap-1">
                                        <dt class="text-xs text-zinc-400">Payer account</dt>
                                        <dd class="font-mono text-xs text-zinc-900 dark:text-white">{{ slip.payerAccount }}</dd>
                                    </div>
                                    <div v-if="slip.paymentCode" class="flex flex-col gap-1">
                                        <dt class="text-xs text-zinc-400">Payment code</dt>
                                        <dd class="text-sm text-zinc-900 dark:text-white">{{ slip.paymentCode }}</dd>
                                    </div>
                                    <div v-if="referenceDisplay(slip)" class="flex flex-col gap-1">
                                        <dt class="text-xs text-zinc-400">Reference</dt>
                                        <dd class="font-mono text-xs text-zinc-900 dark:text-white">{{ referenceDisplay(slip) }}</dd>
                                    </div>
                                </div>
                            </div>

                            <!-- IPS string -->
                            <div v-if="slip.ipsQrData" class="border-t border-zinc-100 pt-4 dark:border-zinc-800">
                                <div class="flex items-center justify-between gap-2">
                                    <dt class="text-xs font-semibold uppercase tracking-widest text-zinc-400">IPS string</dt>
                                    <button
                                        type="button"
                                        class="flex cursor-pointer items-center gap-1 rounded px-2 py-0.5 text-xs font-medium transition-colors"
                                        :class="copiedIndex === index ? 'text-green-600' : 'text-zinc-400 hover:text-zinc-900 dark:hover:text-white'"
                                        @click="copyIpsString(slip.ipsQrData!, index)"
                                    >
                                        <svg v-if="copiedIndex !== index" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-3.5">
                                            <path fill-rule="evenodd" d="M11 3.5A1.5 1.5 0 0 0 9.5 2h-6A1.5 1.5 0 0 0 2 3.5v9A1.5 1.5 0 0 0 3.5 14h6a1.5 1.5 0 0 0 1.5-1.5V12h1a1 1 0 0 0 1-1V5.5L11 3.5ZM10 12V9.5A1.5 1.5 0 0 0 8.5 8H6V4.5A.5.5 0 0 0 5.5 4h-2a.5.5 0 0 0-.5.5v8a.5.5 0 0 0 .5.5h6a.5.5 0 0 0 .5-.5Z" clip-rule="evenodd" />
                                        </svg>
                                        {{ copiedIndex === index ? '✓ Copied' : 'Copy' }}
                                    </button>
                                </div>
                                <dd class="mt-1 break-all font-mono text-xs text-zinc-400">{{ slip.ipsQrData }}</dd>
                            </div>
                        </dl>

                        <div class="flex flex-col items-center gap-4 border-t border-zinc-100 p-5 dark:border-zinc-800 md:w-56 md:border-l md:border-t-0">
                            <img
                                v-if="qrDataUrl"
                                :src="qrDataUrl"
                                width="160"
                                height="160"
                                class="rounded-sm"
                                alt="IPS QR code"
                            />
                            <div
                                v-else
                                class="flex size-40 items-center justify-center rounded-sm border border-dashed border-zinc-200 dark:border-zinc-700"
                            >
                                <span class="text-xs text-zinc-400">No QR</span>
                            </div>

                            <!-- Warning badge -->
                            <div class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 dark:border-amber-800/50 dark:bg-amber-950/30">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mt-px size-3.5 shrink-0 text-amber-600 dark:text-amber-400">
                                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                                </svg>
                                <p class="text-xs leading-snug text-amber-700 dark:text-amber-400">
                                    Always verify recipient and amount before scanning.
                                </p>
                            </div>

                            <!-- Download button -->
                            <button
                                v-if="qrDataUrl"
                                type="button"
                                class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-xs font-medium text-zinc-600 transition-colors hover:border-zinc-300 hover:text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:text-white"
                                @click="downloadQr(qrDataUrl, index)"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-3.5">
                                    <path d="M8.75 2.75a.75.75 0 0 0-1.5 0v5.69L5.03 6.22a.75.75 0 0 0-1.06 1.06l3.5 3.5a.75.75 0 0 0 1.06 0l3.5-3.5a.75.75 0 0 0-1.06-1.06L8.75 8.44V2.75Z" />
                                    <path d="M3.5 9.75a.75.75 0 0 0-1.5 0v1.5A2.75 2.75 0 0 0 4.75 14h6.5A2.75 2.75 0 0 0 14 11.25v-1.5a.75.75 0 0 0-1.5 0v1.5c0 .69-.56 1.25-1.25 1.25h-6.5c-.69 0-1.25-.56-1.25-1.25v-1.5Z" />
                                </svg>
                                Download QR
                            </button>
                        </div>
                    </div>
                </article>
            </section>

        </main>
    </div>
</template>
