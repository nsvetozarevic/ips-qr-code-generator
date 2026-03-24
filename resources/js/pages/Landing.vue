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
    payee: string | null;
    recipientAccount: string | null;
    amount: string | null;
    currency: string | null;
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

async function buildResults(slips: SlipPayload[]): Promise<SlipResult[]> {
    return Promise.all(
        slips.map(async (slip) => {
            const qrDataUrl = slip.ipsQrData
                ? await QRCode.toDataURL(slip.ipsQrData, { width: 240, margin: 2, errorCorrectionLevel: 'M' })
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
    <div class="min-h-screen bg-background">
        <Head title="IPS QR Parser" />

        <main class="mx-auto flex min-h-screen w-full max-w-2xl flex-col gap-8 p-6">
            <section class="w-full rounded-xl border bg-card p-6 shadow-sm">
                <h1 class="text-2xl font-semibold tracking-tight">Upload payment slip PDF</h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Upload your payment slip and we will parse it to prepare IPS QR data.
                </p>

                <form class="mt-6 grid gap-4" @submit.prevent="submitPdf">
                    <div class="grid gap-2">
                        <Label for="pdf">PDF file</Label>
                        <Input
                            id="pdf"
                            name="pdf"
                            type="file"
                            accept="application/pdf"
                            required
                            :disabled="processing"
                        />
                        <InputError :message="pdfError ?? undefined" />
                    </div>

                    <Button type="submit" :disabled="processing" class="w-fit">
                        <Spinner v-if="processing" />
                        Parse PDF
                    </Button>
                </form>
            </section>

            <section v-if="processing" class="w-full space-y-4">
                <div
                    v-for="i in 2"
                    :key="i"
                    class="h-48 w-full animate-pulse rounded-xl border bg-muted"
                />
            </section>

            <section v-else-if="results.length > 0" class="w-full space-y-6">
                <h2 class="text-lg font-semibold tracking-tight">Parsed slips</h2>

                <article
                    v-for="({ slip, qrDataUrl }, index) in results"
                    :key="index"
                    class="rounded-xl border bg-card p-6 shadow-sm"
                >
                    <p class="text-sm font-medium text-muted-foreground">Slip {{ index + 1 }}</p>

                    <dl class="mt-4 grid gap-2 text-sm">
                        <div v-if="slip.payee" class="grid gap-0.5">
                            <dt class="text-muted-foreground">Payee</dt>
                            <dd class="font-medium">{{ slip.payee }}</dd>
                        </div>
                        <div v-if="slip.recipientAccount" class="grid gap-0.5">
                            <dt class="text-muted-foreground">Recipient account</dt>
                            <dd class="font-mono text-xs">{{ slip.recipientAccount }}</dd>
                        </div>
                        <div v-if="slip.amount" class="grid gap-0.5">
                            <dt class="text-muted-foreground">Amount</dt>
                            <dd>{{ slip.currency?.toUpperCase() }} {{ slip.amount }}</dd>
                        </div>
                    </dl>

                    <div v-if="slip.ipsQrData && qrDataUrl" class="mt-6 flex flex-col items-start gap-3">
                        <p class="text-sm font-medium">IPS QR</p>
                        <img
                            :src="qrDataUrl"
                            width="240"
                            height="240"
                            class="rounded-md border bg-white p-2"
                            alt="IPS QR code"
                        />
                        <p class="max-w-full break-all font-mono text-xs text-muted-foreground">
                            {{ slip.ipsQrData }}
                        </p>
                    </div>
                    <p v-else class="mt-4 text-sm text-amber-600 dark:text-amber-500">
                        Could not build IPS QR string (check account checksum and required fields).
                    </p>
                </article>
            </section>
        </main>
    </div>
</template>
