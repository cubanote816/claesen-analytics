{{-- SessionKeeper: Auto-logout on inactivity + heartbeat to keep an active session alive. No modal. --}}
<div x-data="{
    lifetime: {{ $lifetime }},
    warningThreshold: {{ $warningThreshold }},
    heartbeatInterval: 60,
    lastActivity: Date.now(),

    init() {
        ['mousemove', 'keydown', 'scroll', 'click', 'touchstart'].forEach(event => {
            window.addEventListener(event, () => { this.lastActivity = Date.now(); }, { passive: true });
        });

        setInterval(() => {
            const inactive = Math.floor((Date.now() - this.lastActivity) / 1000);
            if (inactive >= this.lifetime) {
                @this.logout();
                setTimeout(() => { window.location.href = '/login'; }, 3000);
            }
        }, 5000);

        // Browser-side activity doesn't touch the server session by itself.
        // Only ping when there was real activity in the last cycle, so a
        // truly idle tab still expires normally instead of being kept alive
        // forever by this timer.
        setInterval(() => {
            const inactive = Math.floor((Date.now() - this.lastActivity) / 1000);
            if (inactive < this.heartbeatInterval) {
                fetch('{{ route('core.heartbeat') }}', { credentials: 'same-origin' });
            }
        }, this.heartbeatInterval * 1000);
    }
}" x-init="init()"></div>
