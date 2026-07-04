<?php

it('returns an event with classes and section numbers but no claim codes', function () {
    $world = trialsWorld();

    $this->getJson(api())
        ->assertOk()
        ->assertJsonPath('event.id', $world['event']->id)
        ->assertJsonPath('classes.0.laps', 2)
        ->assertJsonMissing(['claim_code' => $world['section']->claim_code]);
});

it('404s for an unknown event', function () {
    $this->getJson('/api/v1/events/999')->assertNotFound();
});

it('renders the home page with and without events', function () {
    $this->get('/')->assertOk();

    trialsWorld();
    $this->get('/')->assertOk();
});

it('renders the event dashboard pages', function (string $path) {
    $world = trialsWorld();

    $this->get('/events/'.$world['event']->id.$path)->assertOk();
})->with(['', '/standings', '/sections', '/riders']);
