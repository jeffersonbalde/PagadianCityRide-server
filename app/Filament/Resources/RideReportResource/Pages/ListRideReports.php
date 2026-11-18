<?php

namespace App\Filament\Resources\RideReportResource\Pages;

use App\Filament\Resources\RideReportResource;
use App\ReportStatus;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRideReports extends ListRecords
{
    protected static string $resource = RideReportResource::class;

    public function getTabs(): array
    {
        return [
            'open' => Tab::make('Open')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ReportStatus::Open)),
            'resolved' => Tab::make('Resolved')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ReportStatus::Resolved)),
            'dismissed' => Tab::make('Dismissed')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ReportStatus::Dismissed)),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'open';
    }
}
