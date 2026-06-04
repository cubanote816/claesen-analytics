<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Website\App\Enums\PublicationStatus;

class PublicationState extends Model
{
    protected $table = 'website_publication_states';

    protected $fillable = [
        'status',
        'dispatch_key',
        'dispatched_at',
        'pending_since',
        'last_accepted_at',
        'last_error',
        'last_error_at',
    ];

    protected $casts = [
        'status'           => PublicationStatus::class,
        'dispatched_at'    => 'datetime',
        'pending_since'    => 'datetime',
        'last_accepted_at' => 'datetime',
        'last_error_at'    => 'datetime',
    ];

    // ─── Singleton access ────────────────────────────────────────────────────

    public static function current(): static
    {
        $state = static::find(1);

        if (!$state) {
            $state = new static();
            $state->id = 1;
            $state->status = PublicationStatus::IDLE;
            $state->save();
        }

        return $state;
    }

    // ─── State transitions ────────────────────────────────────────────────────

    public function markPending(): void
    {
        $this->status      = PublicationStatus::PENDING;
        $this->last_error  = null;
        $this->last_error_at = null;

        // Set only on first change of the cycle; subsequent saves keep the
        // original timestamp so the admin can see when content first drifted.
        if (!$this->pending_since) {
            $this->pending_since = now();
        }

        $this->save();
    }

    public function recordDispatch(string $dispatchKey): void
    {
        $this->dispatch_key  = $dispatchKey;
        $this->dispatched_at = now();
        $this->save();
    }

    public function markAccepted(): void
    {
        $this->status           = PublicationStatus::ACCEPTED;
        $this->last_accepted_at = now();
        $this->dispatch_key     = null;
        $this->dispatched_at    = null;
        $this->pending_since    = null;
        $this->last_error       = null;
        $this->last_error_at    = null;
        $this->save();
    }

    public function markError(string $message): void
    {
        $this->status        = PublicationStatus::ERROR;
        $this->last_error    = mb_substr($message, 0, 2000);
        $this->last_error_at = now();
        $this->save();
    }
}
