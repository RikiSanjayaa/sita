<x-filament-panels::page.simple>
    <div class="rounded-3xl border border-gray-200/80 bg-white/95 p-5 shadow-sm dark:border-white/10 dark:bg-gray-900/90 sm:p-8">
        <div class="mb-6 space-y-3 border-b border-gray-200/80 pb-5 dark:border-white/10">
            <span class="inline-flex rounded-full border border-primary-200 bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-300">
                SiTA Universitas Bumigora
            </span>
            <div class="space-y-1">
                <p class="text-sm font-medium text-gray-950 dark:text-white">
                    Portal administrasi tugas akhir
                </p>
                <p class="text-sm leading-6 text-gray-500 dark:text-gray-400">
                    Masuk untuk mengelola data akademik, workflow tugas akhir, dan operasional admin dengan tampilan yang lebih ringkas di layar kecil.
                </p>
            </div>
        </div>

        {{ $this->content }}
    </div>
</x-filament-panels::page.simple>
