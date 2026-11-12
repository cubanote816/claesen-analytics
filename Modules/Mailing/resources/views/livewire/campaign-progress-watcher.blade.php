{{-- CLA-680: polls only while a tracked send is pending; renders nothing visible. --}}
<div @if ($polling) wire:poll.5s="check" @endif></div>
