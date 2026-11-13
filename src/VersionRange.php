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

use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\VersionParser;

/**
 * A version range consists of a lower and upper bound (either inclusive or exclusive).
 */
final class VersionRange
{
    private static VersionParser $versionParser;
    private readonly ?ConstraintInterface $constraint;

    public function __construct(
        public readonly ?Version $lowerBound = null,
        public readonly ?Version $upperBound = null,
        public readonly bool $lowerInclusive = false,
        public readonly bool $upperInclusive = false,
    ) {
        if ($lowerBound === null && $upperBound === null) {
            $this->constraint = null;

            return;
        }

        self::$versionParser ??= new VersionParser();
        $this->constraint = self::$versionParser->parseConstraints($this->__toString());
    }

    public static function createFromString(string $constraints): self
    {
        if ($constraints === '') {
            return new self();
        }

        self::$versionParser ??= new VersionParser();

        return self::createFromConstraint(self::$versionParser->parseConstraints($constraints));
    }

    /**
     * @param (string|Version)[] $versions
     */
    public static function createFromList(array $versions, bool $lowerInclusive = true, bool $upperInclusive = true): self
    {
        $versions = Version::createFromList($versions, sortDirection: \SortDirection::Descending);

        return new self(array_first($versions), array_last($versions), $lowerInclusive, $upperInclusive);
    }

    public static function createFromConstraint(ConstraintInterface $constraint): self
    {
        $min = $constraint->getLowerBound();
        $max = $constraint->getUpperBound();

        if ($min->isZero()) {
            $min = null;
        }

        if ($max->isPositiveInfinity()) {
            $max = null;
        }

        return new self(
            $min ? Version::fromString($min->getVersion()) : null,
            $max ? Version::fromString($max->getVersion()) : null,
            $min?->isInclusive() ?? false,
            $max?->isInclusive() ?? false,
        );
    }

    public function isWithinRange(Version $version): bool
    {
        if ($this->constraint === null) {
            return false;
        }

        return $this->constraint->matches(self::$versionParser->parseConstraints((string) $version));
    }

    /**
     * Return whether the versions cover the bounds of this VersionRange.
     *
     * For example a range of `>=1.0 <=3.0` requires at least one version
     * covers the lower bound of `1.0` *and* another the upper bound of `3.0`.
     *
     * This method should only be used for specific validation purposes.
     * To check if the constraints matches for a version use
     * {@see VersionRange::isWithinRange()} instead.
     *
     * Note:
     *
     *    Only fully inclusive VersionRanges can be checked for bounds covering.
     *
     *    Checking the bounds of an exclusive VersionRange requires too much knowledge of
     *    what is considered acceptable for `<2.0`, technically less than `1.0` is
     *    considered acceptable, but are we looking for `1.9`, `1.99` or `1.9999999`??
     */
    public function areBoundsCoveredBy(Version ...$versions): bool
    {
        if (! $this->lowerInclusive || ! $this->upperInclusive) {
            throw new \InvalidArgumentException('Cannnot check bounds covering unless both bounds are inclusive.');
        }

        if ($this->lowerBound === null || $this->upperBound === null) {
            return false;
        }

        $lowerBoundCovered = false;
        $upperBoundCovered = false;

        foreach ($versions as $version) {
            $lowerBoundCovered = $lowerBoundCovered ?: $this->lowerBound->equals($version);
            $upperBoundCovered = $upperBoundCovered ?: $this->upperBound->equals($version);
        }

        return $lowerBoundCovered && $upperBoundCovered;
    }

    public function __toString(): string
    {
        $str = [];

        if ($this->lowerBound) {
            $str[] = ($this->lowerInclusive ? '>=' : '>') . $this->lowerBound->format();
        }

        if ($this->upperBound) {
            $str[] = ($this->upperInclusive ? '<=' : '<') . $this->upperBound->format();
        }

        return implode(' ', $str);
    }

    /**
     * Returns a normalized version range string.
     *
     * Note that this is to be used for display purposes only.
     * Use {@see VersionRange::__toString()} instead to get a constraint string.
     *
     * By default pre-releases are normalized, change the format to display them fully.
     * Examples without a patch/buid had that version identifier omitted at construction.
     *
     * Examples:
     *
     * * `>=1.0 =<3.0`     becomes `1.0 - 3.0`
     * * `>=1.0 =<3.0.0`   becomes `1.0 - 3.0.0`
     * * `>=1.0 =<3.0.0.5` becomes `1.0 - 3.0.0.5`
     *
     * For exclusive ranges, the bounds are displayed as lower/higher than.
     * We cannot guess what the previous version would be.
     *
     * * `>0.1 <3.0.0`    becomes `>0.1 - <3.0.0`
     * * `>0.1 <3.0.0.0`  becomes `>0.1 - <3.0.0.0`
     * * `>=0.1 <3.0.0`   becomes `0.1 - <3.0.0`
     * * `>=0.1 <3.0.0.0` becomes `0.1 - <3.0.0.0`
     *
     * @param string $format the format to use for the versions
     *                       See {@see Version::format()} for more information
     */
    public function toNormalizedStr(?string $format = '{major}.{minor}.{?patch}.{?build}'): string
    {
        if ($this->lowerBound && $this->upperBound === null) {
            return ($this->lowerInclusive ? '>=' : '>') . $this->lowerBound->format($format);
        }

        if ($this->upperBound && $this->lowerBound === null) {
            return ($this->upperInclusive ? '<=' : '<') . $this->upperBound->format($format);
        }

        $str = [];

        if ($this->lowerBound) {
            $str[] = ($this->lowerInclusive ? '' : '>') . $this->lowerBound->format($format);
        }

        if ($this->upperBound) {
            $str[] = ($this->upperInclusive ? '' : '<') . $this->upperBound->format($format);
        }

        return implode(' - ', $str);
    }
}
