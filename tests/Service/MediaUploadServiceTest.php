<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\MediaUploadService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validation;

final class MediaUploadServiceTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/adem-upload-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/public/img/ruelle', 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/public/img/ruelle/*') ?: [] as $file) { unlink($file); }
        foreach (glob($this->directory.'/input-*') ?: [] as $file) { unlink($file); }
        rmdir($this->directory.'/public/img/ruelle');
        rmdir($this->directory.'/public/img');
        rmdir($this->directory.'/public');
        rmdir($this->directory);
    }

    public function testValidImageHasGeneratedSafeNameAndIsActuallyStored(): void
    {
        $path = $this->directory.'/input-image';
        $image = imagecreatetruecolor(2, 2);
        imagepng($image, $path);
        $file = new UploadedFile($path, '../../payload.php', 'application/x-httpd-php', null, true);
        $result = (new MediaUploadService(Validation::createValidator(), $this->directory))->store($file);
        self::assertMatchesRegularExpression('#^img/ruelle/[a-f0-9]{32}\.png$#', $result);
        self::assertFileExists($this->directory.'/public/'.$result);
        self::assertSame('image/png', mime_content_type($this->directory.'/public/'.$result));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidImages(): iterable
    {
        yield 'php pretending to be jpg' => ['<?php echo 1;', 'attack.jpg'];
        yield 'svg' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'attack.svg'];
        yield 'corrupt png' => ["\x89PNG\r\n\x1a\ntruncated", 'bad.png'];
        yield 'too large' => [str_repeat('a', 5 * 1024 * 1024 + 1), 'oversized.jpg'];
    }

    #[DataProvider('invalidImages')]
    public function testUnsafeUploadsAreRejectedBeforeStorage(string $content, string $name): void
    {
        $path = $this->directory.'/input-file';
        file_put_contents($path, $content);
        $file = new UploadedFile($path, $name, 'image/jpeg', null, true);
        try {
            (new MediaUploadService(Validation::createValidator(), $this->directory))->store($file);
            self::fail('Unsafe upload was accepted.');
        } catch (\InvalidArgumentException) {
            self::assertSame([], glob($this->directory.'/public/img/ruelle/*'));
        }
    }
}
