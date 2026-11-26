<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Actions\Auth\DeleteAccount;
use App\Actions\User\BlockUser;
use App\Actions\User\UnblockUser;
use App\Actions\User\UpdateUserDetails;
use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Rules\PhilippineMobileNumber;
use App\Support\PhoneNumber;
use App\UserRole;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    /**
     * An admin account is changed on the server only, so none of these show on it.
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->editAction(),
            $this->blockAction(),
            $this->unblockAction(),
            $this->deleteAction(),
        ];
    }

    private function editAction(): Action
    {
        return Action::make('edit')
            ->label('Edit')
            ->color('gray')
            ->icon('heroicon-o-pencil-square')
            ->visible(fn (User $record): bool => $record->role !== UserRole::Admin)
            ->modalHeading('Edit name and mobile number')
            ->modalSubmitActionLabel('Save')
            ->fillForm(fn (User $record): array => ['name' => $record->name, 'phone' => $record->phone])
            ->schema([
                TextInput::make('name')->required()->minLength(2)->maxLength(60),
                TextInput::make('phone')
                    ->label('Mobile number')
                    ->helperText('For example 0917 123 4567. Leave empty when the person gave none.')
                    ->maxLength(20)
                    ->rules([
                        new PhilippineMobileNumber,
                        fn (User $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            $number = PhoneNumber::normalize((string) $value);

                            if ($number !== null && User::query()->where('phone', $number)->whereKeyNot($record->id)->exists()) {
                                $fail('This mobile number is already used by another account.');
                            }
                        },
                    ]),
            ])
            ->action(function (array $data, User $record, UpdateUserDetails $update): void {
                $phone = filled($data['phone']) ? PhoneNumber::normalize($data['phone']) : null;

                $update->handle($record, trim($data['name']), $phone);

                Notification::make()->success()->title('Saved')->send();
            });
    }

    private function blockAction(): Action
    {
        return Action::make('block')
            ->label('Block')
            ->color('warning')
            ->icon('heroicon-o-no-symbol')
            ->visible(fn (User $record): bool => $record->role !== UserRole::Admin && ! $record->isBlocked())
            ->modalHeading('Block this person')
            ->modalDescription('They are signed out and cannot sign in again. A driver goes offline. A trip that is going on is ended.')
            ->modalSubmitActionLabel('Block')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason (only admins see it)')
                    ->required()
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->action(function (array $data, User $record, BlockUser $block): void {
                $this->attempt(fn () => $block->handle($record, auth()->user(), $data['reason']), 'Person blocked');
            });
    }

    private function unblockAction(): Action
    {
        return Action::make('unblock')
            ->label('Unblock')
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->visible(fn (User $record): bool => $record->isBlocked())
            ->requiresConfirmation()
            ->modalDescription('The person can sign in again.')
            ->action(function (User $record, UnblockUser $unblock): void {
                $this->attempt(fn () => $unblock->handle($record), 'Person unblocked');
            });
    }

    private function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Delete')
            ->color('danger')
            ->icon('heroicon-o-trash')
            ->visible(fn (User $record): bool => $record->role !== UserRole::Admin)
            ->requiresConfirmation()
            ->modalHeading('Delete this account for good')
            ->modalDescription('The name, number, emergency contacts, trips they asked for, chat photos, reports they made and driver documents are erased. This cannot be undone. They can sign up again with the same email: to keep somebody out, block them instead.')
            ->modalSubmitActionLabel('Delete for good')
            ->action(function (User $record, DeleteAccount $delete): void {
                if ($this->attempt(fn () => $delete->handle($record), 'Account deleted')) {
                    $this->redirect(UserResource::getUrl());
                }
            });
    }

    /**
     * Run an action and say what happened. An action refuses with a message that the admin can read.
     */
    private function attempt(Closure $action, string $done): bool
    {
        try {
            $action();
        } catch (HttpException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return false;
        }

        Notification::make()->success()->title($done)->send();

        return true;
    }
}
