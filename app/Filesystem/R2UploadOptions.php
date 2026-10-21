<?php

namespace App\Filesystem;

use Aws\CommandInterface;

class R2UploadOptions
{
    public static function withoutAcl(CommandInterface $command): void
    {
        unset($command['ACL']);
    }
}
