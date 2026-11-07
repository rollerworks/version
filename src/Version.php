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

namespace Rollerworks\Component\Version;

final class Version
{
    /**
     * Match most common version formats.
     *
     * * No v-prefix/dev-suffix (matched).
     * * For historic reasons stability versions may have a hyphen or dot
     *   and is considered optional.
     * * Patch identifier is optional.
     * * Build identifier is optional.
     */
    public const string VERSION_REGEX = '(?P<major>\d++)\.(?P<minor>\d++)(?:\.(?P<patch>\d++))?(?:\.(?P<build>\d++))?([-.]?(?P<stability>beta|RC|alpha|stable)(?:[.-]?(?P<metaver>\d+))?)?';

    // fromString() options (bitmask)
    public const int INPUT_IGNORE_BUILD = 1;
    public const int INPUT_IGNORE_PATCH = (self::INPUT_IGNORE_BUILD | 2);
    public const int INPUT_IGNORE_PRE_RELEASE = 16;
    public const int INPUT_FILL_PATCH = 4;
    public const int INPUT_FILL_BUILD = (self::INPUT_FILL_PATCH | 8);

    // getNextVersionCandidates() options (bitmask)
    public const int CANDIDATE_EXCLUDE_PATCH = 1;
    public const int CANDIDATE_EXCLUDE_BUILD = 2;
    public const int CANDIDATE_EXCLUDE_ALPHA = 4;
    public const int CANDIDATE_EXCLUDE_BETA = 8;
    public const int CANDIDATE_EXCLUDE_RC = 16;
    public const int CANDIDATE_EXCLUDE_PRE_RELEASES = (self::CANDIDATE_EXCLUDE_ALPHA | self::CANDIDATE_EXCLUDE_BETA | self::CANDIDATE_EXCLUDE_RC);

    public static VersionFormatter $formatter;
    public readonly Stability $stability;
    public readonly int $metaver;

    /** The version string in {@see $this::$outputFormat} format. */
    public readonly string $formatted;

    /** The version string in {@see VersionFormatter::FORMAT_FULL} format. */
    public readonly string $full;

    /** The version string in {@see VersionFormatter::FORMAT_WITHOUT_BUILD} format. */
    public readonly string $fullWithoutBuild;

    public readonly string $outputFormat;
    public readonly string $outputFormatOfIncrement;
    private readonly ?int $patchEmpty;
    private readonly ?int $buildEmpty;

    private function __construct(
        public int $major,
        public int $minor,
        public ?int $patch,
        Stability $stability = Stability::Stable,
        int $metaver = 0,
        public ?int $build = null,
        ?string $outputFormat = null,
    ) {
        self::$formatter ??= new VersionFormatter();

        $this->stability = $major === 0 ? Stability::Alpha : $stability;
        $this->metaver = $this->stability === Stability::Stable ? 0 : $metaver;

        $this->full = self::$formatter->format($this, VersionFormatter::FORMAT_FULL);
        $this->fullWithoutBuild = self::$formatter->format($this, VersionFormatter::FORMAT_WITHOUT_BUILD);

        $this->patchEmpty = $patch === null ? null : 0;
        $this->buildEmpty = $build === null ? null : 0;
        $this->outputFormat = $outputFormat ?? VersionFormatter::FORMAT_DEFAULT;
        $this->formatted = self::$formatter->format($this, $this->outputFormat);

        // When an identifier is empty (patch or build) it will not be considered
        // for as a candidate and so will ommited from the output when empty.
        if ($patch !== null && $this->outputFormat === VersionFormatter::FORMAT_DEFAULT) {
            // Default format already contains patch identifier, and build is only used when none-null.
            $this->outputFormatOfIncrement = $this->outputFormat;
        } else {
            // Output format might not contain all required identifiers.
            $this->outputFormatOfIncrement = self::$formatter->modifyFormat(
                $this->outputFormat,
                patch: IdentifierMarker::WhenProvided,
                build: IdentifierMarker::WhenProvided,
                metaVer: true,
            );
        }

        echo '';
    }

    public function __toString()
    {
        return $this->formatted;
    }

    /**
     * @return array{
     *     formatted: string,
     *     full: string,
     *     version: array{
     *         major: int,
     *         minor: int,
     *         patch: int|null,
     *         build: int|null,
     *         stability: Stability,
     *         pre_release: int
     *     },
     *     output_format: string,
     *     format_of_increment: string
     * }
     */
    public function __debugInfo(): array
    {
        return [
            'formatted' => $this->formatted,
            'full' => $this->full,
            'version' => [
                'major' => $this->major,
                'minor' => $this->minor,
                'patch' => $this->patch,
                'build' => $this->build,
                'stability' => $this->stability,
                'pre_release' => $this->metaver,
            ],
            'output_format' => $this->outputFormat,
            'format_of_increment' => $this->outputFormatOfIncrement,
        ];
    }

    /**
     * Create a Version object from a string.
     *
     * For compatibility with Composer Semver, the build-id and -dev suffix
     * are allowed, but the `-dev` ignored as part of the provided version.
     *
     * - A format might begin with `v` prefix (either `v1.0.0`).
     * - A format might end with `-dev` suffix.
     * - A format might ommit the patch identifier (either `1.0`).
     *
     * The `$full` property contains the input as-is without any normalization
     * (except ommited `v` prefix).
     *
     * Note: When the patch identifier is omitted the patch property is set to null.
     * Use the `$full` property to get a full version string.
     *
     * @param string|null $outputFormat See {@see format()} for details defaults to {@see VersionFormatter::FORMAT_DEFAULT}
     * @param int         $options      Bitmask options {@see Version::INPUT_*}
     */
    public static function fromString(string $version, ?string $outputFormat = null, int $options = 0): self
    {
        // This is a special case that is never accepted anywhere.
        if (str_ends_with(strtolower($version), 'stable-dev')) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'Unable to parse version "%s" Expects an SemVer compatible version. ' .
                    'Either "1.0.0", "1.0", "1.0" or "1.0.0-beta1", "1.0.0-beta-1", "1.0.0.5", "1.0.0.0-dev"',
                    $version
                )
            );
        }

        if (! preg_match('/^v?' . self::VERSION_REGEX . '(?:-dev)?$/i', $version, $matches)) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'Unable to parse version "%s" Expects an SemVer compatible version. ' .
                    'Either "1.0.0", "1.0", "1.0" or "1.0.0-beta1", "1.0.0-beta-1", "1.0.0.5", "1.0.0.0-dev"',
                    $version
                )
            );
        }

        $matches['patch'] = ($matches['patch'] ?? '') === '' ? null : (int) $matches['patch'];
        $matches['build'] = ($matches['build'] ?? '') === '' ? null : (int) $matches['build'];
        $matches['stability'] = strtolower($matches['stability'] ?? 'stable');
        $matches['metaver'] = (int) ($matches['metaver'] ?? 0);

        self::processInputOptions($options, $matches);

        if ($matches['stability'] === 'stable' && $matches['metaver'] > 0) {
            throw new \InvalidArgumentException(\sprintf('Unable to parse version "%s" Expects an SemVer compatible version. Metaver "stable" cannot have a number.', $version));
        }

        return new self(
            major: (int) $matches['major'],
            minor: (int) $matches['minor'],
            patch: $matches['patch'],
            stability: Stability::fromString($matches['stability']),
            metaver: $matches['metaver'],
            build: $matches['build'],
            outputFormat: $outputFormat
        );
    }

    /**
     * Returns a formatted version string.
     *
     * The version format follows some specific rules.
     * See https://semver.org/ for more information.
     *
     * - Must not contain any whitespace characters;
     * - May optionally start with a leading `v` character (lower or uppercase);
     * - Must precede with `{major}.{minor}`;
     * - May procede with a `{patch}` identifier (use `{?patch}` to only use when a patch was provided);
     * - May procede with a `{build}` identifier (use `{?build}` to only use when a build was provided);
     * - May procede with `-{metaver}x` (`dash` and `x` are mandatory) x maybe seperated by a dot (`.`) character
     *   or dash (`-`) character or no character (as shown);
     *
     *   To change the casing of the meta-version, use `{metaver:<case>}`,
     *   `<case>` can be `lower`, `upper` or `title`, like `{metaver:upper}`.
     *
     * No other suffix is allowed after the meta-version identifier.
     * Note: Identifiers (except for meta-version) must be separated by a dot (`.`) character
     * and cannot be reorded. Cannot be used `{build}` before (or instead of) a `{patch}`.
     *
     * A Meta-version is always only displayed when provided.
     *
     * Full examples:
     *
     * - `v{major}.{minor}.{patch}{?build}-{metaver:upper}x` (build is only displayed when provided)
     * - `{major}.{minor}.{patch}.{build}-{metaver:lower}x`
     * - `{major}.{minor}.{?patch}` (patch is only displayed when provided)
     * - `{major}.{minor}` (minimal accepted version)
     *
     * Tip: If you need only one identifier use the object property instead.
     *
     * @param string|null $format The format to use. If null, the output format provided with
     *                            {@see fromString()} is used
     */
    public function format(?string $format = null): string
    {
        return $format === null ? $this->formatted : self::$formatter->format($this, $format);
    }

    /**
     * Returns a list of possible feature versions.
     *
     * Note:
     *
     * - Use the bitmask {@see CANDIDATE_EXCLUDE_*} to exclude certain candidates.
     * - Only identifiers that were provided with {@see fromString()} will be considered
     *   for incrementing (even when `$options` isn't provided).
     * - When the patch was omitted (null) it will not be considered.
     * - When build was omitted (null) it will not be considered.
     *
     * The output-format for candidates is automatically modified as needed.
     * When the output-format doesn't contain a `{patch}`/`{build}` identifier
     * but the candidate does, it will be added accordingly.
     *
     * * 0.1.0 -> [0.1.1, 0.2.0, 1.0.0-beta1, 1.0.0]
     * * 1.0.0 -> [1.0.1, 1.1.0, 2.0.0-beta1, 2.0.0]
     * * 1.0.1 -> [1.0.2, 1.2.0, 2.0.0-beta1, 2.0.0]
     * * 1.1.0 -> [1.2.0, 1.2.0-beta1, 2.0.0-beta1, 2.0.0]
     * * 1.0.0-beta1 -> [1.0.0-beta2, 1.0.0] (no minor or major increases)
     * * 1.0.0-alpha1 -> [1.0.0-alpha2, 1.0.0-beta1, 1.0.0] (no minor or major increases)
     *
     * * No patch: 0.1 -> [0.2.0, 1.0.0-beta1, 1.0.0]
     * * With build: 0.1.0.0 -> [0.1.0.1, 0.2.0.0, 1.0.0.0-beta1, 1.0.0.0]
     * * With build: 0.1.0.1 -> [0.1.0.2, 0.2.0.0, 1.0.0.0-beta1, 1.0.0.0]
     * * With build: 1.1.1.1 -> [1.1.1.2, 1.2.0.0, 1.0.0.0-beta1, 1.0.0.0]
     *
     * @return Version[]
     */
    public function getNextVersionCandidates(int $options = 0): array
    {
        $candidates = [];

        if ($this->major === 0) {
            return $this->computeVersionCandidatesForPreAlpha($options);
        }

        // Latest is unstable, may increase stability or metaver (nothing else)
        // 1.0.1-beta1 is not accepted, an (un)stability only applies for x.0.0
        if ($this->stability->isLowerThan(Stability::Stable)) {
            return $this->computeVersionCandidatesForUnstable($options);
        }

        ['withPatch' => $withPatch, 'withBuild' => $withBuild, 'withPrereleases' => $withPrereleases] = $this->getCandidateOptions($options);

        // Stable, so a patch, major or new minor (with lower stability) version is possible, RC is excluded.
        if ($withPatch) {
            $candidates[] = $this->getNextIncreaseOf(VersionIdentifier::Patch);
        }

        if ($withBuild) {
            $candidates[] = $this->getIncreaseOfBuild();
        }

        if (\in_array(Stability::Beta, $withPrereleases, true)) {
            $candidates[] = $this->getNextIncreaseOf(VersionIdentifier::Beta);
        }

        $candidates[] = $this->getNextIncreaseOf(VersionIdentifier::Minor);

        // New (un)stable major (excluding RC)
        $major = $this->modifyForIncrement(VersionIdentifier::Major, $this->major + 1);

        foreach ($withPrereleases as $prerelease) {
            if ($prerelease !== Stability::Rc) {
                $candidates[] = $major->modifyStabilityForIncrement($prerelease, 1);
            }
        }

        $candidates[] = $major->modifyStabilityForIncrement(Stability::Stable, 0);

        return $candidates;
    }

    public function equalTo(self $second, bool $ignoreBuild = true): bool
    {
        return $this->compareWith($second, '==', $ignoreBuild);
    }

    public function equals(self $second, bool $ignoreBuild = true): bool
    {
        return $this->compareWith($second, '==', $ignoreBuild);
    }

    public function higherThan(self $second, bool $ignoreBuild = true): bool
    {
        return $this->compareWith($second, '>', $ignoreBuild);
    }

    public function lowerThan(self $second, bool $ignoreBuild = true): bool
    {
        return $this->compareWith($second, '<', $ignoreBuild);
    }

    public function lowerThanOrEqual(self $second, bool $ignoreBuild = true): bool
    {
        return $this->compareWith($second, '<=', $ignoreBuild);
    }

    public function higherThanOrEqual(self $second, bool $ignoreBuild = true): bool
    {
        return $this->compareWith($second, '>=', $ignoreBuild);
    }

    /**
     * Returns the increased Version based on the type.
     *
     * Note:
     *
     * * Using 'major' on a beta release produces a stable release for *that* major version.
     * * Using 'stable' on an existing stable will increase the minor version.
     * * Using 'patch' on an unstable release increases the metaver instead.
     *
     * @return self A new version instance with the changes applied
     */
    public function getNextIncreaseOf(VersionIdentifier $type): self
    {
        return match ($type) {
            VersionIdentifier::Major => $this->getIncreaseByMajor(),
            VersionIdentifier::Patch => $this->getIncreaseOfPatch(),
            VersionIdentifier::Stable => $this->getIncreaseOfStable(),
            VersionIdentifier::Minor => $this->modifyForIncrement(VersionIdentifier::Minor, $this->minor + 1),
            VersionIdentifier::Next => $this->getIncreaseOfNextPossibleMinorOrMeta(),
            VersionIdentifier::Build => $this->getIncreaseOfBuild(),

            // Increase the metaver
            VersionIdentifier::Alpha,
            VersionIdentifier::Beta,
            VersionIdentifier::Rc => $this->getIncreaseOfMetaver(Stability::fromString($type->name)),
        };
    }

    /**
     * @param array<string|int, mixed> $matches
     */
    private static function processInputOptions(int $options, array &$matches): void
    {
        if ($options === 0) {
            return;
        }

        if (self::bitmaskHas($options, self::INPUT_IGNORE_PATCH) && self::bitmaskHas($options, self::INPUT_FILL_BUILD)) {
            throw new \InvalidArgumentException('Cannot combine options "INPUT_IGNORE_PATCH" and "INPUT_FILL_BUILD".');
        }

        if (self::bitmaskHas($options, self::INPUT_IGNORE_PATCH) && self::bitmaskHas($options, self::INPUT_FILL_PATCH)) {
            throw new \InvalidArgumentException('Cannot combine options "INPUT_IGNORE_PATCH" and "INPUT_FILL_PATCH".');
        }

        if (self::bitmaskHas($options, self::INPUT_IGNORE_BUILD) && self::bitmaskHas($options, self::INPUT_FILL_BUILD)) {
            throw new \InvalidArgumentException('Cannot combine options "INPUT_IGNORE_BUILD" and "INPUT_FILL_BUILD".');
        }

        if (self::bitmaskHas($options, self::INPUT_IGNORE_PATCH)) {
            $matches['patch'] = null;
        } elseif ($options & self::INPUT_FILL_PATCH) {
            $matches['patch'] ??= 0;
        }

        if (self::bitmaskHas($options, self::INPUT_IGNORE_BUILD)) {
            $matches['build'] = null;
        } elseif (self::bitmaskHas($options, self::INPUT_FILL_BUILD)) {
            $matches['build'] ??= 0;
        }

        if (self::bitmaskHas($options, self::INPUT_IGNORE_PRE_RELEASE)) {
            $matches['stability'] = 'stable';
            $matches['metaver'] = 0;
        }
    }

    /**
     * @return array{withPatch: bool, withBuild: bool, withPrereleases: Stability[]}
     */
    private function getCandidateOptions(int $options): array
    {
        $withPatch = $this->patch !== null && ! self::bitmaskHas($options, self::CANDIDATE_EXCLUDE_PATCH);
        $withBuild = $this->build !== null && ! self::bitmaskHas($options, self::CANDIDATE_EXCLUDE_BUILD);

        $withPrereleases = array_filter(
            Stability::cases(),
            static fn (Stability $s) => $s !== Stability::Stable
                && ! self::bitmaskHas($options, Version::{\sprintf('CANDIDATE_EXCLUDE_%s', $s->label('upper'))}),
        );

        return [
            'withPatch' => $withPatch,
            'withBuild' => $withBuild,
            'withPrereleases' => $withPrereleases,
        ];
    }

    private static function bitmaskHas(int $mask, int $flag): bool
    {
        return ($flag & $mask) === $flag;
    }

    private function compareWith(self $second, string $operator, bool $ignoreBuild): bool
    {
        $a = $ignoreBuild ? $this->fullWithoutBuild : $this->full;
        $b = $ignoreBuild ? $second->fullWithoutBuild : $second->full;

        return version_compare($a, $b, $operator);
    }

    /**
     * @return Version[]
     */
    #[\NoDiscard]
    private function computeVersionCandidatesForPreAlpha(int $options): array
    {
        ['withPatch' => $withPatch, 'withBuild' => $withBuild, 'withPrereleases' => $withPrereleases] = $this->getCandidateOptions($options);

        // Pre first-stable, so 0.x-[rc,beta,stable] releases are not considered.
        // Use alpha as stability with metaver 1, 0.2-alpha2 is simple ignored.
        // If anyone really uses this... not our problem :)

        $candidates = [];

        if ($withPatch) {
            $candidates[] = $this->getNextIncreaseOf(VersionIdentifier::Patch);
        }

        if ($withBuild) {
            $candidates[] = $this->getIncreaseOfBuild();
        }

        $major = $this->modifyForIncrement(VersionIdentifier::Major, 1);
        $candidates[] = $this->getNextIncreaseOf(VersionIdentifier::Minor);

        // stable (RC usually follows *after* beta, but jumps to stable are accepted)
        // RC is technically valid, but not very common and therefor ignored.
        if (\in_array(Stability::Beta, $withPrereleases, true)) {
            $candidates[] = $major->modifyStabilityForIncrement(Stability::Beta, 1);
        }

        $candidates[] = $major;

        return $candidates;
    }

    /**
     * Version is "unstable", may increase stability or metaver (nothing else).
     *
     * 1.0.1-beta1 is not accepted, an (un)stability only applies for x.0.0
     *
     * @return Version[]
     */
    #[\NoDiscard]
    private function computeVersionCandidatesForUnstable(int $options): array
    {
        ['withPrereleases' => $withPrereleases] = $this->getCandidateOptions($options);

        if (\in_array($this->stability, $withPrereleases, true)) {
            $candidates[] = $this->modifyStabilityForIncrement($this->stability, $this->metaver + 1);
        }

        $s = $this->stability->increment();

        while ($s->isLowerThan(Stability::Stable)) {
            if (\in_array($s, $withPrereleases, true)) {
                $candidates[] = $this->modifyStabilityForIncrement($s, 1);
            }

            $s = $s->increment();
        }

        $candidates[] = $this->modifyStabilityForIncrement(Stability::Stable, 0);

        return $candidates;
    }

    private function getIncreaseOfPatch(): self
    {
        if ($this->major > 0 && $this->metaver > 0) {
            return $this->getIncreaseOfNextPossibleMinorOrMeta();
        }

        return $this->modifyForIncrement(VersionIdentifier::Patch, ($this->patch ?? 0) + 1);
    }

    #[\NoDiscard]
    private function getIncreaseOfNextPossibleMinorOrMeta(): self
    {
        if ($this->major > 0 && $this->stability->isLowerThan(Stability::Stable)) {
            return $this->modifyStabilityForIncrement($this->stability, $this->metaver + 1);
        }

        return $this->modifyForIncrement(VersionIdentifier::Minor, $this->minor + 1);
    }

    #[\NoDiscard]
    private function getIncreaseByMajor(): self
    {
        if ($this->stability->isLowerThan(Stability::Stable)) {
            return $this->modifyForIncrement(VersionIdentifier::Major, max($this->major, 1));
        }

        return $this->modifyForIncrement(VersionIdentifier::Major, $this->major + 1);
    }

    #[\NoDiscard]
    private function getIncreaseOfMetaver(Stability $stability): self
    {
        if ($this->stability === $stability) {
            return $this->modifyStabilityForIncrement($this->stability, $this->metaver + 1);
        }

        if ($stability->isHigherThan($this->stability)) {
            return $this->modifyStabilityForIncrement($stability, 1);
        }

        // Lower stability than the current.
        return $this->modifyStabilityForIncrementWithNextMinor($stability, 1);
    }

    #[\NoDiscard]
    private function getIncreaseOfStable(): self
    {
        if ($this->stability === Stability::Stable) {
            return $this->modifyForIncrement(VersionIdentifier::Minor, $this->minor + 1);
        }

        if ($this->major === 0) {
            return $this->modifyForIncrement(VersionIdentifier::Major, 1);
        }

        return $this->modifyForIncrement(VersionIdentifier::Patch, 0);
    }

    #[\NoDiscard]
    private function getIncreaseOfBuild(): self
    {
        return $this->modifyForIncrement(VersionIdentifier::Build, ($this->build ?? 0) + 1);
    }

    #[\NoDiscard]
    private function modifyForIncrement(VersionIdentifier $type, ?int $newValue): self
    {
        if ($newValue === null && ! \in_array($type, [VersionIdentifier::Patch, VersionIdentifier::Build], true)) {
            throw new \LogicException(\sprintf('Cannot modify "%s" to NULL, only "patch" and "build" can be set to NULL. Please report this bug.', $type->value));
        }

        return match ($type) {
            VersionIdentifier::Major => new self($newValue, 0, $this->patchEmpty, build: $this->buildEmpty, outputFormat: $this->outputFormatOfIncrement),
            VersionIdentifier::Minor => new self($this->major, $newValue, $this->patchEmpty, build: $this->buildEmpty, outputFormat: $this->outputFormatOfIncrement),
            VersionIdentifier::Patch => new self($this->major, $this->minor, $newValue, build: $this->buildEmpty, outputFormat: $this->outputFormatOfIncrement),
            VersionIdentifier::Build => new self($this->major, $this->minor, $this->patch, $this->stability, $this->metaver, build: $newValue, outputFormat: $this->outputFormatOfIncrement),
            default => throw new \LogicException(\sprintf('Unsupported version identifier "%s" provided. Please report this bug.', $type->name)),
        };
    }

    #[\NoDiscard]
    private function modifyStabilityForIncrement(Stability $type, int $newValue): self
    {
        return new self($this->major, $this->minor, $this->patchEmpty, $type, $newValue, build: $this->buildEmpty, outputFormat: $this->outputFormatOfIncrement);
    }

    #[\NoDiscard]
    private function modifyStabilityForIncrementWithNextMinor(Stability $type, int $newValue): self
    {
        return new self($this->major, $this->minor + 1, $this->patchEmpty, $type, $newValue, build: $this->buildEmpty, outputFormat: $this->outputFormatOfIncrement);
    }
}
