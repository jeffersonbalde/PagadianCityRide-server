<?php

use App\AlertType;
use App\Models\User;
use App\Notifications\Alert;
use Laravel\Sanctum\Sanctum;

describe('one alert', function () {
    it('deletes the alert of the person', function () {
        $passenger = User::factory()->create();
        $passenger->notify(new Alert(AlertType::RideExpired));
        $passenger->notify(new Alert(AlertType::RideAccepted));
        $alert = $passenger->notifications()->where('data->type', AlertType::RideExpired->value)->sole();

        Sanctum::actingAs($passenger);

        $this->deleteJson(route('api.v1.alerts.destroy', $alert->id))->assertNoContent();

        expect($passenger->notifications()->count())->toBe(1);
        expect($passenger->notifications()->where('id', $alert->id)->exists())->toBeFalse();
    });

    it('lowers the unread count when an alert that was not read is deleted', function () {
        $passenger = User::factory()->create();
        $passenger->notify(new Alert(AlertType::RideExpired));

        Sanctum::actingAs($passenger);

        $this->deleteJson(route('api.v1.alerts.destroy', $passenger->notifications()->sole()->id))->assertNoContent();

        $this->getJson(route('api.v1.alerts.index'))->assertJsonPath('unread_count', 0)->assertJsonCount(0, 'data');
    });

    it('is a 404 for an alert of someone else, and the alert stays', function () {
        $other = User::factory()->create();
        $other->notify(new Alert(AlertType::RideExpired));

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson(route('api.v1.alerts.destroy', $other->notifications()->sole()->id))->assertNotFound();

        expect($other->notifications()->count())->toBe(1);
    });

    it('is a 404 for an alert that does not exist', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson(route('api.v1.alerts.destroy', 'nothing-here'))->assertNotFound();
    });

    it('returns 401 without a token and 403 for an admin', function () {
        $this->deleteJson(route('api.v1.alerts.destroy', 'x'))->assertUnauthorized();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson(route('api.v1.alerts.destroy', 'x'))->assertForbidden();
    });
});

describe('all alerts', function () {
    it('deletes every alert of the person and nobody else\'s', function () {
        $passenger = User::factory()->create();
        $passenger->notify(new Alert(AlertType::RideExpired));
        $passenger->notify(new Alert(AlertType::RideAccepted));
        $other = User::factory()->create();
        $other->notify(new Alert(AlertType::RideExpired));

        Sanctum::actingAs($passenger);

        $this->deleteJson(route('api.v1.alerts.destroy-all'))->assertNoContent();

        expect($passenger->notifications()->count())->toBe(0);
        expect($other->notifications()->count())->toBe(1);
    });

    it('works when there is nothing to delete', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson(route('api.v1.alerts.destroy-all'))->assertNoContent();
    });

    it('returns 401 without a token and 403 for an admin', function () {
        $this->deleteJson(route('api.v1.alerts.destroy-all'))->assertUnauthorized();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson(route('api.v1.alerts.destroy-all'))->assertForbidden();
    });
});
