<x-filament-panels::page>
    @if(in_array($this->record->status, ['pending', 'running']))
        <div wire:poll.3s>
            {{ $this->content }}
        </div>
    @else
        {{ $this->content }}
    @endif
</x-filament-panels::page>
