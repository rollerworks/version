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

    /** @var array<string, true> */
    private readonly array $allVersions;

    /** @var array<int, Version> */
    private array $possibleVersions;

    public function __construct(Version ...$versions)
    {
        usort($versions, static fn (Version $a, Version $b) => $a->lowerThan($b) ? -1 : 1);

        $resolvedVersions = [];
        $allVersions = [];

        foreach ($versions as $version) {
            $resolvedVersions[$version->major][$version->minor] = $version;
            $allVersions[$version->full] = true;
        }

        $this->versions = $resolvedVersions;
        $this->allVersions = $allVersions;
    }

    public function isContinues(Version $new, int $candidateOptions = 0): bool
    {
        if (\count($this->versions) === 0) {
            $this->initializePossibleVersionsForEmptyList($new, $candidateOptions);
        } else {
            $this->computePossibleVersions($new, $candidateOptions);
        }

        ['withBuild' => $withBuild] = $this->getCandidateOptions($new, $candidateOptions);

        $this->possibleVersions = array_merge([], $this->possibleVersions);

        foreach ($this->possibleVersions as $possibleVersion) {
            if ($possibleVersion->equalTo($new, ! $withBuild)) {
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

    private function initializePossibleVersionsForEmptyList(Version $new, int $candidateOptions = 0): void
    {
        $options = 0;
        $options |= $new->patch !== null ? Version::INPUT_FILL_PATCH : 0;
        $options |= $new->build !== null ? Version::INPUT_FILL_BUILD : 0;

        ['withPrereleases' => $withPrereleases] = $this->getCandidateOptions($new, $candidateOptions);

        if ($withPrereleases === []) {
            $this->possibleVersions = [Version::fromString('1.0', $new->outputFormatOfIncrement, $options)];

            return;
        }

        $this->possibleVersions = [
            Version::fromString('0.1', $new->outputFormatOfIncrement, options: $options),
        ];

        $s = Stability::Alpha;

        while ($s->isLowerThan(Stability::Rc)) {
            if (\in_array($s, $withPrereleases, true)) {
                $this->possibleVersions[] = Version::fromString(\sprintf('1.0-%s1', $s->label('upper')), $new->outputFormatOfIncrement, $options);
            }

            $s = $s->increment();
        }

        // No no point of adding the build as candidate as 1.0.0.0 is automatically provided
        $this->possibleVersions[] = Version::fromString('1.0', $new->outputFormatOfIncrement, $options);
    }

    /**
     * @return array{withPatch: bool, withBuild: bool, withPrereleases: Stability[]}
     */
    #[\NoDiscard]
    private function getCandidateOptions(Version $new, int $options): array
    {
        $withPatch = $new->patch !== null && ! self::bitmaskHas($options, Version::CANDIDATE_EXCLUDE_PATCH);
        $withBuild = $new->build !== null && ! self::bitmaskHas($options, Version::CANDIDATE_EXCLUDE_BUILD);

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

    #[\NoDiscard]
    private static function bitmaskHas(int $mask, int $flag): bool
    {
        return ($flag & $mask) === $flag;
    }

    private function computePossibleVersions(Version $new, int $candidateOptions): void
    {
        $major = $new->major;
        $minor = $new->minor;

        if (! isset($this->versions[$major])) {
            $this->computePossibleVersionsFromLastExisting($candidateOptions);

            return;
        }

        if (! isset($this->versions[$major][$minor])) {
            $minor = array_key_last($this->versions[$major]);
        }

        $this->computePossibleVersionsFromMinor($major, $minor, $candidateOptions);

        // In the rare case where a candidate versions already exists, we remove it.
        foreach ($this->possibleVersions as $k => $possibleVersion) {
            if (isset($this->allVersions[$possibleVersion->full])) {
                unset($this->possibleVersions[$k]);
            }
        }
    }

    private function computePossibleVersionsFromLastExisting(int $candidateOptions): void
    {
        /** @var int $major It's impossible this is non-existing */
        $major = array_key_last($this->versions);
        /** @var int $minor It's impossible this is non-existing */
        $minor = array_key_last($this->versions[$major]);

        $this->possibleVersions = $this->versions[$major][$minor]->getNextVersionCandidates($candidateOptions);
    }

    #[\NoDiscard]
    private function hasNewerMajorVersionsAfter(int $major): bool
    {
        return array_key_last($this->versions) > $major;
    }

    #[\NoDiscard]
    private function hasNewerMinorVersionsAfter(int $major, int $minor): bool
    {
        return array_key_last($this->versions[$major]) > $minor;
    }

    private function computePossibleVersionsFromMinor(int $major, int $minor, int $candidateOptions): void
    {
        $version = $this->versions[$major][$minor];

        ['withPatch' => $withPatch, 'withBuild' => $withBuild] = $this->getCandidateOptions($version, $candidateOptions);

        if ($this->hasNewerMinorVersionsAfter($major, $minor)) {
            $this->possibleVersions = [];

            if ($withBuild) {
                $this->possibleVersions[] = $version->getNextIncreaseOf(VersionIdentifier::Build);
            }

            if ($withPatch) {
                $this->possibleVersions = [$version->getNextIncreaseOf(VersionIdentifier::Patch)];
            }

            return;
        }

        if ($this->hasNewerMajorVersionsAfter($major)) {
            $this->possibleVersions = [];

            if ($withBuild) {
                $this->possibleVersions[] = $version->getNextIncreaseOf(VersionIdentifier::Build);
            }

            if ($withPatch) {
                $this->possibleVersions[] = $version->getNextIncreaseOf(VersionIdentifier::Patch);
            }

            $this->possibleVersions[] = $version->getNextIncreaseOf(VersionIdentifier::Minor);

            return;
        }

        $this->possibleVersions = $version->getNextVersionCandidates($candidateOptions);
    }
}
