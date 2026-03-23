<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import ParseUploadedPdfController from '@/actions/App/Http/Controllers/ParseUploadedPdfController';
</script>

<template>
    <div class="min-h-screen bg-background">
        <Head title="IPS QR Parser" />

        <main class="mx-auto flex min-h-screen w-full max-w-2xl items-center p-6">
            <section class="w-full rounded-xl border bg-card p-6 shadow-sm">
                <h1 class="text-2xl font-semibold tracking-tight">Upload payment slip PDF</h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Upload your payment slip and we will parse it to prepare IPS QR data.
                </p>

                <Form
                    v-bind="ParseUploadedPdfController.form()"
                    v-slot="{ errors, processing }"
                    class="mt-6 grid gap-4"
                >
                    <div class="grid gap-2">
                        <Label for="pdf">PDF file</Label>
                        <Input
                            id="pdf"
                            name="pdf"
                            type="file"
                            accept="application/pdf"
                            required
                        />
                        <InputError :message="errors.pdf" />
                    </div>

                    <Button type="submit" :disabled="processing" class="w-fit">
                        <Spinner v-if="processing" />
                        Parse PDF
                    </Button>
                </Form>
            </section>
        </main>
    </div>
</template>
