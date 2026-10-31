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

enum Stability: int
{
    case Alpha = 0;

    case Beta = 1;

    case Rc = 2;

    case Stable = 3;

    /**
     * @param string $stability 'alpha'|'beta'|'rc'|'stable'
     */
    public static function fromString(string $stability): self
    {
        return match (strtolower($stability)) {
            'beta' => self::Beta,
            'alpha' => self::Alpha,
            'stable' => self::Stable,
            'rc' => self::Rc,

            default => throw new \InvalidArgumentException(\sprintf('Invalid stability "%s" given, only accepts: "alpha", "beta", "rc", "stable".', $stability)),
        };
    }

    /**
     * @param string $casing 'upper'|'lower'|'title'
     */
    public function label(string $casing): string
    {
        return match ($casing) {
            'upper' => strtoupper($this->name),
            'lower' => strtolower($this->name),
            'title' => ucfirst($this->name),

            default => throw new \InvalidArgumentException(\sprintf('Invalid casing "%s" given, only accepts: "upper", "lower", "title".', $casing)),
        };
    }

    public function isLowerThan(self $other): bool
    {
        return $this->value < $other->value;
    }

    public function isHigherThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function increment(): self
    {
        return match ($this) {
            self::Alpha => self::Beta,
            self::Beta => self::Rc,
            self::Rc => self::Stable,

            default => throw new \LogicException('Cannot increment stability "stable".'),
        };
    }
}
