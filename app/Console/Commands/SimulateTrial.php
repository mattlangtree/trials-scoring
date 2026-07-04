<?php

namespace App\Console\Commands;

use App\Models\Score;
use App\Services\DemoEvent;
use App\Services\ScoreRelease;
use Illuminate\Console\Command;

class SimulateTrial extends Command
{
    protected $signature = 'trial:simulate
        {--name= : Event name (generated if omitted)}
        {--minutes=10 : Timespan the simulated event runs over}
        {--riders=60 : Total riders in the event}';

    protected $description = 'Create a self-running demo event (same as the home-screen button) and drive its score releases from the terminal.';

    public function handle(DemoEvent $demo, ScoreRelease $release): int
    {
        $event = $demo->create(
            $this->option('name'),
            (int) $this->option('minutes'),
            (int) $this->option('riders'),
        );

        $total = $release->pending($event);

        $this->components->info(sprintf(
            'Simulating "%s": %d riders / %d scores over %s minutes — watch %s',
            $event->name, $event->riders()->count(), $total,
            $this->option('minutes'), route('event.overview', $event),
        ));
        $this->line('Scores also release automatically while anyone has the event open in a browser.');

        $lastReport = microtime(true);
        while ($release->pending($event) > 0) {
            $release->tick($event);
            sleep(1);

            if (microtime(true) - $lastReport >= 30) {
                $lastReport = microtime(true);
                $done = $total - $release->pending($event);
                $this->line(sprintf('%d/%d scores released', $done, $total));
            }
        }

        $this->components->info('Finished.');
        $this->podium($event);

        return self::SUCCESS;
    }

    private function podium($event): void
    {
        foreach ($event->riderClasses()->orderBy('name')->get() as $class) {
            $top = $class->riders()
                ->withSum(['scores as points' => fn ($q) => $q->where('status', Score::STATUS_OFFICIAL)], 'points')
                ->withCount(['scores as cleans' => fn ($q) => $q->where('status', Score::STATUS_OFFICIAL)->where('points', 0)])
                ->get()
                ->sortBy([['points', 'asc'], ['cleans', 'desc']])
                ->take(3)
                ->values();

            if ($top->isEmpty()) {
                continue;
            }

            $this->components->twoColumnDetail(
                "<options=bold>{$class->name}</>",
                $top->map(fn ($r, $i) => ($i + 1).'. #'.$r->rider_number.' '.$r->name.' ('.($r->points ?? 0).')')->join('  ·  '),
            );
        }
    }
}
