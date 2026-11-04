<?php

namespace App\Enums;

enum BiometricStatus: string
{
    case NotRegistered = 'not_registered';
    case Pending = 'pending';
    case Processing = 'processing';
    case Enrolled = 'enrolled';
    case Failed = 'failed';
    case Registered = 'registered';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
