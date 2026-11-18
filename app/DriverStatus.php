<?php

namespace App;

enum DriverStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Statuses in which the driver may still change the profile and documents.
     *
     * @return list<self>
     */
    public static function editable(): array
    {
        return [self::Draft, self::Rejected];
    }

    public function isEditable(): bool
    {
        return in_array($this, self::editable(), true);
    }
}
