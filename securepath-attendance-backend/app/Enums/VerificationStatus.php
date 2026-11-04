<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Automatic = 'automatic';
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
}

