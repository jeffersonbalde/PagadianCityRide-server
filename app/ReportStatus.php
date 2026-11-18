<?php

namespace App;

enum ReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
