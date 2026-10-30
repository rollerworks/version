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

final class ContinuesVersionsValidator
{
    /** @var Version[] */
    private array $versions;

    /** @var Version[] */
    private array $possibleVersions = [];

    /**
     * @var array<int, array<int, Version>> [major][minor] = {Version}
     */
    private array $resolveVersions = [];

    public function __construct(Version ...$versions)
    {
        $this->versions = $versions;
    }

    public function isContinues(Version $new): bool
    {
        if (\count($this->versions) === 0) {
            $this->possibleVersions = [
                Version::fromString('0.1.0'),
                Version::fromString('1.0.0-ALPHA1'),
                Version::fromString('1.0.0-BETA1'),
                Version::fromString('1.0.0'),
            ];
        } else {
            $this->computePossibleVersions($new);
        }

        foreach ($this->possibleVersions as $possibleVersion) {
            if ($possibleVersion->equalTo($new)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Version[]
     */
    public function getPossibleVersions(): array
    {
        return $this->possibleVersions;
    }

    private function computePossibleVersions(Version $new): void
    {
        $this->arrangeExistingVersions();

        $major = $new->major;
        $minor = $new->minor;

        if (! isset($this->resolveVersions[$major])) {
            $this->computePossibleVersionsFromLastExisting();

            return;
        }

        if (! isset($this->resolveVersions[$major][$minor])) {
            $minor = array_key_last($this->resolveVersions[$major]);
            assert(is_int($minor));
        }

        $this->computePossibleVersionsFromMinor($major, $minor);
    }

    private function arrangeExistingVersions(): void
    {
        usort($this->versions, static function (Version $a, Version $b) {
            return version_compare(strtolower($a->full), strtolower($b->full), '<') ? -1 : 1;
        });

        $resolvedVersions = [];

        foreach ($this->versions as $version) {
            $resolvedVersions[$version->major][$version->minor] = $version;
        }

        $this->resolveVersions = $resolvedVersions;
    }

    private function computePossibleVersionsFromLastExisting(): void
    {
        $major = array_key_last($this->resolveVersions);
        $minor = array_key_last($this->resolveVersions[$major]);

        /** @var Version $version */
        $version = $this->resolveVersions[$major][$minor];
        $this->possibleVersions = $version->getNextVersionCandidates();
    }

    private function hasNewerMajorVersionsAfter(int $major): bool
    {
        return array_key_last($this->resolveVersions) > $major;
    }

    private function hasNewerMinorVersionsAfter(int $major, int $minor): bool
    {
        return array_key_last($this->resolveVersions[$major]) > $minor;
    }

    private function computePossibleVersionsFromMinor(int $major, int $minor): void
    {
        /** @var Version $version */
        $version = $this->resolveVersions[$major][$minor];

        if ($this->hasNewerMinorVersionsAfter($major, $minor)) {
            $this->possibleVersions = [$version->getNextIncreaseOf('patch')];

            return;
        }

        if ($this->hasNewerMajorVersionsAfter($major)) {
            $this->possibleVersions = [$version->getNextIncreaseOf('patch'), $version->getNextIncreaseOf('minor')];

            return;
        }

        $versionCandidates = $version->getNextVersionCandidates();
        $this->possibleVersions = $versionCandidates;
    }
}
