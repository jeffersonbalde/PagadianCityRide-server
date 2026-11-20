<?php

use App\Exceptions\SmsDeliveryFailed;
use App\Models\EmergencyContact;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use Laravel\Sanctum\Sanctum;

describe('index', function () {
    it('returns 401 without a token', function () {
        $this->getJson(route('api.v1.emergency-contacts.index'))->assertUnauthorized();
    });

    it('lists only the contacts of the signed in person, oldest first', function () {
        $user = User::factory()->create();
        $first = EmergencyContact::factory()->for($user)->create(['name' => 'Ana', 'relationship' => 'Sister', 'phone' => '+639171112222']);
        EmergencyContact::factory()->for($user)->create(['name' => 'Jose']);
        EmergencyContact::factory()->create(['name' => 'Someone else']);

        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.emergency-contacts.index'))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0', ['id' => $first->id, 'name' => 'Ana', 'relationship' => 'Sister', 'phone' => '+639171112222'])
            ->assertJsonPath('1.name', 'Jose');
    });
});

describe('store', function () {
    it('adds a contact with the number in the +639XXXXXXXXX format', function () {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.emergency-contacts.store'), ['name' => 'Ana', 'relationship' => 'Sister', 'phone' => '0917 111 2222'])
            ->assertCreated()
            ->assertJsonPath('phone', '+639171112222')
            ->assertJsonPath('name', 'Ana');

        expect($user->emergencyContacts()->sole()->relationship)->toBe('Sister');
    });

    it('lets the relationship stay empty', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.emergency-contacts.store'), ['name' => 'Ana', 'phone' => '09171112222'])
            ->assertCreated()
            ->assertJsonPath('relationship', null);
    });

    it('returns 422 for a missing name, a bad number or a number that is already in the list', function (array $payload, string $field) {
        $user = User::factory()->create();
        EmergencyContact::factory()->for($user)->create(['phone' => '+639171112222']);

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.emergency-contacts.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'no name' => [['phone' => '09173334444'], 'name'],
        'short name' => [['name' => 'A', 'phone' => '09173334444'], 'name'],
        'bad number' => [['name' => 'Ana', 'phone' => '12345'], 'phone'],
        'same number' => [['name' => 'Ana', 'phone' => '0917 111 2222'], 'phone'],
    ]);

    it('stops at the most contacts one person can have', function () {
        $user = User::factory()->create();
        EmergencyContact::factory()->count(EmergencyContact::MAX_PER_USER)->for($user)->create();

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.emergency-contacts.store'), ['name' => 'Ana', 'phone' => '09171112222'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        expect($user->emergencyContacts()->count())->toBe(EmergencyContact::MAX_PER_USER);
    });
});

describe('destroy', function () {
    it('removes a contact', function () {
        $user = User::factory()->create();
        $contact = EmergencyContact::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->deleteJson(route('api.v1.emergency-contacts.destroy', $contact))->assertNoContent();

        $this->assertModelMissing($contact);
    });

    it('does not remove the contact of another person', function () {
        $contact = EmergencyContact::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson(route('api.v1.emergency-contacts.destroy', $contact))->assertNotFound();

        $this->assertModelExists($contact);
    });
});

describe('alert', function () {
    it('returns 401 without a token', function () {
        $this->postJson(route('api.v1.emergency-alert.store'), ['latitude' => 7.82, 'longitude' => 123.44])->assertUnauthorized();
    });

    it('texts every contact with the name and a map link, and lists who was texted', function () {
        config(['services.sms.prefix' => 'PagCityRide']);
        $user = User::factory()->create(['name' => 'Maria Santos']);
        EmergencyContact::factory()->for($user)->create(['name' => 'Ana', 'phone' => '+639171112222']);
        EmergencyContact::factory()->for($user)->create(['name' => 'Jose', 'phone' => '+639173334444']);
        EmergencyContact::factory()->create(['phone' => '+639175556666']);

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.emergency-alert.store'), ['latitude' => 7.8231, 'longitude' => 123.4402])
            ->assertOk()
            ->assertJsonPath('*.name', ['Ana', 'Jose']);

        expect(sms()->sent())->toHaveCount(2);
        expect(sms()->sent()[0]['phone'])->toBe('+639171112222');
        expect(sms()->sent()[0]['message'])
            ->toStartWith('PagCityRide: ')
            ->toContain('Maria Santos')
            ->toContain('https://maps.google.com/?q=7.8231,123.4402');
    });

    it('goes on to the next contact when one cannot be reached', function () {
        $user = User::factory()->create();
        EmergencyContact::factory()->for($user)->create(['name' => 'Ana', 'phone' => '+639171112222']);
        EmergencyContact::factory()->for($user)->create(['name' => 'Jose', 'phone' => '+639173334444']);

        $sent = [];

        $this->app->instance(SmsGateway::class, new class($sent) implements SmsGateway
        {
            public function __construct(public array &$sent) {}

            public function send(string $phone, string $message): void
            {
                if ($phone === '+639171112222') {
                    throw SmsDeliveryFailed::because(new RuntimeException('down'));
                }

                $this->sent[] = $phone;
            }
        });

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.emergency-alert.store'), ['latitude' => 7.82, 'longitude' => 123.44])
            ->assertOk()
            ->assertJsonPath('*.name', ['Jose']);

        expect($sent)->toBe(['+639173334444']);
    });

    it('returns 422 when the person has no contact yet', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(route('api.v1.emergency-alert.store'), ['latitude' => 7.82, 'longitude' => 123.44])
            ->assertUnprocessable();

        expect(sms()->sent())->toBeEmpty();
    });

    it('returns 422 without a location', function () {
        $user = User::factory()->create();
        EmergencyContact::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.emergency-alert.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    });
});
