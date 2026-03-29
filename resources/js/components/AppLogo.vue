<script setup lang="ts">
defineProps<{
    class?: string;
}>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 20 20"
        fill="currentColor"
        aria-hidden="true"
        :class="$props.class"
    >
        <!-- Top-left finder -->
        <path fill-rule="evenodd" d="M0 0h6v6H0V0zm1 1h4v4H1V1z" />
        <rect x="2" y="2" width="2" height="2" />

        <!-- Top-right finder -->
        <path fill-rule="evenodd" d="M14 0h6v6h-6V0zm1 1h4v4h-4V1z" />
        <rect x="16" y="2" width="2" height="2" />

        <!-- Bottom-left finder -->
        <path fill-rule="evenodd" d="M0 14h6v6H0v-6zm1 1h4v4H1v-4z" />
        <rect x="2" y="16" width="2" height="2" />

        <!-- Timing dots (top) -->
        <rect x="8" y="1" width="1.5" height="1.5" />
        <rect x="10.5" y="1" width="1.5" height="1.5" />

        <!-- Timing dots (left) -->
        <rect x="1" y="8" width="1.5" height="1.5" />
        <rect x="1" y="10.5" width="1.5" height="1.5" />

        <!-- Center data area -->
        <rect x="8" y="8" width="2" height="2" />
        <rect x="11" y="8" width="1.5" height="1.5" />
        <rect x="8" y="11" width="1.5" height="1.5" />
        <rect x="10.5" y="11" width="2" height="2" />

        <!-- Right data area -->
        <rect x="14" y="8" width="1.5" height="1.5" />
        <rect x="16.5" y="8" width="1.5" height="1.5" />
        <rect x="14" y="10.5" width="3" height="1.5" />

        <!-- Bottom data area -->
        <rect x="8" y="14" width="1.5" height="1.5" />
        <rect x="10.5" y="14" width="2" height="1.5" />
        <rect x="8" y="16.5" width="3" height="1.5" />

        <!-- Bottom-right corner dot -->
        <rect x="14" y="14" width="2" height="2" />
        <rect x="17" y="16" width="1.5" height="1.5" />
    </svg>
</template>
