<x-filament-panels::page>
    @if($this->record->status === 'running')
        <div wire:poll.3s>
            {{ $this->content }}
        </div>
    @else
        {{ $this->content }}
    @endif
</x-filament-panels::page>
