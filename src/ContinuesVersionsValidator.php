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
    /** @var array<int, array<int, Version>> [major][minor] = {Version} */
    private readonly array $versions;

    /** @var array<int, Version> */
    private array $possibleVersions;

    public function __construct(Version ...$versions)
    {
        usort($versions, static fn (Version $a, Version $b) => version_compare(strtolower($a->full), strtolower($b->full), '<') ? -1 : 1);
        $resolvedVersions = [];

        foreach ($versions as $version) {
            $resolvedVersions[$version->major][$version->minor] = $version;
        }

        $this->versions = $resolvedVersions;
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
        if (! isset($this->possibleVersions)) {
            throw new \LogicException('Cannot get possible versions before computing them with isContinues().');
        }

        return $this->possibleVersions;
    }

    private function computePossibleVersions(Version $new): void
    {
        $major = $new->major;
        $minor = $new->minor;

        if (! isset($this->versions[$major])) {
            $this->computePossibleVersionsFromLastExisting();

            return;
        }

        if (! isset($this->versions[$major][$minor])) {
            $minor = array_key_last($this->versions[$major]);
        }

        $this->computePossibleVersionsFromMinor($major, $minor);
    }

    private function computePossibleVersionsFromLastExisting(): void
    {
        /** @var int $major It's impossible this non-existing */
        $major = array_key_last($this->versions);
        /** @var int $minor It's impossible this non-existing */
        $minor = array_key_last($this->versions[$major]);

        $this->possibleVersions = $this->versions[$major][$minor]->getNextVersionCandidates();
    }

    private function hasNewerMajorVersionsAfter(int $major): bool
    {
        return array_key_last($this->versions) > $major;
    }

    private function hasNewerMinorVersionsAfter(int $major, int $minor): bool
    {
        return array_key_last($this->versions[$major]) > $minor;
    }

    private function computePossibleVersionsFromMinor(int $major, int $minor): void
    {
        $version = $this->versions[$major][$minor];

        if ($this->hasNewerMinorVersionsAfter($major, $minor)) {
            $this->possibleVersions = [$version->getNextIncreaseOf(VersionType::Patch)];

            return;
        }

        if ($this->hasNewerMajorVersionsAfter($major)) {
            $this->possibleVersions = [$version->getNextIncreaseOf(VersionType::Patch), $version->getNextIncreaseOf(VersionType::Minor)];

            return;
        }

        $this->possibleVersions = $version->getNextVersionCandidates();
    }
}
