<?php

use App\Models\Event;
use App\Services\ScoreRelease;
use Livewire\Component;

/**
 * Invisible engine of a self-running demo event: while staged scores
 * remain, any viewer's browser polls this component, which releases the
 * due ones as real broadcast scores. No shell or queue worker needed.
 */
new class extends Component
{
    public Event $event;

    public int $pending = 0;

    public function mount(Event $event): void
    {
        $this->event = $event;
        $this->pending = app(ScoreRelease::class)->pending($event);
    }

    public function tick(): void
    {
        app(ScoreRelease::class)->tick($this->event);
        $this->pending = app(ScoreRelease::class)->pending($this->event);
    }
};
?>

<div>
    @if ($pending > 0)
        <span wire:poll.2s="tick" class="hidden"></span>
    @endif
</div>
