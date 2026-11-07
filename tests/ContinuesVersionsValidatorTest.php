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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rollerworks\Component\Version\ContinuesVersionsValidator;
use Rollerworks\Component\Version\Version;

/**
 * @internal
 */
class ContinuesVersionsValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        $this->registerComparator(new VersionComparator());
    }

    /**
     * @return iterable<array{string, array<string, string>}>
     */
    public static function provideInitialContinuesVersions(): iterable
    {
        yield [
            '0.1.0',
            [
                '0.1.0' => '0.1.0',
                '1.0.0-ALPHA1' => '1.0.0-ALPHA1',
                '1.0.0-BETA1' => '1.0.0-BETA1',
                '1.0.0' => '1.0.0',
            ],
        ];

        yield [
            '0.1',
            [
                '0.1' => '0.1',
                '1.0-ALPHA1' => '1.0-ALPHA1',
                '1.0-BETA1' => '1.0-BETA1',
                '1.0' => '1.0',
            ],
        ];

        yield [
            '0.1',
            [
                '0.1' => '0.1',
                '1.0' => '1.0',
            ],
            Version::CANDIDATE_EXCLUDE_ALPHA | Version::CANDIDATE_EXCLUDE_BETA,
        ];

        yield [
            '0.1.0',
            [
                '0.1.0' => '0.1.0',
                '1.0.0-ALPHA1' => '1.0.0-ALPHA1',
                '1.0.0-BETA1' => '1.0.0-BETA1',
                '1.0.0' => '1.0.0',
            ],
            Version::CANDIDATE_EXCLUDE_PATCH,
        ];

        yield [
            '1.0-BETA1',
            [
                '0.1' => '0.1',
                '1.0-BETA1' => '1.0-BETA1',
                '1.0' => '1.0',
            ],
            Version::CANDIDATE_EXCLUDE_ALPHA,
        ];

        yield [
            '1.0',
            [
                '1.0' => '1.0',
            ],
            Version::CANDIDATE_EXCLUDE_PRE_RELEASES,
        ];
    }

    /**
     * @param array<string, string> $expected
     */
    #[Test]
    #[DataProvider('provideInitialContinuesVersions')]
    public function it_accepts_a_continues_version_with_no_pre_existing(string $new, array $expected, int $options = 0): void
    {
        $validator = new ContinuesVersionsValidator();

        self::assertTrue($validator->isContinues(Version::fromString($new), $options));
        self::assertVersionsEquals($this->createVersions($expected), $validator->getPossibleVersions());
    }

    #[Test]
    public function it_formats_initial_candidates_with_the_correct_format(): void
    {
        $expectedFormat = '{major}.{minor}.{?patch}.{?build}-{metaver:title}.x';
        $validator = new ContinuesVersionsValidator();

        self::assertTrue($validator->isContinues(Version::fromString('1.0', '{major}.{minor}-{metaver:title}.x')));
        self::assertVersionsEquals(
            [
                '0.1' => Version::fromString('0.1', $expectedFormat),
                '1.0-Alpha.1' => Version::fromString('1.0-ALPHA1', $expectedFormat),
                '1.0-Beta.1' => Version::fromString('1.0-BETA1', $expectedFormat),
                '1.0' => Version::fromString('1.0', $expectedFormat),
            ],
            $validator->getPossibleVersions()
        );

        self::assertTrue($validator->isContinues(Version::fromString('1.0.0', $expectedFormat)));
        self::assertVersionsEquals(
            [
                '0.1.0' => Version::fromString('0.1.0', $expectedFormat),
                '1.0.0-Alpha.1' => Version::fromString('1.0.0-ALPHA1', $expectedFormat),
                '1.0.0-Beta.1' => Version::fromString('1.0.0-BETA1', $expectedFormat),
                '1.0.0' => Version::fromString('1.0.0', $expectedFormat),
            ],
            $validator->getPossibleVersions()
        );

        self::assertTrue($validator->isContinues(Version::fromString('1.0.0.0', $expectedFormat)));
        self::assertVersionsEquals(
            [
                '0.1.0.0' => Version::fromString('0.1.0.0', $expectedFormat),
                '1.0.0.0-Alpha.1' => Version::fromString('1.0.0.0-ALPHA1', $expectedFormat),
                '1.0.0.0-Beta.1' => Version::fromString('1.0.0.0-BETA1', $expectedFormat),
                '1.0.0.0' => Version::fromString('1.0.0.0', $expectedFormat),
            ],
            $validator->getPossibleVersions()
        );
    }

    #[Test]
    public function continues_version_with_no_pre_existing_multiple_calls(): void
    {
        $validator = new ContinuesVersionsValidator();

        self::assertTrue($validator->isContinues(Version::fromString('0.1.0')));
        self::assertVersionsEquals(
            [
                '0.1.0' => Version::fromString('0.1.0'),
                '1.0.0-ALPHA1' => Version::fromString('1.0.0-ALPHA1'),
                '1.0.0-BETA1' => Version::fromString('1.0.0-BETA1'),
                '1.0.0' => Version::fromString('1.0.0'),
            ],
            $validator->getPossibleVersions()
        );

        self::assertTrue($validator->isContinues(Version::fromString('1.0-ALPHA1')));
        self::assertVersionsEquals(
            [
                '0.1' => Version::fromString('0.1'),
                '1.0-ALPHA1' => Version::fromString('1.0-ALPHA1'),
                '1.0-BETA1' => Version::fromString('1.0-BETA1'),
                '1.0' => Version::fromString('1.0'),
            ],
            $validator->getPossibleVersions()
        );

        self::assertFalse($validator->isContinues(Version::fromString('2.0.0')));
    }

    #[Test]
    public function continues_version_with_existing_multiple_calls(): void
    {
        $validator = new ContinuesVersionsValidator(
            Version::fromString('0.1.0'),
            Version::fromString('0.2.0'),
            Version::fromString('1.0.0'),
            Version::fromString('2.0.0'),
        );

        self::assertTrue($validator->isContinues(Version::fromString('0.3.0')));
        self::assertFalse($validator->isContinues(Version::fromString('0.4.0')));
        self::assertVersionsEquals(
            [
                '0.2.1' => Version::fromString('0.2.1'),
                '0.3.0' => Version::fromString('0.3.0'),
            ],
            $validator->getPossibleVersions()
        );

        self::assertTrue($validator->isContinues(Version::fromString('1.1.0')));
        self::assertFalse($validator->isContinues(Version::fromString('1.0.0-ALPHA1')));
        self::assertVersionsEquals(
            [
                '1.0.1' => Version::fromString('1.0.1'),
                '1.1.0' => Version::fromString('1.1.0'),
            ],
            $validator->getPossibleVersions()
        );

        self::assertFalse($validator->isContinues(Version::fromString('4.0.0')));
        self::assertVersionsEquals(
            [
                '2.0.1' => Version::fromString('2.0.1'),
                '2.1.0-BETA1' => Version::fromString('2.1.0-BETA1'),
                '2.1.0' => Version::fromString('2.1.0'),
                '3.0.0-ALPHA1' => Version::fromString('3.0.0-ALPHA1'),
                '3.0.0-BETA1' => Version::fromString('3.0.0-BETA1'),
                '3.0.0' => Version::fromString('3.0.0'),
            ],
            $validator->getPossibleVersions()
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string[], 2: string[], 3?: int}>
     */
    public static function provideContinuesVersions(): iterable
    {
        yield 'unstable #1' => ['0.3.0', ['0.2.0', '0.1.0'], ['0.2.1', '0.3.0', '1.0.0-BETA1', '1.0.0']];
        yield 'unstable #2' => ['0.2.1', ['0.2.0', '0.1.0'], ['0.2.1', '0.3.0', '1.0.0-BETA1', '1.0.0']];
        yield 'unstable #3' => ['1.0.0', ['0.2.0', '0.1.0'], ['0.2.1', '0.3.0', '1.0.0-BETA1', '1.0.0']];
        yield 'unstable #4' => ['0.1.1', ['0.2.0', '0.1.0'], ['0.1.1']];
        yield 'unstable #5' => ['0.2.1', ['0.2.0', '0.1.0'], ['0.2.1', '0.3.0', '1.0.0-BETA1', '1.0.0']];

        // Without patch
        yield 'unstable #1 without patch' => ['1.0-BETA1', ['0.2', '0.1'], ['0.3', '1.0-BETA1', '1.0']];
        yield 'unstable #2 without patch (exclude beta)' => ['1.0', ['0.2', '0.1'], ['0.3', '1.0'], Version::CANDIDATE_EXCLUDE_BETA];

        // With build
        yield 'unstable #1 with build' => ['1.0.0.0', ['0.2.0.0', '0.1.0.0'], ['0.2.1.0', '0.2.0.1', '0.3.0.0', '1.0.0.0-BETA1', '1.0.0.0']];
        yield 'unstable #2 with build' => ['1.0.0.0', ['0.2.0.1', '0.1.0.0'], ['0.2.1.0', '0.2.0.2', '0.3.0.0', '1.0.0.0-BETA1', '1.0.0.0']];

        yield 'stable #1' => ['1.2.0', ['1.0.0', '1.1.0'], ['1.1.1', '1.2.0-BETA1', '1.2.0', '2.0.0-ALPHA1', '2.0.0-BETA1', '2.0.0']];
        yield 'stable #2' => ['1.1.1', ['1.1.0', '2.0.0'], ['1.1.1', '1.2.0']];

        yield 'stable #1 with build' => ['1.1.2.0', ['1.1.0.0', '1.1.1.0'], ['1.1.2.0', '1.1.1.1', '1.2.0.0-BETA1', '1.2.0.0', '2.0.0.0-ALPHA1', '2.0.0.0-BETA1', '2.0.0.0']];
        yield 'stable #2 with build' => ['1.1.1.0', ['1.1.0.0', '2.0.0.0'], ['1.1.0.1', '1.1.1.0', '1.2.0.0']];
        yield 'stable #3 with build' => ['1.1.1.0', ['1.1.0.0', '1.3.0.0', '2.0.0.0'], ['1.1.1.0']];

        yield 'stable #1 without patch (exclude alpha)' => ['1.2', ['1.0.0', '1.1.0'], ['1.1.1', '1.2.0-BETA1', '1.2.0', '2.0.0-BETA1', '2.0.0'], Version::CANDIDATE_EXCLUDE_ALPHA];
        yield 'stable #2 without patch (exclude alpha)' => ['1.2', ['1.0.0', '1.1.1'], ['1.1.2', '1.2.0-BETA1', '1.2.0', '2.0.0-BETA1', '2.0.0'], Version::CANDIDATE_EXCLUDE_ALPHA];
    }

    /**
     * @param string[] $existing
     * @param string[] $possible
     */
    #[Test]
    #[DataProvider('provideContinuesVersions')]
    public function it_accepts_a_continues_version(string $new, array $existing, array $possible, int $options = 0): void
    {
        $validator = new ContinuesVersionsValidator(...$this->createVersions($existing));
        $formatter = static fn (Version $version): string => \sprintf("'%s' => '%s'", $version->format(), $version->full);

        self::assertTrue(
            $validator->isContinues(Version::fromString($new), $options),
            \sprintf(
                'Expect "%s". Existing ["%s"] with candidates ["%s"]',
                $new,
                implode('", "', $existing),
                implode('", "', array_map($formatter, $validator->getPossibleVersions())),
            ),
        );
        self::assertVersionsEquals($this->createVersions($possible), $validator->getPossibleVersions());
    }

    /**
     * @param string[] $existing
     *
     * @return Version[]
     */
    private function createVersions(array $existing): array
    {
        $processed = [];

        foreach ($existing as $version) {
            $processed[$version] = Version::fromString($version);
        }

        return $processed;
    }

    /**
     * @return iterable<array{0: string, 1: array<string, Version>}>
     */
    public static function provideNotInitialContinuesVersions(): iterable
    {
        yield [
            '0.2.0',
            [
                '0.1.0' => Version::fromString('0.1.0'),
                '1.0.0-ALPHA1' => Version::fromString('1.0.0-ALPHA1'),
                '1.0.0-BETA1' => Version::fromString('1.0.0-BETA1'),
                '1.0.0' => Version::fromString('1.0.0'),
            ],
        ];

        yield [
            '2.0-ALPHA1',
            [
                '0.1' => Version::fromString('0.1'),
                '1.0-ALPHA1' => Version::fromString('1.0-ALPHA1'),
                '1.0-BETA1' => Version::fromString('1.0-BETA1'),
                '1.0' => Version::fromString('1.0'),
            ],
        ];

        yield [
            '2.0-BETA1',
            [
                '0.1' => Version::fromString('0.1'),
                '1.0-ALPHA1' => Version::fromString('1.0-ALPHA1'),
                '1.0-BETA1' => Version::fromString('1.0-BETA1'),
                '1.0' => Version::fromString('1.0'),
            ],
        ];

        yield [
            '1.1',
            [
                '0.1' => Version::fromString('0.1'),
                '1.0-ALPHA1' => Version::fromString('1.0-ALPHA1'),
                '1.0-BETA1' => Version::fromString('1.0-BETA1'),
                '1.0' => Version::fromString('1.0'),
            ],
        ];

        yield [
            '2.0',
            [
                '0.1' => Version::fromString('0.1'),
                '1.0-ALPHA1' => Version::fromString('1.0-ALPHA1'),
                '1.0-BETA1' => Version::fromString('1.0-BETA1'),
                '1.0' => Version::fromString('1.0'),
            ],
        ];

        yield [
            '2.0.0.0',
            [
                '0.1.0.0' => Version::fromString('0.1.0.0'),
                '1.0.0.0-ALPHA1' => Version::fromString('1.0.0.0-ALPHA1'),
                '1.0.0.0-BETA1' => Version::fromString('1.0.0.0-BETA1'),
                '1.0.0.0' => Version::fromString('1.0.0.0'),
            ],
        ];
    }

    /**
     * @param array<string, Version> $expected
     */
    #[Test]
    #[DataProvider('provideNotInitialContinuesVersions')]
    public function it_rejects_non_continues_version_with_no_pre_existing(string $new, array $expected): void
    {
        $validator = new ContinuesVersionsValidator();

        self::assertFalse($validator->isContinues(Version::fromString($new)));
        self::assertVersionsEquals($expected, $validator->getPossibleVersions());
    }

    /**
     * @return iterable<string, array{0: string, 1: string[]}>
     */
    public static function provideNonContinuesVersions(): iterable
    {
        yield 'unstable #1' => ['0.5', ['0.2', '0.1'], ['0.3', '1.0-BETA1', '1.0']];
        yield 'unstable #1-1' => ['0.5.0', ['0.2.0', '0.1.0'], ['0.2.1', '0.3.0', '1.0.0-BETA1', '1.0.0']];
        yield 'unstable #2' => ['0.2.4', ['0.2.0', '0.1.0'], ['0.2.1', '0.3.0', '1.0.0-BETA1', '1.0.0']];
        yield 'unstable #3' => ['2.0', ['0.2', '0.1'], ['0.3', '1.0-BETA1', '1.0']];
        yield 'unstable #4' => ['0.1.5', ['0.2.0', '0.1.0'], ['0.1.1']];
        yield 'unstable #5' => ['1.0-BETA2', ['0.2', '0.1'], ['0.3', '1.0-BETA1', '1.0']];
        yield 'unstable #6' => ['1.0-ALPHA2', ['0.2', '0.1'], ['0.3', '1.0-BETA1', '1.0']];
        yield 'unstable #7' => ['1.0-ALPHA2', ['0.2', '0.1', '1.0'], ['1.1-BETA1', '1.1', '2.0-ALPHA1', '2.0-BETA1', '2.0']];

        yield 'stable #1' => ['1.3', ['1.0', '1.1'], ['1.2-BETA1', '1.2', '2.0-ALPHA1', '2.0-BETA1', '2.0']];
        yield 'stable #2' => ['1.3.0', ['1.0.0', '1.1.0'], ['1.1.1', '1.2.0-BETA1', '1.2.0', '2.0.0-ALPHA1', '2.0.0-BETA1', '2.0.0']];
        yield 'stable #3' => ['3.6', ['3.5-BETA1'], ['3.5-BETA2', '3.5-RC1', '3.5']];
        yield 'stable #4' => ['3.6', ['v3.4', 'v3.7'], ['3.8-BETA1', '3.8', '4.0-ALPHA1', '4.0-BETA1', '4.0']];
        yield 'stable #5' => ['3.6.0', ['v3.4.0', 'v3.7.0'], ['3.7.1', '3.8.0-BETA1', '3.8.0', '4.0.0-ALPHA1', '4.0.0-BETA1', '4.0.0']];

        yield 'stable with build)' => ['1.3.0.1', ['1.0.0.0', '1.1.0.0', '1.1.0.1'], ['1.1.1.0', '1.2.0.0-BETA1', '1.2.0.0', '2.0.0.0-ALPHA1', '2.0.0.0-BETA1', '2.0.0.0']];
    }

    /**
     * @param array<int, string> $existing
     * @param array<int, string> $possible
     */
    #[Test]
    #[DataProvider('provideNonContinuesVersions')]
    public function it_rejects_non_continues_version(string $new, array $existing, array $possible): void
    {
        $validator = new ContinuesVersionsValidator(...$this->createVersions($existing));

        self::assertFalse($validator->isContinues(Version::fromString($new)));
        self::assertVersionsEquals($this->createVersions($possible), $validator->getPossibleVersions());
    }

    /**
     * @param array<string, Version> $expected
     * @param array<int, Version>    $candidates
     */
    private static function assertVersionsEquals(array $expected, array $candidates): void
    {
        $formatter = static fn (Version $version): string => \sprintf("'%s' => '%s'", $version->format(), $version->full);

        $candidatesWithKey = $expectedWithDebug = [];

        foreach ($candidates as $v) {
            $candidatesWithKey[$v->format()] = $v->__debugInfo();
        }

        foreach ($expected as $key => $value) {
            $expectedWithDebug[$key] = $value->__debugInfo();
        }

        self::assertSame(
            $expectedWithDebug,
            $candidatesWithKey,
            \sprintf(
                'Expected [%s] but got [%s]',
                implode(', ', array_map($formatter, $expected)),
                implode(', ', array_map($formatter, $candidates)),
            ),
        );
    }
}
