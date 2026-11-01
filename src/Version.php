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

final readonly class Version
{
    /**
     * Match most common version formats.
     *
     * * No prefix or build-meta (matched).
     * * For historic reasons stability versions may have a hyphen or dot
     *   and is considered optional.
     */
    public const VERSION_REGEX = '(?P<major>\d++)\.(?P<minor>\d++)(?:\.(?P<patch>\d++))?(?:\.(?P<buildNr>\d++))?([-.]?(?P<stability>beta|RC|alpha|stable)(?:[.-]?(?P<metaver>\d+))?)?';

    /**
     * The full version string in normalized format.
     *
     * Example: 1.2.3-BETA4 or 1.2.0
     */
    public string $full;

    public Stability $stability;

    /**
     * @param int|null $buildNr a buildNr is only provided for informational purposes
     *                          it should not be used for comparisons
     */
    private function __construct(
        public int $major,
        public int $minor,
        public ?int $patch,
        Stability $stability,
        public int $metaver = 0,
        public ?int $buildNr = null,
    ) {
        if ($major === 0) {
            $stability = Stability::Alpha;
        }

        $this->stability = $stability;

        if ($stability === Stability::Stable && $this->metaver > 0) {
            throw new \InvalidArgumentException('Meta version of the stability flag cannot be set for stable.');
        }

        $version = \sprintf('%d.%d.%d', $this->major, $this->minor, $this->patch ?? 0);

        if ($this->buildNr !== null) {
            $version .= '.' . $this->buildNr;
        }

        // Add meta version
        if ($major > 0 && $stability->isLowerThan(Stability::Stable)) {
            $version .= \sprintf('-%s%d', strtoupper($stability->name), $this->metaver);
        }

        $this->full = $version;
    }

    public function __toString()
    {
        return $this->full;
    }

    /**
     * Create a Version object from a string.
     *
     * For compatibility with Composer Semver, the build-id and -dev suffix
     * are allowed, but the `-dev` ignored as part of the provided version.
     *
     * - A format might begin with `v` prefix (either `v1.0.0`).
     * - A format might end with `-dev` suffix.
     * - A format might ommit the patch compartment (either `1.0`).
     *
     * The `$full` property contains the input as-is without any normalization
     * (except ommited `v` prefix).
     *
     * Note: When the patch compartment is omitted the patch property is set to null.
     * Use the `$normalized` property to get a full version string.
     */
    public static function fromString(string $version): self
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

        if (preg_match('/^v?' . self::VERSION_REGEX . '(?:-dev)?$/i', $version, $matches)) {
            $matches['patch'] = ($matches['patch'] ?? '') === '' ? null : (int) $matches['patch'];
            $matches['buildNr'] = ($matches['buildNr'] ?? '') === '' ? null : (int) $matches['buildNr'];

            return new self(
                major: (int) $matches['major'],
                minor: (int) $matches['minor'],
                patch: $matches['patch'],
                stability: Stability::fromString($matches['stability'] ?? 'stable'),
                metaver: (int) ($matches['metaver'] ?? 0),
                buildNr: $matches['buildNr'],
            );
        }

        throw new \InvalidArgumentException(
            \sprintf(
                'Unable to parse version "%s" Expects an SemVer compatible version. ' .
                'Either "1.0.0", "1.0", "1.0" or "1.0.0-beta1", "1.0.0-beta-1", "1.0.0.5", "1.0.0.0-dev"',
                $version
            )
        );
    }

    /**
     * Returns a list of possible feature versions.
     *
     * * 0.1.0 -> [0.1.1, 0.2.0, 1.0.0-beta1, 1.0.0]
     * * 1.0.0 -> [1.0.1, 1.1.0, 2.0.0-beta1, 2.0.0]
     * * 1.0.1 -> [1.0.2, 1.2.0, 2.0.0-beta1, 2.0.0]
     * * 1.1.0 -> [1.2.0, 1.2.0-beta1, 2.0.0-beta1, 2.0.0]
     * * 1.0.0-beta1 -> [1.0.0-beta2, 1.0.0] (no minor or major increases)
     * * 1.0.0-alpha1 -> [1.0.0-alpha2, 1.0.0-beta1, 1.0.0] (no minor or major increases)
     *
     * Note: Build metadata is not considered a candidate.
     *
     * @return Version[]
     */
    public function getNextVersionCandidates(): array
    {
        $candidates = [];

        // Pre first-stable, so 0.x-[rc,beta,stable] releases are not considered.
        // Use alpha as stability with metaver 1, 0.2-alpha2 is simple ignored.
        // If anyone really uses this... not our problem :)
        if ($this->major === 0) {
            $candidates[] = $this->getNextIncreaseOf(VersionType::Patch);
            $candidates[] = $this->getNextIncreaseOf(VersionType::Minor);
            $candidates[] = self::fromString('1.0.0-BETA1');

            // stable (RC usually follows *after* beta, but jumps to stable are accepted)
            // RC is technically valid, but not very common and therefor ignored.
            $candidates[] = self::fromString('1.0.0');

            return $candidates;
        }

        // Latest is unstable, may increase stability or metaver (nothing else)
        // 1.0.1-beta1 is not accepted, an (un)stability only applies for x.0.0
        if ($this->stability->isLowerThan(Stability::Stable)) {
            $candidates[] = new self($this->major, $this->minor, 0, $this->stability, $this->metaver + 1);

            $s = $this->stability->increment();

            while ($s->isLowerThan(Stability::Stable)) {
                $candidates[] = new self($this->major, $this->minor, 0, $s, 1);
                $s = $s->increment();
            }

            $candidates[] = new self($this->major, $this->minor, 0, Stability::Stable);

            return $candidates;
        }

        // Stable, so a patch, major or new minor (with lower stability) version is possible, RC is excluded.
        $candidates[] = $this->getNextIncreaseOf(VersionType::Patch);
        $candidates[] = $this->getNextIncreaseOf(VersionType::Beta);
        $candidates[] = $this->getNextIncreaseOf(VersionType::Minor);

        // New (un)stable major (excluding RC)
        $candidates[] = new self($this->major + 1, 0, 0, Stability::Alpha, 1);
        $candidates[] = new self($this->major + 1, 0, 0, Stability::Beta, 1);
        $candidates[] = new self($this->major + 1, 0, 0, Stability::Stable);

        return $candidates;
    }

    public function equalTo(self $second): bool
    {
        return $this->full === $second->full;
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
    public function getNextIncreaseOf(VersionType $type): self
    {
        return match ($type) {
            VersionType::Major => $this->getIncreaseByMajor(),
            VersionType::Patch => $this->getIncreaseOfPatch(),
            VersionType::Stable => $this->getIncreaseOfStable(),
            VersionType::Minor => new self($this->major, $this->minor + 1, 0, Stability::Stable),
            VersionType::Next => $this->getIncreaseOfNextPossibleMinorOrMeta(),

            // Increase the metaver
            VersionType::Alpha,
            VersionType::Beta,
            VersionType::Rc => $this->getIncreaseOfMetaver(Stability::fromString($type->name)),
        };
    }

    private function getIncreaseOfPatch(): self
    {
        if ($this->major > 0 && $this->metaver > 0) {
            return $this->getIncreaseOfNextPossibleMinorOrMeta();
        }

        return new self($this->major, $this->minor, $this->patch + 1, Stability::Stable);
    }

    private function getIncreaseOfNextPossibleMinorOrMeta(): self
    {
        if ($this->major > 0 && $this->stability->isLowerThan(Stability::Stable)) {
            return new self($this->major, $this->minor, $this->patch, $this->stability, $this->metaver + 1);
        }

        return new self($this->major, $this->minor + 1, 0, Stability::Stable);
    }

    private function getIncreaseByMajor(): self
    {
        if ($this->stability->isLowerThan(Stability::Stable)) {
            return new self(max($this->major, 1), 0, 0, Stability::Stable);
        }

        return new self($this->major + 1, 0, 0, Stability::Stable);
    }

    private function getIncreaseOfMetaver(Stability $stability): self
    {
        if ($this->stability === $stability) {
            return new self($this->major, $this->minor, 0, $this->stability, $this->metaver + 1);
        }

        if ($stability->isHigherThan($this->stability)) {
            return new self($this->major, $this->minor, 0, $stability, 1);
        }

        // Lower stability than current.
        return new self($this->major, $this->minor + 1, 0, $stability, 1);
    }

    private function getIncreaseOfStable(): self
    {
        if ($this->stability === Stability::Stable) {
            return new self($this->major, $this->minor + 1, 0, Stability::Stable);
        }

        if ($this->major === 0) {
            return new self(1, 0, 0, Stability::Stable);
        }

        return new self($this->major, $this->minor, 0, Stability::Stable);
    }
}
