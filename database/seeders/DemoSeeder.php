<?php

namespace Database\Seeders;

use App\Services\DemoEvent;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * One self-running 10-minute demo event, same as pressing
     * "Start event" on the home screen.
     */
    public function run(): void
    {
        app(DemoEvent::class)->create('Demo Trial', minutes: 10);
    }
}
