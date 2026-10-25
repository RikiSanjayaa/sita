<x-filament-widgets::widget class="sita-admin-actions">
    @php($items = $this->getItems())

    <section aria-labelledby="admin-action-heading">
        <header class="sita-dashboard-heading">
            <h2 id="admin-action-heading">Perlu tindakan</h2>
            <p>Urut dari yang paling mendesak.</p>
        </header>

        @if ($items->isEmpty())
            <div class="sita-dashboard-clear">
                <x-filament::icon icon="heroicon-o-check-circle" aria-hidden="true" />
                <div>
                    <h3>Semua beres</h3>
                    <p>Tidak ada yang perlu ditindak. Semua antrean kosong.</p>
                </div>
            </div>
        @else
            <ul class="sita-action-queue" role="list">
                @foreach ($items as $item)
                    <li
                        @class(['sita-action-queue-row sita-action-card', 'sita-action-card-featured' => $loop->first])
                        data-tone="{{ $item['color'] }}"
                        wire:key="queue-{{ $item['key'] }}"
                    >
                        <div class="sita-action-card-header">
                            <span class="sita-action-card-icon"><x-filament::icon :icon="$item['icon']" aria-hidden="true" /></span>
                            <h3>{{ $item['label'] }}</h3>
                            <span class="sita-action-card-count" aria-label="{{ $item['count'] }} {{ \App\Support\AcademicTerminology::neutral()['finalWorkLower'] }}">{{ $item['count'] }}</span>
                        </div>
                        @if ($loop->first)
                            <p class="sita-action-queue-description">{{ $item['description'] }}</p>
                            @if (filled($item['samples']))
                                <p class="sita-action-queue-samples">{{ $item['samples'] }}</p>
                            @endif
                        @endif
                        <x-filament::button
                            tag="a"
                            :href="$item['url']"
                            :color="$loop->first ? 'primary' : 'gray'"
                            icon="heroicon-m-arrow-right"
                            icon-position="after"
                        >
                            {{ $item['action'] }}
                        </x-filament::button>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-filament-widgets::widget>
