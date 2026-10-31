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

enum VersionType: string
{
    case Major = 'major';

    case Minor = 'minor';

    case Patch = 'patch';

    case Alpha = 'alpha';

    case Beta = 'beta';

    case Rc = 'rc';

    case Stable = 'stable';

    case Next = 'next';

    public static function fromString(string $type): self
    {
        return match (strtolower($type)) {
            'major' => self::Major,
            'minor' => self::Minor,
            'patch' => self::Patch,
            // ---
            'alpha' => self::Alpha,
            'beta' => self::Beta,
            'rc' => self::Rc,
            'stable' => self::Stable,
            'next' => self::Next,

            default => throw new \InvalidArgumentException(\sprintf('Invalid version type "%s" given, only accepts: "%s".', $type, implode('", "', array_map(static fn (self $type): string => $type->name, self::cases())))),
        };
    }
}
