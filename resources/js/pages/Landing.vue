<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import { ref } from 'vue';
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
const results = ref<SlipResult[]>([]);
const pdfError = ref<string | null>(null);

function sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

function xsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
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
    <div class="min-h-screen bg-background text-foreground">
        <Head title="IPS QR Generator" />

        <header class="border-b border-border">
            <div class="mx-auto flex max-w-4xl items-center px-6 py-4">
                <div class="flex items-center gap-2.5">
                    <div class="size-5 rounded-sm bg-foreground" />
                    <span class="text-sm font-semibold tracking-tight">IPS QR</span>
                </div>
            </div>
        </header>

        <main class="mx-auto flex max-w-4xl flex-col gap-16 px-6 py-14">

            <section class="flex flex-col gap-8">
                <div>
                    <h1 class="text-3xl font-semibold tracking-tight">
                        Parse payment slip,<br />get IPS QR code.
                    </h1>
                    <p class="mt-3 max-w-sm text-sm text-muted-foreground">
                        Upload a digital PDF payment slip. We'll extract the data and generate a scannable IPS QR code instantly.
                    </p>
                </div>

                <form class="flex max-w-xs flex-col gap-4" @submit.prevent="submitPdf">
                    <div class="flex flex-col gap-2">
                        <Label for="pdf" class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
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

                    <Button type="submit" :disabled="processing" class="w-fit">
                        <Spinner v-if="processing" />
                        {{ processing ? 'Parsing…' : 'Generate QR code' }}
                    </Button>
                </form>
            </section>

            <section v-if="processing" class="flex flex-col gap-4">
                <div
                    v-for="i in 2"
                    :key="i"
                    class="overflow-hidden rounded-xl border border-border"
                >
                    <div class="flex items-center justify-between border-b border-border px-5 py-3">
                        <div class="h-2.5 w-12 animate-pulse rounded-full bg-muted" />
                        <div class="h-2.5 w-16 animate-pulse rounded-full bg-muted" />
                    </div>
                    <div class="flex flex-col md:flex-row">
                        <div class="flex flex-1 flex-col gap-5 p-5">
                            <div class="grid grid-cols-2 gap-x-8 gap-y-4">
                                <div v-for="j in 6" :key="j" class="flex flex-col gap-1.5">
                                    <div class="h-2 w-12 animate-pulse rounded-full bg-muted" />
                                    <div class="h-2.5 w-28 animate-pulse rounded-full bg-muted" />
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-center border-t border-border p-5 md:w-52 md:border-l md:border-t-0">
                            <div class="size-36 animate-pulse rounded bg-muted" />
                        </div>
                    </div>
                </div>
            </section>

            <section v-else-if="results.length > 0" class="flex flex-col gap-4">
                <div class="flex items-center gap-3">
                    <span class="shrink-0 text-xs font-medium uppercase tracking-widest text-muted-foreground">
                        {{ results.length }} {{ results.length === 1 ? 'slip' : 'slips' }} parsed
                    </span>
                    <div class="h-px flex-1 bg-border" />
                </div>

                <article
                    v-for="({ slip, qrDataUrl }, index) in results"
                    :key="index"
                    class="overflow-hidden rounded-xl border border-border"
                >
                    <div class="flex items-center justify-between border-b border-border px-5 py-3">
                        <span class="text-xs font-medium uppercase tracking-widest text-muted-foreground">
                            Slip {{ index + 1 }}
                        </span>
                        <span
                            v-if="slip.ipsQrData"
                            class="text-xs font-medium text-foreground"
                        >
                            QR ready
                        </span>
                        <span v-else class="text-xs text-amber-600 dark:text-amber-400">
                            QR unavailable
                        </span>
                    </div>

                    <div class="flex flex-col md:flex-row">
                        <dl class="flex flex-1 flex-col gap-5 p-5">
                            <div class="grid grid-cols-2 gap-x-8 gap-y-4">
                                <div v-if="slip.payer" class="flex flex-col gap-1">
                                    <dt class="text-xs text-muted-foreground">Payer</dt>
                                    <dd class="text-sm font-medium">{{ slip.payer }}</dd>
                                </div>
                                <div v-if="slip.payee" class="flex flex-col gap-1">
                                    <dt class="text-xs text-muted-foreground">Payee</dt>
                                    <dd class="text-sm font-medium">{{ slip.payee }}</dd>
                                </div>
                                <div v-if="slip.amount" class="flex flex-col gap-1">
                                    <dt class="text-xs text-muted-foreground">Amount</dt>
                                    <dd class="text-sm font-medium">{{ slip.currency?.toUpperCase() }} {{ slip.amount }}</dd>
                                </div>
                                <div v-if="slip.paymentCode" class="flex flex-col gap-1">
                                    <dt class="text-xs text-muted-foreground">Payment code</dt>
                                    <dd class="text-sm">{{ slip.paymentCode }}</dd>
                                </div>
                                <div v-if="slip.recipientAccount" class="flex flex-col gap-1">
                                    <dt class="text-xs text-muted-foreground">Recipient account</dt>
                                    <dd class="font-mono text-xs">{{ slip.recipientAccount }}</dd>
                                </div>
                                <div v-if="slip.payerAccount" class="flex flex-col gap-1">
                                    <dt class="text-xs text-muted-foreground">Payer account</dt>
                                    <dd class="font-mono text-xs">{{ slip.payerAccount }}</dd>
                                </div>
                                <div v-if="slip.purpose" class="col-span-2 flex flex-col gap-1">
                                    <dt class="text-xs text-muted-foreground">Purpose</dt>
                                    <dd class="text-sm">{{ slip.purpose }}</dd>
                                </div>
                                <div v-if="referenceDisplay(slip)" class="col-span-2 flex flex-col gap-1">
                                    <dt class="text-xs text-muted-foreground">Reference</dt>
                                    <dd class="font-mono text-xs">{{ referenceDisplay(slip) }}</dd>
                                </div>
                            </div>

                            <div v-if="slip.ipsQrData" class="border-t border-border pt-4">
                                <dt class="text-xs text-muted-foreground">IPS string</dt>
                                <dd class="mt-1 break-all font-mono text-xs text-muted-foreground">{{ slip.ipsQrData }}</dd>
                            </div>
                        </dl>

                        <div class="flex flex-col items-center justify-start gap-4 border-t border-border p-5 md:w-52 md:border-l md:border-t-0">
                            <img
                                v-if="qrDataUrl"
                                :src="qrDataUrl"
                                width="160"
                                height="160"
                                class="rounded-sm dark:invert"
                                alt="IPS QR code"
                            />
                            <div
                                v-else
                                class="flex size-40 items-center justify-center rounded-sm border border-dashed border-border"
                            >
                                <span class="text-xs text-muted-foreground">No QR</span>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

        </main>
    </div>
</template>
