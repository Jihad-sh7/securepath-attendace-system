<?php

namespace App\Enums;

enum AttendanceActionSource: string
{
    case FaceRecognition = 'face_recognition';
    case AdminManual = 'admin_manual';
    case AutoOvertimeLimit = 'auto_overtime_limit';
    case AdminCorrection = 'admin_correction';
    case System = 'system';
}

