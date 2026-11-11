<?php

declare(strict_types=1);

namespace App\Tests\DevOps;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class PackagingTest extends TestCase
{
    public function testPackageContainsRuntimeAndExcludesPrivateEnvironmentAndKey(): void
    {
        $archive = sys_get_temp_dir().'/adem-package-test-'.bin2hex(random_bytes(8)).'.tar.gz';
        try {
            $process = new Process(['bash', 'scripts/package.sh', $archive], dirname(__DIR__, 2));
            $process->mustRun();
            $listing = new Process(['tar', '-tzf', $archive]);
            $listing->mustRun();
            $files = explode("\n", $listing->getOutput());
            self::assertContains('bin/console', $files);
            self::assertContains('public/index.php', $files);
            self::assertContains('composer.lock', $files);
            self::assertContains('translations/messages.fr.yaml', $files);
            self::assertNotContains('.env.production', $files);
            self::assertNotContains('.env.local', $files);
            self::assertStringNotContainsString('.decrypt.private.php', $listing->getOutput());
            $overwrite = new Process(['bash', 'scripts/package.sh', $archive], dirname(__DIR__, 2));
            self::assertSame(2, $overwrite->run());
        } finally { if (is_file($archive)) { unlink($archive); } }
    }

    public function testDeployScriptsRefuseImplicitOrPartialProductionAction(): void
    {
        foreach (['scripts/deploy.sh', 'scripts/deploy-config.sh'] as $script) {
            $process = new Process(['bash', $script], dirname(__DIR__, 2));
            self::assertSame(2, $process->run());
        }
    }
}
