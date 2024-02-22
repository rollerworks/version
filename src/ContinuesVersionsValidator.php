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

use function count;

final class ContinuesVersionsValidator
{
    /** @var Version[] */
    private $versions = [];

    /** @var Version[] */
    private $possibleVersions = [];

    /** @var array<int, Version[]> */
    private $resolveVersions = [];

    public function __construct(Version ...$versions)
    {
        $this->versions = $versions;
    }

    public function isContinues(Version $new): bool
    {
        if (count($this->versions) === 0) {
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

        if (!isset($this->resolveVersions[$major])) {
            $this->computePossibleVersionsFromLastExisting();

            return;
        }

        if (!isset($this->resolveVersions[$major][$minor])) {
            $minor = $this->getLastArrayIndex($this->resolveVersions[$major]);
        }

        $this->computePossibleVersionsFromMinor($major, $minor);
    }

    private function arrangeExistingVersions(): void
    {
        usort($this->versions, function (Version $a, Version $b) {
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
        $major = $this->getLastArrayIndex($this->resolveVersions);
        $minor = $this->getLastArrayIndex($this->resolveVersions[$major]);

        /** @var Version $version */
        $version = $this->resolveVersions[$major][$minor];
        $this->possibleVersions = $version->getNextVersionCandidates();
    }

    /**
     * @param array<int, mixed> $array
     */
    private function getLastArrayIndex(array $array): int
    {
        end($array);

        return key($array);
    }

    private function hasNewerMajorVersionsAfter(int $major): bool
    {
        return $this->getLastArrayIndex($this->resolveVersions) > $major;
    }

    private function hasNewerMinorVersionsAfter(int $major, int $minor): bool
    {
        return $this->getLastArrayIndex($this->resolveVersions[$major]) > $minor;
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
