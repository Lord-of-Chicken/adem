<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PiiMasker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PiiMaskerTest extends TestCase
{
    /** @return iterable<array{?string, string}> */
    public static function emails(): iterable
    {
        yield [null, '***']; yield ['', '***']; yield ['a@example.test', '***@example.test'];
        yield ['john.doe@example.test', 'j***@example.test']; yield ['Élodie@example.test', 'É***@example.test'];
        yield ['not-an-email', 'n***'];
    }

    #[DataProvider('emails')]
    public function testEmailMasking(?string $email, string $expected): void
    {
        self::assertSame($expected, PiiMasker::maskEmail($email));
    }

    public function testNamesHandleWhitespaceAndUnicode(): void
    {
        self::assertSame('***', PiiMasker::maskName(null));
        self::assertSame('***', PiiMasker::maskName(' '));
        self::assertSame('É***', PiiMasker::maskName(' Élodie Dupont '));
    }
}
