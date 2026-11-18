<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Only admins can open the panel, so every admin may look at these records. The records are changed
 * through the actions on their pages, never through a create, edit or delete page.
 */
trait ReadOnlyForAdmins
{
    public static function canViewAny(): bool
    {
        return true;
    }

    public static function canView(Model $record): bool
    {
        return true;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
