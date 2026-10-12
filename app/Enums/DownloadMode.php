<?php

namespace App\Enums;

enum DownloadMode: string
{
    case Apk = 'apk';
    case PlayStore = 'play_store';
    case ComingSoon = 'coming_soon';
}
