<?php

namespace App;

/**
 * What went wrong on a ride, as the person who reports it chooses from a short list.
 */
enum ReportCategory: string
{
    case Safety = 'safety';
    case Behavior = 'behavior';
    case Route = 'route';
    case NoShow = 'no_show';
    case Other = 'other';
}
