<?php

namespace App\Service;

use Symfony\Component\Uid\Uuid;

class Utils
{
    public static function generateUuid(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}
