@php
    $url = $entry->getState();
@endphp

@if (!empty($url))
    <div x-data="{ open: false }">
        <img
            src="{{ $url }}"
            alt="Inspection answer photo"
            style="width:120px;height:120px;object-fit:cover;border-radius:0.5rem;cursor:pointer;display:block;"
            @click="open = true"
        />

        <div
            x-show="open"
            x-transition.opacity
            @click.self="open = false"
            style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.85);"
        >
            <button
                @click="open = false"
                style="position:absolute;top:1.25rem;right:1.25rem;color:white;font-size:1.75rem;line-height:1;background:none;border:none;cursor:pointer;padding:0.5rem;"
                title="Sluiten"
                aria-label="Sluiten"
            >&times;</button>

            <img
                src="{{ $url }}"
                alt="Inspection answer photo"
                @click.stop
                style="max-width:90vw;max-height:90vh;object-fit:contain;border-radius:0.75rem;box-shadow:0 25px 50px rgba(0,0,0,0.5);"
            />
        </div>
    </div>
@endif
