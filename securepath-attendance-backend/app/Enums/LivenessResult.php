<?php

namespace App\Enums;

enum LivenessResult: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Inconclusive = 'inconclusive';
    case NotProvided = 'not_provided';
}

