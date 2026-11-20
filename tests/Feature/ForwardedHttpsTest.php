<?php

it('makes the links of the admin website with https when the proxy says the person used https', function () {
    $response = $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'api.example.com', 'X-Forwarded-Port' => '443'])
        ->get('/admin/login')
        ->assertOk();

    $response->assertSee('https://api.example.com/css/filament/filament/app.css', false);
    $response->assertDontSee('http://api.example.com/css/filament', false);
});

it('keeps http when the proxy does not say anything', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertDontSee('https://localhost/css/filament', false);
});
