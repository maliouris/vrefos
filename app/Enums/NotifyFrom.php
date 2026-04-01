<?php

namespace App\Enums;

enum NotifyFrom: string
{
    case StartedAt = 'started_at';
    case FinishedAt = 'finished_at';
}
