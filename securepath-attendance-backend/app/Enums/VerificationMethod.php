<?php

namespace App\Enums;

enum VerificationMethod: string
{
    case FaceRecognition = 'face_recognition';
    case Manual = 'manual';
    case AdminCorrection = 'admin_correction';
    case LeaveApproval = 'leave_approval';
    case System = 'system';
}

