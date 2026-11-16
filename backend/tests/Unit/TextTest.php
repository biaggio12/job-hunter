<?php

namespace App\Tests\Unit;

use App\Util\Text;
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase
{
    public function testNormalizeStripsPolishCharactersAndPunctuation(): void
    {
        self::assertSame('wroclaw lodz c# c++', Text::normalize('  Wrocław, Łódź — C#  C++ '));
    }

    public function testCompanyNormalizationRemovesLegalForms(): void
    {
        self::assertSame('ideamotive', Text::normalizeCompany('Ideamotive Sp. z o.o.'));
        self::assertSame('acme', Text::normalizeCompany('ACME S.A.'));
    }

    public function testDuplicateKeyIgnoresSeniorityGenderAndBrackets(): void
    {
        $a = Text::duplicateKey('Ideamotive Sp. z o.o.', 'Senior PHP Developer (k/m)');
        $b = Text::duplicateKey('IDEAMOTIVE', 'PHP Developer [remote]');
        self::assertSame('ideamotive|php developer', $a);
        self::assertSame($a, $b);
    }

    public function testDuplicateKeyDiffersForDifferentRoles(): void
    {
        self::assertNotSame(
            Text::duplicateKey('Acme', 'PHP Developer'),
            Text::duplicateKey('Acme', 'Python Developer'),
        );
    }

    public function testDuplicateKeyIsEmptyWithoutCompany(): void
    {
        self::assertSame('', Text::duplicateKey('', 'PHP Developer'));
    }
}
