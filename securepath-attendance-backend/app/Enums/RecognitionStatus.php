<?php

namespace App\Enums;

enum RecognitionStatus: string
{
    case Matched = 'matched';
    case Unknown = 'unknown';
    case LowConfidence = 'low_confidence';
    case Rejected = 'rejected';
    case Duplicate = 'duplicate';
}

