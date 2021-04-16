<?php

declare(strict_types=1);

/*
 * This file is part of the Rollerworks Semver package.
 *
 * (c) Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Rollerworks\Component\Version\Tests;

use PHPUnit\Framework\TestCase;
use Rollerworks\Component\Version\ContinuesVersionsValidator;
use Rollerworks\Component\Version\Version;

class ContinuesVersionsValidatorTest extends TestCase
{
    /**
     * @return array<int, string[]>
     */
    public function provideInitialContinuesVersions(): iterable
    {
        yield ['0.1.0'];
        yield ['1.0-ALPHA1'];
        yield ['1.0-BETA1'];
        yield ['1.0'];
    }

    /**
     * @test
     * @dataProvider provideInitialContinuesVersions
     */
    public function it_accepts_a_continues_version_with_no_pre_existing(string $new): void
    {
        $validator = new ContinuesVersionsValidator();

        self::assertTrue($validator->isContinues(Version::fromString($new)));
        self::assertEquals(
            [
                Version::fromString('0.1.0'),
                Version::fromString('1.0.0-ALPHA1'),
                Version::fromString('1.0.0-BETA1'),
                Version::fromString('1.0.0'),
            ],
            $validator->getPossibleVersions()
        );
    }

    /**
     * @return array<string, array<int, string[]|string>>
     */
    public function provideContinuesVersions(): iterable
    {
        yield 'unstable #1' => ['0.3', ['0.2', '0.1'], ['0.2.1', '0.3', '1.0-BETA1', '1.0']];
        yield 'unstable #2' => ['0.2.1', ['0.2', '0.1'], ['0.2.1', '0.3', '1.0-BETA1', '1.0']];
        yield 'unstable #3' => ['1.0', ['0.2', '0.1'], ['0.2.1', '0.3', '1.0-BETA1', '1.0']];
        yield 'unstable #4' => ['0.1.1', ['0.2', '0.1'], ['0.1.1']];
        yield 'unstable #5' => ['0.2.1', ['0.2', '0.1'], ['0.2.1', '0.3', '1.0-BETA1', '1.0']];
        yield 'unstable #6' => ['1.0-BETA1', ['0.2', '0.1'], ['0.2.1', '0.3', '1.0-BETA1', '1.0']];

        yield 'stable #1' => ['1.2', ['1.0', '1.1'], ['1.1.1', '1.2-BETA1', '1.2', '2.0-ALPHA1', '2.0-BETA1', '2.0']];
        yield 'stable #2' => ['1.1.1', ['1.1', '2.0'], ['1.1.1']];
        yield 'stable #3' => ['2.20.0', ['2.19.0', '2.19.1', '2.19.2', '3.0.0', '3.1.0'], ['2.19.3', '2.20.0', '3.0.1', '3.1.1', '3.2.0']];
    }

    /**
     * @test
     * @dataProvider provideContinuesVersions
     *
     * @param array<int, string> $existing
     * @param array<int, string> $possible
     */
    public function it_accepts_a_continues_version(string $new, array $existing, array $possible): void
    {
        $validator = new ContinuesVersionsValidator(...$this->createVersions($existing));

        self::assertTrue($validator->isContinues(Version::fromString($new)));
        self::assertEquals($this->createVersions($possible), array_merge([], $validator->getPossibleVersions()));
    }

    /**
     * @param array<int, string> $existing
     *
     * @return array<int, Version>
     */
    private function createVersions(array $existing): array
    {
        return array_map(
            function (string $version) {
                return Version::fromString($version);
            },
            $existing
        );
    }

    /**
     * @return array<int, string[]>
     */
    public function provideNotInitialContinuesVersions(): iterable
    {
        yield ['0.2.0'];
        yield ['2.0-ALPHA1'];
        yield ['2.0-BETA1'];
        yield ['1.1'];
        yield ['2.0'];
    }

    /**
     * @test
     * @dataProvider provideNotInitialContinuesVersions
     */
    public function it_rejects_non_continues_version_with_no_pre_existing(string $new): void
    {
        $validator = new ContinuesVersionsValidator();

        self::assertFalse($validator->isContinues(Version::fromString($new)));
        self::assertEquals(
            [
                Version::fromString('0.1.0'),
                Version::fromString('1.0.0-ALPHA1'),
                Version::fromString('1.0.0-BETA1'),
                Version::fromString('1.0.0'),
            ],
            $validator->getPossibleVersions()
        );
    }

    /**
     * @return array<string, array<int, string[]|string>>
     */
    public function provideNonContinuesVersions(): iterable
    {
        yield 'unstable #1' => ['0.5', ['0.2', '0.1'], ['0.2.1', '0.3', '1.0-BETA1', '1.0']];
        yield 'unstable #2' => ['0.2.4', ['0.2', '0.1'], ['0.2.1', '0.3', '1.0-BETA1', '1.0']];
        yield 'unstable #3' => ['2.0', ['0.2', '0.1'], ['0.2.1', '0.3', '1.0-BETA1', '1.0']];
        yield 'unstable #4' => ['0.1.5', ['0.2', '0.1'], ['0.1.1']];
        yield 'unstable #5' => ['1.0-BETA2', ['0.2', '0.1'], ['0.2.1', '0.3', '1.0-BETA1', '1.0']];
        yield 'unstable #6' => ['1.0-ALPHA1', ['0.2', '0.1'], ['0.2.1', '0.3', '1.0-BETA1', '1.0']];

        yield 'stable #1' => ['1.3', ['1.0', '1.1'], ['1.1.1', '1.2-BETA1', '1.2', '2.0-ALPHA1', '2.0-BETA1', '2.0']];
        yield 'stable #2' => ['3.6', ['v3.5-beta1'], ['v3.5-beta2', 'v3.5-RC1', 'v3.5']];
    }

    /**
     * @test
     * @dataProvider provideNonContinuesVersions
     *
     * @param array<int, string> $existing
     * @param array<int, string> $possible
     */
    public function it_rejects_non_continues_version(string $new, array $existing, array $possible): void
    {
        $validator = new ContinuesVersionsValidator(...$this->createVersions($existing));

        self::assertFalse($validator->isContinues(Version::fromString($new)));
        self::assertEquals($this->createVersions($possible), $validator->getPossibleVersions());
    }
}
