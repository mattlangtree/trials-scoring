<?php

use App\Models\SectionClaim;

it('lets an observer claim a section with a valid code', function () {
    $world = trialsWorld();

    $response = $this->postJson(api('/observer/claims'), [
        'code' => $world['section']->claim_code,
        'device_id' => 'observer-phone-1',
        'observer_name' => 'Karen Mills',
    ]);

    $response->assertCreated()
        ->assertJsonPath('section.number', 6)
        ->assertJsonStructure(['claim_token', 'section', 'event', 'observer_name', 'claimed_at']);

    expect(SectionClaim::where('device_id', 'observer-phone-1')->sole())
        ->observer_name->toBe('Karen Mills')
        ->token->toBe($response->json('claim_token'));
});

it('accepts a claim code case-insensitively and ignoring whitespace', function () {
    $world = trialsWorld();

    $this->postJson(api('/observer/claims'), [
        'code' => ' '.strtolower($world['section']->claim_code).' ',
        'device_id' => 'observer-phone-1',
        'observer_name' => 'Karen Mills',
    ])->assertCreated();
});

it('rejects an unknown claim code', function () {
    trialsWorld();

    $this->postJson(api('/observer/claims'), [
        'code' => 'XXX',
        'device_id' => 'observer-phone-1',
        'observer_name' => 'Karen Mills',
    ])->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('allows multiple observers to claim the same section', function () {
    $world = trialsWorld();

    foreach (['Karen Mills', 'Bob Tanner'] as $i => $observer) {
        $this->postJson(api('/observer/claims'), [
            'code' => $world['section']->claim_code,
            'device_id' => "device-$i",
            'observer_name' => $observer,
        ])->assertCreated();
    }

    expect($world['section']->claims()->count())->toBe(3); // incl. the seeded claim
});
