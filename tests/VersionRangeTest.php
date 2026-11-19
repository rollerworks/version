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

use Composer\Semver\Constraint\Constraint;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rollerworks\Component\Version\Version;
use Rollerworks\Component\Version\VersionRange;

/**
 * @internal
 */
final class VersionRangeTest extends TestCase
{
    #[Test]
    public function it_creates_empty_range(): void
    {
        $range = VersionRange::createFromString('');
        self::assertSame('', (string) $range);
        self::assertSame('null', json_encode($range, \JSON_THROW_ON_ERROR));
        self::assertNull($range->lowerBound);
        self::assertNull($range->upperBound);
        self::assertFalse($range->isWithinRange(Version::create()));

        $range = new VersionRange();
        self::assertSame('', (string) $range);
        self::assertSame('null', json_encode($range, \JSON_THROW_ON_ERROR));
        self::assertNull($range->lowerBound);
        self::assertNull($range->upperBound);
        self::assertFalse($range->isWithinRange(Version::create()));
    }

    #[Test]
    public function it_creates(): void
    {
        $range = new VersionRange($l = Version::fromString('1.0', options: Version::INPUT_FILL_PATCH), $u = Version::fromString('2.0'), true, true);
        self::assertSame('>=1.0.0 <=2.0', (string) $range);
        self::assertSame($l, $range->lowerBound);
        self::assertSame($u, $range->upperBound);
        self::assertSame('1.0.0 - 2.0', $range->toNormalizedStr());
        self::assertIsJsonReconstructable($range);

        $range = new VersionRange($l = Version::fromString('1.0', options: Version::INPUT_FILL_PATCH), $u = Version::fromString('2.0'), false, false);
        self::assertSame('>1.0.0 <2.0', (string) $range);
        self::assertSame($l, $range->lowerBound);
        self::assertSame($u, $range->upperBound);
        self::assertSame('>1.0.0 - <2.0', $range->toNormalizedStr());
        self::assertIsJsonReconstructable($range);

        $range = new VersionRange($l = Version::fromString('1.0', outputFormat: 'v{major}.{minor}.{patch}'), $u = Version::fromString('2.0.5', outputFormat: 'v{major}.{minor}'), true, true);
        self::assertSame('>=v1.0.0 <=v2.0', (string) $range);
        self::assertSame($l, $range->lowerBound);
        self::assertSame($u, $range->upperBound);
        self::assertSame('1.0.0 - 2.0.5', $range->toNormalizedStr('{major}.{minor}.{patch}'));
        self::assertIsJsonReconstructable($range);
    }

    #[Test]
    public function it_creates_from_string(): void
    {
        $range = VersionRange::createFromString('>1.0, <2.0');

        self::assertSame('>1.0.0.0 <2.0.0.0', (string) $range);
        self::assertSame('1.0.0.0', (string) $range->lowerBound);
        self::assertSame('2.0.0.0', (string) $range->upperBound);
        self::assertSame('>1.0.0.0 - <2.0.0.0', $range->toNormalizedStr());
        self::assertIsJsonReconstructable($range);

        $range = VersionRange::createFromString('^1.0');
        self::assertSame('>=1.0.0.0 <2.0.0.0', (string) $range);
        self::assertSame('1.0.0.0', (string) $range->lowerBound);
        self::assertSame('2.0.0.0', (string) $range->upperBound);
        self::assertSame('1.0.0.0 - <2.0.0.0', $range->toNormalizedStr());
        self::assertIsJsonReconstructable($range);
    }

    #[Test]
    public function it_creates_from_constraint(): void
    {
        $range = VersionRange::createFromConstraint(new Constraint(Constraint::STR_OP_GT, '1.0'));
        self::assertSame('1.0', (string) $range->lowerBound);
        self::assertNull($range->upperBound);
        self::assertFalse($range->lowerInclusive);
        self::assertFalse($range->upperInclusive);
        self::assertSame('>1.0', (string) $range);
        self::assertSame('>1.0', $range->toNormalizedStr());
        self::assertIsJsonReconstructable($range);

        $range = VersionRange::createFromConstraint(new Constraint(Constraint::STR_OP_LT, '1.0'));
        self::assertNull($range->lowerBound);
        self::assertSame('1.0', (string) $range->upperBound);
        self::assertFalse($range->lowerInclusive);
        self::assertFalse($range->upperInclusive);
        self::assertSame('<1.0', (string) $range);
        self::assertSame('<1.0', $range->toNormalizedStr());
        self::assertIsJsonReconstructable($range);

        $range = VersionRange::createFromConstraint(new Constraint(Constraint::STR_OP_GE, '1.0'));
        self::assertSame('1.0', (string) $range->lowerBound);
        self::assertNull($range->upperBound);
        self::assertTrue($range->lowerInclusive);
        self::assertFalse($range->upperInclusive);
        self::assertSame('>=1.0', (string) $range);
        self::assertSame('>=1.0', $range->toNormalizedStr());
        self::assertIsJsonReconstructable($range);

        $range = VersionRange::createFromConstraint(new Constraint(Constraint::STR_OP_LE, '1.0'));
        self::assertNull($range->lowerBound);
        self::assertSame('1.0', (string) $range->upperBound);
        self::assertFalse($range->lowerInclusive);
        self::assertTrue($range->upperInclusive);
        self::assertSame('<=1.0', (string) $range);
        self::assertSame('<=1.0', $range->toNormalizedStr());
        self::assertIsJsonReconstructable($range);
    }

    #[Test]
    public function it_checks_whether_is_within_range(): void
    {
        $range = VersionRange::createFromString('>=1.0, <2.0');
        self::assertTrue($range->isWithinRange(Version::fromString('1.0.0.0')));
        self::assertTrue($range->isWithinRange(Version::fromString('1.0.0.1')));
        self::assertTrue($range->isWithinRange(Version::fromString('1.9.9.9')));
        self::assertFalse($range->isWithinRange(Version::fromString('0.9.9.9')));
        self::assertFalse($range->isWithinRange(Version::fromString('2.0.0.0')));

        $range = VersionRange::createFromString('>=1.0, <=2.0');
        self::assertTrue($range->isWithinRange(Version::fromString('1.0.0.0')));
        self::assertTrue($range->isWithinRange(Version::fromString('1.0.0.1')));
        self::assertTrue($range->isWithinRange(Version::fromString('1.9.9.9')));
        self::assertTrue($range->isWithinRange(Version::fromString('2.0.0.0')));
        self::assertFalse($range->isWithinRange(Version::fromString('0.9.9.9')));

        $range = VersionRange::createFromString('^0.3');
        self::assertTrue($range->isWithinRange(Version::fromString('0.3.0.0')));
        self::assertTrue($range->isWithinRange(Version::fromString('0.3.0.1')));
        self::assertTrue($range->isWithinRange(Version::fromString('0.3.9.9')));
        self::assertFalse($range->isWithinRange(Version::fromString('0.2.9.9')));
        self::assertFalse($range->isWithinRange(Version::fromString('0.4.0.0')));
    }

    #[Test]
    public function it_creates_from_list(): void
    {
        $range = VersionRange::createFromList([
            $lower = Version::fromString('1.0.0.0'),
            $upper = Version::fromString('1.9.0.0'),
            Version::fromString('1.0.0.1'),
            Version::fromString('1.5.0.1'),
        ]);
        self::assertEquals($lower, $range->lowerBound);
        self::assertEquals($upper, $range->upperBound);
        self::assertSame('>=1.0.0.0 <=1.9.0.0', (string) $range);

        $range = VersionRange::createFromList(
            [
                $lower = Version::fromString('1.0.0.0'),
                $upper = Version::fromString('1.9.0.0'),
                Version::fromString('1.0.0.1'),
                Version::fromString('1.3.7.1'),
            ],
            lowerInclusive: false,
            upperInclusive: false,
        );
        self::assertEquals($lower, $range->lowerBound);
        self::assertEquals($upper, $range->upperBound);
        self::assertSame('>1.0.0.0 <1.9.0.0', (string) $range);

        $range = VersionRange::createFromList(
            [
                $lower = Version::fromString('1.0.0.0'),
                $upper = Version::fromString('1.9.0.0'),
                Version::fromString('1.0.0.1'),
                Version::fromString('1.3.7.1'),
            ],
            upperInclusive: false,
        );
        self::assertEquals($lower, $range->lowerBound);
        self::assertEquals($upper, $range->upperBound);
        self::assertSame('>=1.0.0.0 <1.9.0.0', (string) $range);

        $range = VersionRange::createFromList(
            [
                $lower = Version::fromString('1.0.0.0'),
                $upper = Version::fromString('1.9.0.0'),
                Version::fromString('1.0.0.1'),
                Version::fromString('1.3.7.1'),
            ],
            lowerInclusive: false,
        );
        self::assertEquals($lower, $range->lowerBound);
        self::assertEquals($upper, $range->upperBound);
        self::assertSame('>1.0.0.0 <=1.9.0.0', (string) $range);
    }

    #[Test]
    public function it_checks_bounds_coverage(): void
    {
        $range = new VersionRange(Version::fromString('8.3'), Version::fromString('8.5'), true, true);

        self::assertTrue(
            $range->areBoundsCoveredBy(
                Version::fromString('8.0'),
                Version::fromString('8.3'),
                Version::fromString('8.5'),
                Version::fromString('8.6'),
            )
        );

        self::assertFalse(
            $range->areBoundsCoveredBy(
                Version::fromString('8.1'),
                Version::fromString('8.4'),
            )
        );

        self::assertFalse($range->areBoundsCoveredBy(Version::fromString('8.3')));
        self::assertFalse($range->areBoundsCoveredBy(Version::fromString('8.5')));
    }

    #[Test]
    public function it_gives_false_for_bounds_coverage_with_only_one_bound(): void
    {
        $range = new VersionRange(Version::fromString('8.3'), lowerInclusive: true, upperInclusive: true);
        self::assertFalse(
            $range->areBoundsCoveredBy(
                Version::fromString('8.0'),
                Version::fromString('8.3'),
                Version::fromString('8.5'),
                Version::fromString('8.6'),
            )
        );

        self::assertFalse(
            $range->areBoundsCoveredBy(
                Version::fromString('8.1'),
                Version::fromString('8.4'),
            )
        );

        self::assertFalse($range->areBoundsCoveredBy(Version::fromString('8.3')));

        self::assertFalse($range->areBoundsCoveredBy(Version::fromString('8.5')));

        $range = new VersionRange(upperBound: Version::fromString('8.3'), lowerInclusive: true, upperInclusive: true);
        self::assertFalse(
            $range->areBoundsCoveredBy(
                Version::fromString('8.0'),
                Version::fromString('8.3'),
                Version::fromString('8.5'),
                Version::fromString('8.6'),
            )
        );

        self::assertFalse(
            $range->areBoundsCoveredBy(
                Version::fromString('8.1'),
                Version::fromString('8.4'),
            )
        );

        self::assertFalse($range->areBoundsCoveredBy(Version::fromString('8.3')));
        self::assertFalse($range->areBoundsCoveredBy(Version::fromString('8.5')));
    }

    #[Test]
    public function it_cannot_check_exclusive_bounds_for_coverage_lower(): void
    {
        $range = new VersionRange(Version::fromString('8.3'), Version::fromString('8.5'), false, true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageContains('Cannnot check bounds covering unless both bounds are inclusive.');

        $range->areBoundsCoveredBy(Version::fromString('8.0'));
    }

    #[Test]
    public function it_cannot_check_exclusive_bounds_for_coverage_upper(): void
    {
        $range = new VersionRange(Version::fromString('8.3'), Version::fromString('8.5'), true, false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageContains('Cannnot check bounds covering unless both bounds are inclusive.');

        $range->areBoundsCoveredBy(Version::fromString('8.0'));
    }

    #[Test]
    public function it_fails_from_array_with_unexpected_keys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageContains('Invalid version range data, unexpected keys provided: "format", "constraint".');

        /** @phpstan-ignore-next-line */
        VersionRange::fromArray(['lower' => '1.0', 'format' => true, 'constraint' => 1]);
    }

    private function expectExceptionMessageContains(string $message): void
    {
        /** @phpstan-ignore-next-line PHPUnit forward compatibility */
        if (method_exists($this, 'expectExceptionMessageIsOrContains')) {
            $this->expectExceptionMessageIsOrContains($message);
        } else {
            $this->expectExceptionMessage($message);
        }
    }

    private static function assertIsJsonReconstructable(VersionRange $range): void
    {
        self::assertJsonStringEqualsJsonString(
            json_encode(['lower' => $range->lowerBound, 'upper' => $range->upperBound, 'lowerInclusive' => $range->lowerInclusive, 'upperInclusive' => $range->upperInclusive], \JSON_THROW_ON_ERROR),
            json_encode($range, \JSON_THROW_ON_ERROR)
        );

        self::assertEquals(
            $range,
            VersionRange::fromArray(
                json_decode(
                    json_encode($range, \JSON_THROW_ON_ERROR),
                    associative: true,
                    depth: 5,
                    flags: \JSON_THROW_ON_ERROR,
                ),
            ),
        );
    }
}
