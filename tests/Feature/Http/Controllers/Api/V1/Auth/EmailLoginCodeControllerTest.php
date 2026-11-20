<?php

use App\Mail\LoginCodeMail;
use App\Models\EmailLoginCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(fn () => Mail::fake());

it('returns 202 and emails a code to the address', function () {
    $this->postJson(route('api.v1.auth.email-code.store'), ['email' => 'Maria.Santos@Gmail.com '])
        ->assertAccepted()
        ->assertExactJson(['message' => 'We sent a code to your email.']);

    $sent = null;

    Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use (&$sent): bool {
        $sent = $mail;

        return $mail->hasTo('maria.santos@gmail.com')
            && preg_match('/^\d{6}$/', $mail->code) === 1
            && $mail->minutes === 10;
    });

    $record = EmailLoginCode::sole();

    expect($record->email)->toBe('maria.santos@gmail.com');
    expect($record->expires_at->isFuture())->toBeTrue();
    expect($record->getRawOriginal('code'))->not->toBe($sent->code);
    expect(Hash::check($sent->code, $record->getRawOriginal('code')))->toBeTrue();
});

it('puts the code in the subject and says how long it works', function () {
    $this->postJson(route('api.v1.auth.email-code.store'), ['email' => 'maria@example.com'])->assertAccepted();

    Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail): bool {
        $mail->assertHasSubject(config('app.name').' code: '.$mail->code);
        $mail->assertSeeInText('It works for 10 minutes');

        return true;
    });
});

it('returns 422 when the email is missing or not an email', function (array $body) {
    $this->postJson(route('api.v1.auth.email-code.store'), $body)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    Mail::assertNothingSent();
    $this->assertDatabaseCount('email_login_codes', 0);
})->with([
    'missing' => [[]],
    'not an email' => [['email' => 'maria']],
    'no domain' => [['email' => 'maria@']],
]);

it('replaces the earlier code and leaves the codes of other addresses alone', function () {
    $earlier = EmailLoginCode::factory()->create(['email' => 'maria@example.com']);
    $other = EmailLoginCode::factory()->create(['email' => 'jose@example.com']);

    $this->postJson(route('api.v1.auth.email-code.store'), ['email' => 'maria@example.com'])->assertAccepted();

    $this->assertModelMissing($earlier);
    $this->assertModelExists($other);
    $this->assertDatabaseCount('email_login_codes', 2);
});

it('returns 429 when the same address asks twice within a minute, even when typed in capitals', function () {
    $this->postJson(route('api.v1.auth.email-code.store'), ['email' => 'maria@example.com'])->assertAccepted();

    $this->postJson(route('api.v1.auth.email-code.store'), ['email' => 'MARIA@example.com'])->assertTooManyRequests();
});

it('returns 503 and removes the code when the mail server cannot send it', function () {
    Mail::shouldReceive('to->send')->andThrow(new TransportException('connection refused'));

    $this->postJson(route('api.v1.auth.email-code.store'), ['email' => 'maria@example.com'])
        ->assertStatus(503)
        ->assertJson(['message' => 'We could not send the email. Please try again in a moment.']);

    $this->assertDatabaseCount('email_login_codes', 0);
});
