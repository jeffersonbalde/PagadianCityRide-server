<?php

namespace App\Filament\Resources\DriverApprovalResource\Pages;

use App\Actions\Driver\ApproveDriverApplication;
use App\Actions\Driver\RejectDriverApplication;
use App\DocumentType;
use App\DriverStatus;
use App\Filament\Resources\DriverApprovalResource;
use App\Models\DriverProfile;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ViewDriverApproval extends ViewRecord
{
    protected static string $resource = DriverApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (DriverProfile $record): bool => $record->status === DriverStatus::Submitted)
                ->requiresConfirmation()
                ->modalHeading('Approve this driver?')
                ->modalDescription('The driver gets a text message and can go online right away.')
                ->modalSubmitActionLabel('Yes, approve')
                ->action(function (DriverProfile $record, ApproveDriverApplication $approve): void {
                    try {
                        $approve->handle($record, auth()->user());
                    } catch (HttpException $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();

                        return;
                    }

                    Notification::make()->success()->title('Driver approved')->send();

                    $this->redirect(DriverApprovalResource::getUrl());
                }),
            Action::make('sendBack')
                ->label('Send back')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn (DriverProfile $record): bool => $record->status === DriverStatus::Submitted)
                ->modalHeading('Send this application back')
                ->modalDescription('The driver gets a text message and sees the reason in the app.')
                ->modalSubmitActionLabel('Send back')
                ->schema([
                    Textarea::make('reason')
                        ->label('What needs to change?')
                        ->helperText('The driver reads this, so say it simply.')
                        ->required()
                        ->maxLength(500)
                        ->rows(3),
                    CheckboxList::make('document_types')
                        ->label('Which photos must be taken again?')
                        ->options([
                            DocumentType::Photo->value => 'Driver photo',
                            DocumentType::License->value => 'Driver license',
                            DocumentType::PlatePapers->value => 'Plate and papers',
                            DocumentType::Franchise->value => 'Franchise',
                        ])
                        ->columns(2),
                ])
                ->action(function (array $data, DriverProfile $record, RejectDriverApplication $reject): void {
                    try {
                        $reject->handle(
                            $record,
                            auth()->user(),
                            $data['reason'],
                            array_map(fn (string $type): DocumentType => DocumentType::from($type), $data['document_types'] ?? []),
                        );
                    } catch (HttpException $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();

                        return;
                    }

                    Notification::make()->success()->title('Application sent back')->send();

                    $this->redirect(DriverApprovalResource::getUrl());
                }),
        ];
    }
}
