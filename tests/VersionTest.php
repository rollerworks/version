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
use Rollerworks\Component\Version\Stability;
use Rollerworks\Component\Version\Version;
use Rollerworks\Component\Version\VersionType;

/**
 * @internal
 */
class VersionTest extends TestCase
{
    #[Test]
    public function it_creates_from_full_string(): void
    {
        $version = Version::fromString('1.0.0-beta-5');

        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertEquals(0, $version->patch);
        self::assertEquals(Stability::Beta, $version->stability);
        self::assertEquals(5, $version->metaver);
        self::assertNull($version->buildNr);

        self::assertEquals('1.0.0-BETA5', (string) $version);
    }

    #[Test]
    public function it_creates_with_explicit_stable(): void
    {
        $version = Version::fromString('1.0.0-stable');

        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertEquals(0, $version->patch);
        self::assertEquals(Stability::Stable, $version->stability);
        self::assertEquals(0, $version->metaver);
        self::assertNull($version->buildNr);

        self::assertEquals('1.0.0', (string) $version);
    }

    #[Test]
    public function it_creates_without_patch(): void
    {
        $version = Version::fromString('1.0');

        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertEquals(0, $version->patch);
        self::assertEquals(Stability::Stable, $version->stability);
        self::assertEquals(0, $version->metaver);
        self::assertNull($version->buildNr);

        self::assertEquals('1.0.0', (string) $version);
    }

    #[Test]
    public function it_creates_build_nr(): void
    {
        $version = Version::fromString('1.0.0.5');

        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertEquals(0, $version->patch);
        self::assertEquals(5, $version->buildNr);
        self::assertEquals(Stability::Stable, $version->stability);
        self::assertEquals(0, $version->metaver);

        self::assertEquals('1.0.0.5', (string) $version);
    }

    /**
     * @return iterable<string,string[]>
     */
    public static function provideValidFormats(): iterable
    {
        yield 'with prefix in lowercase' => ['v1.0.0', '1.0.0'];
        yield 'with prefix in uppercase' => ['V1.0.0', '1.0.0'];
        yield 'stability with hyphen' => ['1.0.0-alpha-1', '1.0.0-ALPHA1'];
        yield 'stability with dot' => ['1.0.0-alpha.1', '1.0.0-ALPHA1'];
        yield 'stability without patch' => ['1.0-alpha.1', '1.0.0-ALPHA1'];

        yield 'beta' => ['1.0.0-beta1', '1.0.0-BETA1'];
        yield 'RC' => ['1.0.0-RC1', '1.0.0-RC1'];
        yield 'dev suffix' => ['1.0.0-RC1-dev', '1.0.0-RC1'];

        // Build-nr
        yield 'build data' => ['1.0.0.0', '1.0.0.0'];
        yield 'build data 2' => ['1.0.0.0-RC1', '1.0.0.0-RC1'];
        yield 'build data 3' => ['1.0.0.0-RC1', '1.0.0.0-RC1'];
        yield 'full example' => ['1.0.0.5-RC1-dev', '1.0.0.5-RC1'];
    }

    #[Test]
    #[DataProvider('provideValidFormats')]
    public function it_supports_various_formats(string $version, string $expectedOutput): void
    {
        $version = Version::fromString($version);

        self::assertEquals($expectedOutput, (string) $version);
    }

    #[Test]
    public function it_compares_two_versions_are_equal(): void
    {
        $version = Version::fromString('1.0.0-beta-5');
        $version2 = Version::fromString('1.0.0-beta5');
        $version3 = Version::fromString('1.0.0-beta6');

        self::assertTrue($version->equalTo($version2));
        self::assertFalse($version->equalTo($version3));
    }

    #[Test]
    public function it_fails_for_invalid_format(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessageIsOrContains('Unable to parse version "1.0.0-WAT"');

        Version::fromString('1.0.0-WAT');
    }

    #[Test]
    public function it_fails_with_stable_plus_metaver(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessageIs('Meta version of the stability flag cannot be set for stable.');

        Version::fromString('1.0.0-stable-5');
    }

    /**
     * @return iterable<string, array<int, array<int, string>|string>>
     */
    public static function provideExpectedNextVersionCandidates(): iterable
    {
        yield 'alpha 0' => ['0.1.0', ['0.1.1', '0.2.0', '1.0.0-BETA1', '1.0.0']];
        yield 'beta' => ['1.0.0-beta-5', ['1.0.0-BETA6', '1.0.0-RC1', '1.0.0']];
        yield 'beta 2' => ['2.0.0-beta-5', ['2.0.0-BETA6', '2.0.0-RC1', '2.0.0']];
        yield 'beta 3' => ['v3.5-beta1', ['v3.5-beta2', 'v3.5-RC1', 'v3.5']];
        yield 'rc' => ['1.0.0-RC5', ['1.0.0-RC6', '1.0.0']];

        yield 'stable major' => ['1.0.0', ['1.0.1', '1.1.0-BETA1', '1.1.0', '2.0.0-ALPHA1', '2.0.0-BETA1', '2.0.0']];
        yield 'stable major 2' => ['2.0.0', ['2.0.1', '2.1.0-BETA1', '2.1.0', '3.0.0-ALPHA1', '3.0.0-BETA1', '3.0.0']];
        yield 'stable with minor' => ['1.1.0', ['1.1.1', '1.2.0-BETA1', '1.2.0', '2.0.0-ALPHA1', '2.0.0-BETA1', '2.0.0']];
        yield 'stable with minor and patch' => ['1.1.1', ['1.1.2', '1.2.0-BETA1', '1.2.0', '2.0.0-ALPHA1', '2.0.0-BETA1', '2.0.0']];
        yield 'stable with patch' => ['1.0.1', ['1.0.2', '1.1.0-BETA1', '1.1.0', '2.0.0-ALPHA1', '2.0.0-BETA1', '2.0.0']];
    }

    /**
     * @param string[] $expected
     */
    #[Test]
    #[DataProvider('provideExpectedNextVersionCandidates')]
    public function it_provides_next_version_candidates(string $current, array $expected): void
    {
        $candidates = Version::fromString($current)->getNextVersionCandidates();
        $expected = array_map(Version::fromString(...), $expected);

        self::assertEquals($expected, $candidates);
    }

    /**
     * @return iterable<string,string[]>
     */
    public static function provideExpectedIncreasedVersion(): iterable
    {
        yield 'patch with patch 0' => ['0.1.0', '0.1.1', 'patch'];
        yield 'patch with patch 1' => ['0.1.1', '0.1.2', 'patch'];
        yield 'patch with patch stable' => ['1.0.0', '1.0.1', 'patch'];

        // Patch with unstable. Increase metaver instead
        yield 'patch with patch alpha' => ['1.0.0-alpha1', '1.0.0-alpha2', 'patch'];
        yield 'patch with patch beta' => ['1.0.0-beta1', '1.0.0-beta2', 'patch'];
        yield 'patch with patch rc' => ['1.0.0-rc1', '1.0.0-rc2', 'patch'];

        // Minor, patch must be reset
        yield 'minor with patch 0' => ['0.1.0', '0.2.0', 'minor'];
        yield 'minor with patch 1' => ['0.1.1', '0.2.0', 'minor'];
        yield 'minor with new minor' => ['1.1.2', '1.2.0', 'minor'];

        // Major, minor and patch must be reset
        yield 'major.0.0' => ['0.1.0', '1.0.0', 'major'];
        yield 'major.1.0' => ['0.1.0', '1.0.0', 'major'];
        yield 'major.1.1' => ['0.1.1', '1.0.0', 'major'];
        yield 'major from beta' => ['1.0.0-beta1', '1.0.0', 'major'];
        yield 'major from 1.0' => ['1.0.0', '2.0.0', 'major'];
        yield 'major from 2.0-beta' => ['2.0.0-beta1', '2.0.0', 'major'];

        // Next
        yield 'new next from 0.1.0' => ['0.1.0', '0.2.0', 'next'];
        yield 'new next from 0.1.1' => ['0.1.1', '0.2.0', 'next'];
        yield 'new next from alpha' => ['1.0.0-alpha6', '1.0.0-alpha7', 'next'];
        yield 'new next from alpha 2' => ['1.2.0-alpha6', '1.2.0-alpha7', 'next'];
        yield 'new next from beta' => ['1.0.0-beta1', '1.0.0-beta2', 'next'];
        yield 'new next from current stable' => ['1.0.0', '1.1.0', 'next'];

        // Alpha
        yield 'next alpha' => ['1.0.0-alpha1', '1.0.0-alpha2', 'alpha'];
        yield 'new alpha' => ['1.0.0', '1.1.0-alpha1', 'alpha'];

        // Beta
        yield 'next beta' => ['1.0.0-beta1', '1.0.0-beta2', 'beta'];
        yield 'new beta' => ['1.0.0', '1.1.0-beta1', 'beta'];
        yield 'new beta from alpha' => ['1.0.0-alpha1', '1.0.0-beta1', 'beta'];

        // RC
        yield 'next rc' => ['1.0.0-rc1', '1.0.0-rc2', 'rc'];
        yield 'new rc' => ['1.0.0', '1.1.0-rc1', 'rc'];
        yield 'new rc from alpha' => ['1.0.0-alpha1', '1.0.0-rc1', 'rc'];
        yield 'new rc from beta' => ['1.0.0-beta1', '1.0.0-rc1', 'rc'];

        // Stable
        yield 'new stable from 0.0' => ['0.1.0', '1.0.0', 'stable'];
        yield 'new stable from alpha' => ['1.0.0-alpha6', '1.0.0', 'stable'];
        yield 'new stable from beta' => ['1.0.0-beta1', '1.0.0', 'stable'];
        yield 'new stable from beta 2' => ['1.2.0-beta1', '1.2.0', 'stable'];
        yield 'new stable from current stable' => ['1.0.0', '1.1.0', 'stable'];
    }

    #[Test]
    #[DataProvider('provideExpectedIncreasedVersion')]
    public function it_increases_to_next_version(string $current, string $expected, string $stability): void
    {
        self::assertEquals(
            Version::fromString($expected),
            Version::fromString($current)->getNextIncreaseOf(VersionType::from($stability)),
        );
    }
}
