<?php

namespace App;

enum DocumentType: string
{
    case Photo = 'photo';
    case License = 'license';
    case PlatePapers = 'plate-papers';
    case Franchise = 'franchise';
}
