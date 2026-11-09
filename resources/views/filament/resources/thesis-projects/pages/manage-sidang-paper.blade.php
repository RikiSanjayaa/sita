<x-filament-panels::page>
    {{ $this->details }}
    {{ $this->skForm }}
    <div id="nilai-dari-berkas">
        {{ $this->scoreForm }}
    </div>
    @error('paper')
        <p role="alert" class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
    @enderror
</x-filament-panels::page>
