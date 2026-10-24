<x-filament-widgets::widget>
    @php
        $items = $this->getItems();
    @endphp

    <x-filament::section
        heading="Perlu tindakan"
        description="Urut dari yang paling mendesak. Setiap baris membuka daftar yang sudah difilter."
        :compact="true"
    >
        @if ($items->isEmpty())
            <div class="flex items-center gap-3 text-sm text-gray-600 dark:text-gray-300">
                <x-filament::icon icon="heroicon-m-check-circle" class="size-5 text-success-600 dark:text-success-400" />
                <p>Tidak ada yang perlu ditindak. Semua antrean kosong.</p>
            </div>
        @else
            <ul class="sita-action-queue" role="list">
                @foreach ($items as $item)
                    <li class="sita-action-queue-row" wire:key="queue-{{ $item['key'] }}">
                        <x-filament::icon :icon="$item['icon']" class="sita-action-queue-icon" />

                        <div class="sita-action-queue-text">
                            <p class="sita-action-queue-label">{{ $item['label'] }}</p>
                            <p class="sita-action-queue-description">{{ $item['description'] }}</p>
                            <p class="sita-action-queue-samples">{{ $item['samples'] }}</p>
                        </div>

                        <div class="sita-action-queue-actions">
                            <x-filament::badge :color="$item['color']" size="lg">
                                {{ $item['count'] }}
                            </x-filament::badge>
                            <x-filament::button
                                tag="a"
                                :href="$item['url']"
                                size="sm"
                                color="gray"
                                icon="heroicon-m-arrow-right"
                                icon-position="after"
                            >
                                {{ $item['action'] }}
                            </x-filament::button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
