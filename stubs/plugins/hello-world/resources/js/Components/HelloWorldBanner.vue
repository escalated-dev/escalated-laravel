<script setup>
defineProps({
    message: {
        type: String,
        default: 'Hello from the Hello World plugin!',
    },
});
</script>

<template>
    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-800">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5 text-blue-600 dark:text-blue-400"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"
                    />
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-100">
                    Hello World Plugin
                </h3>
                <p class="text-sm text-blue-700 dark:text-blue-300">
                    {{ message }}
                </p>
            </div>
        </div>
    </div>
</template>
