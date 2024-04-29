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
    public const STABILITY_ALPHA = 0;
    public const STABILITY_BETA = 1;
    public const STABILITY_RC = 2;
    public const STABILITY_STABLE = 3;

    /**
     * Match most common version formats.
     *
     * * No prefix or build-meta (matched)
     * * For historic reasons stability versions may have a hyphen or dot
     *   and is considered optional
     */
    public const VERSION_REGEX = '(?P<major>\d++)\.(?P<minor>\d++)(?:\.(?P<patch>\d++))?(?:[-.]?(?P<stability>beta|RC|alpha|stable)(?:[.-]?(?P<metaver>\d+))?)?';

    /** @var int */
    public $major;

    /** @var int */
    public $minor;

    /** @var int */
    public $patch;

    /** @var int */
    public $stability;

    /** @var int */
    public $metaver;

    /** @var string */
    public $full;

    /**
     * Higher means more stable.
     *
     * @var array<string,int>
     */
    private static $stabilityIndexes = [
        'alpha' => self::STABILITY_ALPHA,
        'beta' => self::STABILITY_BETA,
        'rc' => self::STABILITY_RC,
        'stable' => self::STABILITY_STABLE,
    ];

    private function __construct(int $major, int $minor, int $patch, int $stability, int $metaver = 0)
    {
        if (0 === $major) {
            $stability = self::STABILITY_ALPHA;
        }

        $this->major = $major;
        $this->minor = $minor;
        $this->patch = $patch;
        $this->stability = $stability;
        $this->metaver = $metaver;

        if (self::STABILITY_STABLE === $stability && $this->metaver > 0) {
            throw new \InvalidArgumentException('Meta version of the stability flag cannot be set for stable.');
        }

        if ($major > 0 && $stability < self::STABILITY_STABLE) {
            $this->full = sprintf(
                '%d.%d.%d-%s%d',
                $this->major,
                $this->minor,
                $this->patch,
                strtoupper(array_search($this->stability, self::$stabilityIndexes, true)), /** @phpstan-ignore-line */
                $this->metaver
            );
        } else {
            $this->full = sprintf('%d.%d.%d', $this->major, $this->minor, $this->patch);
        }
    }

    public function __toString()
    {
        return $this->full;
    }

    public static function fromString(string $version): self
    {
        if (preg_match('/^v?'.self::VERSION_REGEX.'$/i', $version, $matches)) {
            return new self(
                (int) $matches['major'],
                (int) $matches['minor'],
                (int) ($matches['patch'] ?? 0),
                self::$stabilityIndexes[strtolower($matches['stability'] ?? 'stable')],
                (int) ($matches['metaver'] ?? 0)
            );
        }

        throw new \InvalidArgumentException(
            sprintf(
                'Unable to parse version "%s" Expects an SemVer compatible version without build-metadata. '.
                'Either "1.0.0", "1.0", "1.0" or "1.0.0-beta1", "1.0.0-beta-1"',
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
     * @return Version[]
     */
    public function getNextVersionCandidates(): array
    {
        $candidates = [];

        // Pre first-stable, so 0.x-[rc,beta,stable] releases are not considered.
        // Use alpha as stability with metaver 1, 0.2-alpha2 is simple ignored.
        // If anyone really uses this... not our problem :)
        if (0 === $this->major) {
            $candidates[] = $this->getNextIncreaseOf('patch');
            $candidates[] = $this->getNextIncreaseOf('minor');
            $candidates[] = self::fromString('1.0.0-BETA1');

            // stable (RC usually follows *after* beta, but jumps to stable are accepted)
            // RC is technically valid, but not very common and therefor ignored.
            $candidates[] = self::fromString('1.0.0');

            return $candidates;
        }

        // Latest is unstable, may increase stability or metaver (nothing else)
        // 1.0.1-beta1 is not accepted, an (un)stability only applies for x.0.0
        if ($this->stability < self::STABILITY_STABLE) {
            $candidates[] = new self($this->major, $this->minor, 0, $this->stability, $this->metaver + 1);

            for ($s = $this->stability + 1; $s < 3; ++$s) {
                $candidates[] = new self($this->major, $this->minor, 0, $s, 1);
            }

            $candidates[] = new self($this->major, $this->minor, 0, self::STABILITY_STABLE);

            return $candidates;
        }

        // Stable, so a patch, major or new minor (with lower stability) version is possible, RC is excluded.
        $candidates[] = $this->getNextIncreaseOf('patch');
        $candidates[] = $this->getNextIncreaseOf('beta');
        $candidates[] = $this->getNextIncreaseOf('minor');

        // New (un)stable major (excluding RC)
        $candidates[] = new self($this->major + 1, 0, 0, self::STABILITY_ALPHA, 1);
        $candidates[] = new self($this->major + 1, 0, 0, self::STABILITY_BETA, 1);
        $candidates[] = new self($this->major + 1, 0, 0, self::STABILITY_STABLE);

        return $candidates;
    }

    public function equalTo(self $second): bool
    {
        return $this->full === $second->full;
    }

    /**
     * Returns the increased Version based on the stability.
     *
     * Note:
     *
     * * Using 'major' on a beta release produces a stable release for *that* major version.
     * * Using 'stable' on an existing stable will increase the minor version.
     * * Using 'patch' on an unstable release increases the metaver instead.
     *
     * @param string $stability either alpha, beta, rc, stable, major, minor or patch
     *
     * @return self A new version instance with the changes applied
     */
    public function getNextIncreaseOf(string $stability): self
    {
        switch ($stability) {
            case 'patch':
                if ($this->major > 0 && $this->metaver > 0) {
                    return $this->getIncreaseOfNextPossibleMinorOrMeta();
                }

                return new self($this->major, $this->minor, $this->patch + 1, self::STABILITY_STABLE);

            case 'minor':
                return new self($this->major, $this->minor + 1, 0, self::STABILITY_STABLE);

            case 'major':
                return $this->getIncreaseByMajor();

            case 'alpha':
            case 'beta':
            case 'rc':
                return $this->getIncreaseOfMetaver($stability);

            case 'stable':
                return $this->getIncreaseOfStable();

            case 'next':
                return $this->getIncreaseOfNextPossibleMinorOrMeta();

            default:
                throw new \InvalidArgumentException(
                    sprintf(
                        'Unknown stability "%s", accepts "%s".',
                        $stability,
                        implode('", "', ['alpha', 'beta', 'rc', 'stable', 'major', 'next', 'minor', 'patch'])
                    )
                );
        }
    }

    private function getIncreaseOfNextPossibleMinorOrMeta(): self
    {
        if ($this->major > 0 && $this->stability < self::STABILITY_STABLE) {
            return new self($this->major, $this->minor, $this->patch, $this->stability, $this->metaver + 1);
        }

        return new self($this->major, $this->minor + 1, 0, self::STABILITY_STABLE);
    }

    private function getIncreaseByMajor(): self
    {
        if ($this->stability < self::STABILITY_STABLE) {
            return new self(max($this->major, 1), 0, 0, self::STABILITY_STABLE);
        }

        return new self($this->major + 1, 0, 0, self::STABILITY_STABLE);
    }

    private function getIncreaseOfMetaver(string $stability): self
    {
        if ($this->stability === self::$stabilityIndexes[$stability]) {
            return new self($this->major, $this->minor, 0, $this->stability, $this->metaver + 1);
        }

        if (self::$stabilityIndexes[$stability] > $this->stability) {
            return new self($this->major, $this->minor, 0, self::$stabilityIndexes[$stability], 1);
        }

        // Lower stability than current.
        return new self($this->major, $this->minor + 1, 0, self::$stabilityIndexes[$stability], 1);
    }

    private function getIncreaseOfStable(): self
    {
        if ($this->stability === self::STABILITY_STABLE) {
            return new self($this->major, $this->minor + 1, 0, self::STABILITY_STABLE);
        }

        if ($this->major === 0) {
            return new self(1, 0, 0, self::STABILITY_STABLE);
        }

        return new self($this->major, $this->minor, 0, self::STABILITY_STABLE);
    }
}
