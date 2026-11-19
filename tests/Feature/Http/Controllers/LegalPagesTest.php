<?php

it('shows the public legal pages without signing in', function (string $route, string $text) {
    $this->get(route($route))->assertOk()->assertSee($text);
})->with([
    'privacy policy' => ['legal.privacy', 'What we collect'],
    'terms of use' => ['legal.terms', 'What the app does'],
    'account deletion' => ['legal.account-deletion', 'What is erased'],
]);

it('shows the contact email on the page when one is set', function () {
    config(['legal.contact_email' => 'help@example.com']);

    $this->get(route('legal.privacy'))->assertSee('mailto:help@example.com', false);
});

it('points to the Google Play page when no contact email is set', function () {
    config(['legal.contact_email' => null]);

    $this->get(route('legal.account-deletion'))
        ->assertSee('Google Play page of the app')
        ->assertDontSee('mailto:', false);
});
