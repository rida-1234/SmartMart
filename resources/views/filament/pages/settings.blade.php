<x-filament-panels::page>
    <form wire:submit="save" style="display: grid; gap: 1rem;">
        {{ $this->form }}
        <div>
            <x-filament::button type="submit">Save settings</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
