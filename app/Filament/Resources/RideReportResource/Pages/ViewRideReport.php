<?php

namespace App\Filament\Resources\RideReportResource\Pages;

use App\Actions\Ride\ResolveRideReport;
use App\Filament\Resources\RideReportResource;
use App\Models\RideReport;
use App\ReportStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ViewRideReport extends ViewRecord
{
    protected static string $resource = RideReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->closeAction('resolve', 'Resolve', ReportStatus::Resolved, 'success', 'heroicon-o-check-circle', 'Resolve this report', 'Resolved'),
            $this->closeAction('dismiss', 'Dismiss', ReportStatus::Dismissed, 'gray', 'heroicon-o-x-circle', 'Dismiss this report', 'Dismissed'),
        ];
    }

    private function closeAction(string $name, string $label, ReportStatus $status, string $color, string $icon, string $heading, string $done): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->icon($icon)
            ->visible(fn (RideReport $record): bool => $record->status->isOpen())
            ->modalHeading($heading)
            ->modalDescription('The person who sent the report is told that it was looked at. They do not see your note.')
            ->modalSubmitActionLabel($label)
            ->schema([
                Textarea::make('note')
                    ->label('Note for the admins')
                    ->helperText('What you found and what you did.')
                    ->required()
                    ->maxLength(1000)
                    ->rows(4),
            ])
            ->action(function (array $data, RideReport $record, ResolveRideReport $resolve) use ($status, $done): void {
                try {
                    $resolve->handle($record, auth()->user(), $status, $data['note']);
                } catch (HttpException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title($done)->send();

                $this->redirect(RideReportResource::getUrl());
            });
    }
}
