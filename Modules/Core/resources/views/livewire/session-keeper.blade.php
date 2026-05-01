{{-- SessionKeeper: Auto-logout on inactivity. No modal. --}}
<div x-data="{
    lifetime: {{ $lifetime }},
    warningThreshold: {{ $warningThreshold }},
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
    }
}" x-init="init()"></div>
