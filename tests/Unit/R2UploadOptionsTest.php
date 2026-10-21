<?php

namespace Tests\Unit;

use App\Filesystem\R2UploadOptions;
use Aws\Command;
use PHPUnit\Framework\TestCase;

class R2UploadOptionsTest extends TestCase
{
    public function test_it_removes_the_unsupported_acl_from_s3_upload_commands(): void
    {
        $command = new Command('PutObject', ['ACL' => 'private']);

        R2UploadOptions::withoutAcl($command);

        $this->assertFalse($command->hasParam('ACL'));
    }
}
