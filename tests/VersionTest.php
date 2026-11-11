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
use Rollerworks\Component\Version\VersionIdentifier;

/**
 * @internal
 */
class VersionTest extends TestCase
{
    protected function setUp(): void
    {
        $this->registerComparator(new VersionComparator());
    }

    #[Test]
    public function it_creates_from_full_string(): void
    {
        $version = Version::fromString('1.0.0-beta-5');

        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertEquals(0, $version->patch);
        self::assertNull($version->build);
        self::assertEquals(Stability::Beta, $version->stability);
        self::assertEquals(5, $version->preRelease);
        self::assertEquals('1.0.0.0-BETA5', $version->full);
        self::assertEquals('1.0.0-BETA5', $version->fullWithoutBuild);

        self::assertEquals([
            'formatted' => $version->formatted,
            'full' => $version->full,
            'version' => [
                'major' => 1,
                'minor' => 0,
                'patch' => 0,
                'build' => null,
                'stability' => Stability::Beta,
                'pre_release' => 5,
            ],
            'output_format' => $version->outputFormat,
            'format_of_increment' => '{major}.{minor}.{?patch}.{?build}-{pre-release:upper}x',
        ], $version->__debugInfo());
    }

    #[Test]
    public function it_creates_with_explicit_stable(): void
    {
        $version = Version::fromString('1.0.0-stable');

        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertEquals(0, $version->patch);
        self::assertNull($version->build);
        self::assertEquals(Stability::Stable, $version->stability);
        self::assertEquals(0, $version->preRelease);
        self::assertEquals('1.0.0.0', $version->full);
        self::assertEquals('1.0.0', $version->fullWithoutBuild);
    }

    #[Test]
    public function it_creates_without_patch(): void
    {
        $version = Version::fromString('1.0');

        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertEquals(0, $version->patch);
        self::assertNull($version->build);
        self::assertEquals(Stability::Stable, $version->stability);
        self::assertEquals(0, $version->preRelease);
        self::assertEquals('1.0.0.0', $version->full);
        self::assertEquals('1.0.0', $version->fullWithoutBuild);
    }

    #[Test]
    public function it_creates_with_build(): void
    {
        $version = Version::fromString('1.0.0.5');

        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertEquals(0, $version->patch);
        self::assertEquals(5, $version->build);
        self::assertEquals(Stability::Stable, $version->stability);
        self::assertEquals(0, $version->preRelease);
        self::assertEquals('1.0.0.5', $version->full);
        self::assertEquals('1.0.0', $version->fullWithoutBuild);

        self::assertEquals([
            'formatted' => $version->formatted,
            'full' => $version->full,
            'version' => [
                'major' => 1,
                'minor' => 0,
                'patch' => 0,
                'build' => 5,
                'stability' => Stability::Stable,
                'pre_release' => 0,
            ],
            'output_format' => $version->outputFormat,
            'format_of_increment' => '{major}.{minor}.{?patch}.{?build}-{pre-release:upper}x',
        ], $version->__debugInfo());
    }

    #[Test]
    public function it_creates_with_ignored_patch(): void
    {
        $version = Version::fromString('1.0.5.5', options: Version::INPUT_IGNORE_PATCH);

        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertNull($version->patch);
        self::assertNull($version->build);
        self::assertEquals(Stability::Stable, $version->stability);
        self::assertEquals(0, $version->preRelease);
        self::assertEquals('1.0.0.0', $version->full);
        self::assertEquals('1.0.0', $version->fullWithoutBuild);

        self::assertEquals([
            'formatted' => $version->formatted,
            'full' => $version->full,
            'version' => [
                'major' => 1,
                'minor' => 0,
                'patch' => null,
                'build' => null,
                'stability' => Stability::Stable,
                'pre_release' => 0,
            ],
            'output_format' => $version->outputFormat,
            'format_of_increment' => '{major}.{minor}.{?patch}.{?build}-{pre-release:upper}x',
        ], $version->__debugInfo());
    }

    #[Test]
    public function it_creates_with_ignored_build(): void
    {
        $version = Version::fromString('1.0.1.5', options: Version::INPUT_IGNORE_BUILD);
        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertEquals(1, $version->patch);
        self::assertNull($version->build);
        self::assertEquals(Stability::Stable, $version->stability);
        self::assertEquals(0, $version->preRelease);
        self::assertEquals('1.0.1.0', $version->full);
        self::assertEquals('1.0.1', $version->fullWithoutBuild);

        self::assertEquals([
            'formatted' => $version->formatted,
            'full' => $version->full,
            'version' => [
                'major' => 1,
                'minor' => 0,
                'patch' => 1,
                'build' => null,
                'stability' => Stability::Stable,
                'pre_release' => 0,
            ],
            'output_format' => $version->outputFormat,
            'format_of_increment' => '{major}.{minor}.{?patch}.{?build}-{pre-release:upper}x',
        ], $version->__debugInfo());
    }

    #[Test]
    public function it_creates_with_ignored_pre_release(): void
    {
        $version = Version::fromString('1.0.5.3-beta1', options: Version::INPUT_IGNORE_PRE_RELEASE);

        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertEquals(5, $version->patch);
        self::assertEquals(3, $version->build);
        self::assertEquals(Stability::Stable, $version->stability);
        self::assertEquals(0, $version->preRelease);
        self::assertEquals('1.0.5.3', $version->full);
        self::assertEquals('1.0.5', $version->fullWithoutBuild);
    }

    #[Test]
    public function it_creates_with_filled_patch(): void
    {
        $version = Version::fromString('1.0', options: Version::INPUT_FILL_PATCH);

        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertEquals(0, $version->patch);
        self::assertNull($version->build);
        self::assertEquals(Stability::Stable, $version->stability);
        self::assertEquals(0, $version->preRelease);
        self::assertEquals('1.0.0.0', $version->full);
        self::assertEquals('1.0.0', $version->fullWithoutBuild);

        $version = Version::fromString('1.0.5', options: Version::INPUT_FILL_PATCH);
        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertEquals(5, $version->patch);
        self::assertNull($version->build);
        self::assertEquals(Stability::Stable, $version->stability);
        self::assertEquals(0, $version->preRelease);
        self::assertEquals('1.0.5.0', $version->full);
        self::assertEquals('1.0.5', $version->fullWithoutBuild);
    }

    #[Test]
    public function it_creates_with_filled_build(): void
    {
        $version = Version::fromString('1.0', options: Version::INPUT_FILL_BUILD);
        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertEquals(0, $version->patch);
        self::assertEquals(0, $version->build);
        self::assertEquals(Stability::Stable, $version->stability);
        self::assertEquals(0, $version->preRelease);
        self::assertEquals('1.0.0.0', $version->full);
        self::assertEquals('1.0.0', $version->fullWithoutBuild);

        $version = Version::fromString('1.0.5', options: Version::INPUT_FILL_BUILD);
        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertEquals(5, $version->patch);
        self::assertEquals(0, $version->build);
        self::assertEquals(Stability::Stable, $version->stability);
        self::assertEquals(0, $version->preRelease);
        self::assertEquals('1.0.5.0', $version->full);
        self::assertEquals('1.0.5', $version->fullWithoutBuild);

        $version = Version::fromString('1.0.5.8', options: Version::INPUT_FILL_BUILD);
        self::assertEquals(1, $version->major);
        self::assertEquals(0, $version->minor);
        self::assertEquals(5, $version->patch);
        self::assertEquals(8, $version->build);
        self::assertEquals(Stability::Stable, $version->stability);
        self::assertEquals(0, $version->preRelease);
        self::assertEquals('1.0.5.8', $version->full);
        self::assertEquals('1.0.5', $version->fullWithoutBuild);
    }

    #[Test]
    #[DataProvider('provideInvalidInputOptions')]
    public function reject_invalid_input_options(int $options, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        Version::fromString('1.0.5.8', options: $options);
    }

    /**
     * @return iterable<array{0: int, 1: string}>
     */
    public static function provideInvalidInputOptions(): iterable
    {
        yield [Version::INPUT_FILL_PATCH | Version::INPUT_IGNORE_PATCH, 'Cannot combine options "INPUT_IGNORE_PATCH" and "INPUT_FILL_PATCH".'];
        yield [Version::INPUT_FILL_BUILD | Version::INPUT_IGNORE_BUILD, 'Cannot combine options "INPUT_IGNORE_BUILD" and "INPUT_FILL_BUILD".'];
        yield [Version::INPUT_IGNORE_PATCH | Version::INPUT_FILL_BUILD, 'Cannot combine options "INPUT_IGNORE_PATCH" and "INPUT_FILL_BUILD".'];
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideValidFormats(): iterable
    {
        yield 'with prefix in lowercase' => ['v1.0.0', '1.0.0.0'];
        yield 'with prefix in uppercase' => ['V1.0.0', '1.0.0.0'];
        yield 'stability with hyphen' => ['1.0.0-alpha-1', '1.0.0.0-ALPHA1'];
        yield 'stability with dot' => ['1.0.0-alpha.1', '1.0.0.0-ALPHA1'];
        yield 'stability without patch' => ['1.0-alpha.1', '1.0.0.0-ALPHA1'];

        yield 'beta' => ['1.0.0-beta1', '1.0.0.0-BETA1'];
        yield 'RC' => ['1.0.0-RC1', '1.0.0.0-RC1'];
        yield 'dev suffix' => ['1.0.0-RC1-dev', '1.0.0.0-RC1'];

        // Build
        yield 'build data' => ['1.0.0.0', '1.0.0.0'];
        yield 'build data 2' => ['1.0.0.0-RC1', '1.0.0.0-RC1'];
        yield 'build data 3' => ['1.0.0.0-RC1', '1.0.0.0-RC1'];
        yield 'full example' => ['1.0.0.5-RC1-dev', '1.0.0.5-RC1'];
    }

    #[Test]
    #[DataProvider('provideValidFormats')]
    public function it_accepts_various_formats(string $version, string $expectedOutput): void
    {
        $version = Version::fromString($version);

        self::assertEquals($version, (string) $version);
        self::assertEquals($expectedOutput, $version->full);
    }

    #[Test]
    public function it_compares_two_versions_are_equal(): void
    {
        $version = Version::fromString('1.0.0');
        self::assertTrue($version->equalTo($version));

        self::assertTrue(Version::fromString('0.0.0')->equalTo(Version::fromString('0.0.0')));
        self::assertTrue(Version::fromString('0.1.0')->equalTo(Version::fromString('0.1.0')));
        self::assertTrue(Version::fromString('0.1.1')->equalTo(Version::fromString('0.1.1')));
        self::assertTrue(Version::fromString('1.0.0')->equalTo(Version::fromString('1.0.0')));
        self::assertTrue(Version::fromString('1.0.0')->equalTo(Version::fromString('1.0.0-stable')));

        // Pre-release
        self::assertTrue(Version::fromString('1.0.0-Alpha-1')->equalTo(Version::fromString('1.0.0-ALPHA1')));
        self::assertTrue(Version::fromString('1.0.0-beta-5')->equalTo(Version::fromString('1.0.0-beta5')));
        self::assertTrue(Version::fromString('1.0.0-beta6')->equalTo(Version::fromString('1.0.0-beta6')));
        self::assertTrue(Version::fromString('1.0.0-RC1')->equalTo(Version::fromString('1.0.0-RC1')));

        // Build
        self::assertTrue(Version::fromString('1.0.0.0')->equalTo(Version::fromString('1.0.0')));
        self::assertTrue(Version::fromString('1.0.0.6')->equalTo(Version::fromString('1.0.0.0'))); // Equals because build is ignored
        self::assertTrue(Version::fromString('1.0.0.0')->equalTo(Version::fromString('1.0.0.0'), ignoreBuild: false));
        self::assertTrue(Version::fromString('1.0.0')->equalTo(Version::fromString('1.0.0'), ignoreBuild: false));

        // Build is always filled (in full format)
        self::assertTrue(Version::fromString('1.0.0.0')->equalTo(Version::fromString('1.0.0'), ignoreBuild: false));

        // Falseness
        self::assertFalse(Version::fromString('0.1.0')->equalTo(Version::fromString('0.2.0')));
        self::assertFalse(Version::fromString('0.1.0')->equalTo(Version::fromString('2.0.0')));
        self::assertFalse(Version::fromString('0.1.0')->equalTo(Version::fromString('1.1.0')));
        self::assertFalse(Version::fromString('1.0.0-beta-5')->equalTo(Version::fromString('1.0.0-beta6')));

        // Builds not equal
        self::assertFalse(Version::fromString('1.0.0.0')->equalTo(Version::fromString('1.0.0.6'), ignoreBuild: false));
    }

    #[Test]
    public function it_compares_versions(): void
    {
        $version = Version::fromString('1.1.0');
        $versionWithBuild = Version::fromString('1.1.0.1');

        $beta1 = Version::fromString('1.0.0-BETA1');
        $beta2 = Version::fromString('1.0.0-BETA-2');

        $beta1WithBuild = Version::fromString('1.0.0.5-BETA1');
        $beta2bWithBuild = Version::fromString('1.0.0.8-beta.1');
        $beta2WithBuild = Version::fromString('1.0.0.0-BETA2');

        self::assertTrue($version->equals($version));
        self::assertTrue($version->equals($versionWithBuild));
        self::assertTrue($versionWithBuild->equals($versionWithBuild, false));
        self::assertFalse($version->equals($versionWithBuild, false));

        self::assertTrue($version->higherThanOrEqual($versionWithBuild));
        self::assertTrue($version->higherThanOrEqual($beta1));
        self::assertTrue($versionWithBuild->higherThanOrEqual($version, false));
        self::assertTrue($versionWithBuild->higherThanOrEqual($versionWithBuild, false));

        self::assertFalse($beta1->higherThanOrEqual($versionWithBuild, false));

        self::assertTrue($version->lowerThanOrEqual($versionWithBuild));
        self::assertTrue($version->lowerThanOrEqual($versionWithBuild, false));
        self::assertTrue($versionWithBuild->lowerThanOrEqual($versionWithBuild, false));

        self::assertTrue($beta1WithBuild->lowerThan($beta2WithBuild));
        self::assertTrue($beta1->lowerThan($beta2WithBuild));

        self::assertTrue($beta2WithBuild->higherThan($beta1WithBuild));
        self::assertTrue($beta2->higherThan($beta1WithBuild));

        self::assertTrue($beta1WithBuild->lowerThan($beta2bWithBuild, false));
        self::assertTrue($beta2bWithBuild->higherThan($beta1WithBuild, false));

        self::assertFalse($beta2->higherThan($beta1WithBuild, false));
    }

    /**
     * @return iterable<string, array{0: Version, 1: string|null, 2: string}>
     */
    public static function provide_formats_with_custom_format(): iterable
    {
        $stableVersion = Version::fromString('1.1.0');
        $stableVersionWithBuild = Version::fromString('1.1.0.1');
        $alphaVersion = Version::fromString('1.0.0.0-BETA1');

        $versionWithoutBuild = Version::fromString('1.0.0-BETA1');
        $versionWithCustomFormat = Version::fromString('1.0-BETA1', outputFormat: '{major}.{minor}.{patch}.{build}-{pre-release:title}.x');
        $versionWithoutPatch = Version::fromString('1.0');

        yield 'Default format' => [$stableVersion, null, '1.1.0'];
        yield 'Default format (with without patch)' => [$versionWithoutPatch, null, '1.0'];
        yield 'Default format (with build)' => [$stableVersionWithBuild, null, '1.1.0.1'];
        yield 'Default format (with pre-release)' => [$alphaVersion, null, '1.0.0.0-BETA1'];
        yield 'Default custom format' => [$versionWithCustomFormat, null, '1.0.0.0-Beta.1'];

        yield 'Custom format' => [$alphaVersion, '{major}.{minor}.{patch}.{build}-{pre-release:lower}-x', '1.0.0.0-beta-1'];
        yield 'Custom format (v prefix)' => [$alphaVersion, 'v{major}.{minor}.{patch}.{build}-{pre-release:lower}-x', 'v1.0.0.0-beta-1'];
        yield 'Custom format (V prefix)' => [$alphaVersion, 'V{major}.{minor}.{patch}.{build}-{pre-release:lower}-x', 'V1.0.0.0-beta-1'];
        yield 'Custom format (without casing)' => [$alphaVersion, '{major}.{minor}-{pre-release}.x', '1.0-BETA.1'];

        yield 'Custom format (with optional patch)' => [$versionWithoutPatch, '{major}.{minor}.{?patch}.{?build}-{pre-release:lower}-x', '1.0'];
        yield 'Custom format (with required patch)' => [$versionWithoutPatch, '{major}.{minor}.{patch}.{?build}-{pre-release:lower}-x', '1.0.0'];

        yield 'Custom format (with build)' => [$stableVersionWithBuild, '{major}.{minor}.{patch}.{?build}-{pre-release:lower}-x', '1.1.0.1'];
        yield 'Custom format (with required build)' => [$versionWithoutBuild, '{major}.{minor}.{patch}.{build}-{pre-release:lower}-x', '1.0.0.0-beta-1'];
        yield 'Custom format (with optional build)' => [$versionWithoutBuild, '{major}.{minor}.{patch}.{?build}-{pre-release:lower}-x', '1.0.0-beta-1'];
    }

    #[Test]
    #[DataProvider('provide_formats_with_custom_format')]
    public function formats_with_custom_format(Version $version, ?string $format, string $expected): void
    {
        self::assertEquals($expected, $version->format($format));
    }

    #[Test]
    public function it_fails_for_invalid_format(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Unable to parse version "1.0.0-WAT"');

        Version::fromString('1.0.0-WAT');
    }

    #[Test]
    public function it_fails_with_stable_plus_pre_release(): void
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Unable to parse version "1.0.0-stable-5" Expects an SemVer compatible version. Pre-release "stable" cannot have a number.');

        Version::fromString('1.0.0-stable-5');
    }

    /**
     * @return iterable<string, array{0: string, 1: string[], 2?: int}>
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

        // Without patch
        yield 'alpha without patch' => ['0.1', ['0.2.0', '1.0.0-BETA1', '1.0.0']];
        yield 'stable without patch' => ['1.0', ['1.1.0-BETA1', '1.1.0', '2.0.0-ALPHA1', '2.0.0-BETA1', '2.0.0']];

        // With build
        yield 'alpha with build' => ['0.1.0.0', ['0.1.1.0', '0.1.0.1', '0.2.0.0', '1.0.0.0-BETA1', '1.0.0.0']];
        yield 'stable with build' => ['1.0.0.0', ['1.0.1.0', '1.0.0.1', '1.1.0.0-BETA1', '1.1.0.0', '2.0.0.0-ALPHA1', '2.0.0.0-BETA1', '2.0.0.0']];

        // With exclusions
        yield 'alpha 0 (exclude patch)' => ['0.1.0', ['0.2.0', '1.0.0-BETA1', '1.0.0'], Version::CANDIDATE_EXCLUDE_PATCH];
        yield 'alpha 0 with build (exclude patch)' => ['0.1.0.5', ['0.1.0.6', '0.2.0.0', '1.0.0-BETA1', '1.0.0'], Version::CANDIDATE_EXCLUDE_PATCH];
        yield 'alpha 0 with build (exclude build)' => ['0.1.0.5', ['0.1.1.0', '0.2.0.0', '1.0.0-BETA1', '1.0.0'], Version::CANDIDATE_EXCLUDE_BUILD];
        yield 'alpha 0 with build (exclude patch and build)' => ['0.1.0.5', ['0.2.0.0', '1.0.0-BETA1', '1.0.0'], Version::CANDIDATE_EXCLUDE_BUILD | Version::CANDIDATE_EXCLUDE_PATCH];
        yield 'alpha 0 with build (exclude patch, build and pre-releases)' => ['0.1.0.5', ['0.2.0.0', '1.0.0'], Version::CANDIDATE_EXCLUDE_BUILD | Version::CANDIDATE_EXCLUDE_PATCH | Version::CANDIDATE_EXCLUDE_PRE_RELEASES];

        yield 'alpha with excluding alpha' => ['1.0.0-alpha-1', ['1.0.0-BETA1', '1.0.0-RC1', '1.0.0'], Version::CANDIDATE_EXCLUDE_ALPHA];
        yield 'alpha with excluding beta' => ['1.0.0-alpha-1', ['1.0.0-alpha-2', '1.0.0-RC1', '1.0.0'], Version::CANDIDATE_EXCLUDE_BETA];
        yield 'alpha with excluding rc' => ['1.0.0-alpha-1', ['1.0.0-alpha-2', '1.0.0-BETA1', '1.0.0'], Version::CANDIDATE_EXCLUDE_RC];
        yield 'alpha with excluding pre-releases' => ['1.0.0-alpha-1', ['1.0.0'], Version::CANDIDATE_EXCLUDE_PRE_RELEASES];

        yield 'beta with excluding beta and rc' => ['1.0.0-beta-5', ['1.0.0'], Version::CANDIDATE_EXCLUDE_BETA | Version::CANDIDATE_EXCLUDE_RC];
        yield 'stable excluding patch' => ['1.0.0', ['1.1.0-BETA1', '1.1.0', '2.0.0-ALPHA1', '2.0.0-BETA1', '2.0.0'], Version::CANDIDATE_EXCLUDE_PATCH];
        yield 'stable excluding build' => ['1.0.0.5', ['1.0.1.0', '1.1.0.0-BETA1', '1.1.0.0', '2.0.0.0-ALPHA1', '2.0.0.0-BETA1', '2.0.0.0'], Version::CANDIDATE_EXCLUDE_BUILD];
    }

    /**
     * @param string[] $expected
     */
    #[Test]
    #[DataProvider('provideExpectedNextVersionCandidates')]
    public function it_provides_next_version_candidates(string $current, array $expected, int $candidateOptions = 0): void
    {
        $candidates = Version::fromString($current)->getNextVersionCandidates($candidateOptions);
        $expected = array_map(static fn (string $version): Version => Version::fromString($version), $expected);

        $formatter = static fn (Version $version): string => $version->format();

        self::assertEquals(
            $expected,
            $candidates,
            \sprintf(
                "Expected ['%s'] but got ['%s']",
                implode("', '", array_map($formatter, $expected)),
                implode("', '", array_map($formatter, $candidates)),
            ),
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string[], 2: string, 3?: int}>
     */
    public static function provideExpectedNextVersionCandidatesWithCustomFormat(): iterable
    {
        // Anything not part of the input is ommited from the candidates
        // Unless filled-in

        yield 'alpha 0 (with {build} but ommited from input)' => ['0.1.0', ['0.1.1', '0.2.0', '1.0.0-BETA1', '1.0.0'], '{major}.{minor}.{patch}.{build}-{pre-release}x'];
        yield 'alpha 0 (with patch} but ommited from pattern' => ['0.1.0', ['0.1.1', '0.2.0', '1.0.0-BETA1', '1.0.0'], '{major}.{minor}-{pre-release}x'];
        yield 'alpha 0 with minimum pattern' => ['0.1.0', ['0.1.1', '0.2.0', '1.0.0-BETA1', '1.0.0'], '{major}.{minor}'];

        yield 'alpha 0 with patch fill-in' => ['0.1', ['0.1.1', '0.2.0', '1.0.0-BETA1', '1.0.0'], '{major}.{minor}.{patch}.{build}-{pre-release}x', Version::INPUT_FILL_PATCH];
        yield 'alpha 0 with patch ignored' => ['0.1.5', ['0.2', '1.0-BETA1', '1.0'], '{major}.{minor}.{patch}.{build}-{pre-release}x', Version::INPUT_IGNORE_PATCH];

        // Alternative format
        yield 'beta (with casing)' => ['1.0.0-beta-5', ['1.0.0-Beta6', '1.0.0-Rc1', '1.0.0'], '{major}.{minor}.{patch}-{pre-release:title}x'];
        yield 'beta (with separator -)' => ['1.0.0-beta5', ['1.0.0-BETA-6', '1.0.0-RC-1', '1.0.0'], '{major}.{minor}.{patch}-{pre-release}-x'];
        yield 'beta (with separator .)' => ['1.0.0-beta5', ['1.0.0-BETA.6', '1.0.0-RC.1', '1.0.0'], '{major}.{minor}.{patch}-{pre-release}.x'];
        yield 'beta (without patch)' => ['1.0-beta-5', ['1.0-Beta-6', '1.0-Rc-1', '1.0'], '{major}.{minor}-{pre-release:title}-x'];
    }

    /**
     * @param string[] $expected
     */
    #[Test]
    #[DataProvider('provideExpectedNextVersionCandidatesWithCustomFormat')]
    public function it_provides_next_version_candidates_with_custom_format(string $current, array $expected, ?string $outputFormat, int $inputOptions = 0): void
    {
        // The returned candidates output-format must jave the identifiers as needed.
        // When $outputFormat ommits {patch} but the input has a patch identifier,
        // the candidate must have a {patch} identifier accordingly.
        $candidates = Version::fromString($current, $outputFormat, $inputOptions)->getNextVersionCandidates();
        $candidates = array_map(static fn (Version $version): string => $version->format(), $candidates);

        self::assertEquals($expected, $candidates);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function provideExpectedIncreasedVersion(): iterable
    {
        yield 'patch with patch 0' => ['0.1.0', '0.1.1', 'patch'];
        yield 'patch with patch 1' => ['0.1.1', '0.1.2', 'patch'];
        yield 'patch with patch stable' => ['1.0.0', '1.0.1', 'patch'];

        // Patch with unstable. Increase pre-release instead
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
            Version::fromString($current)->getNextIncreaseOf(VersionIdentifier::from($stability)),
        );
    }
}
