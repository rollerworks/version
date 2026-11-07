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

/**
 * IdentifierMarker is used for the VersionFormatter to indicate when to include the identifier.
 */
enum IdentifierMarker
{
    case Always;

    case Omit;

    case WhenProvided;

    /**
     * @internal this method is not covered by the BC promise
     */
    public function toFormat(string $identifier): string
    {
        return match ($this) {
            self::Always => \sprintf('.{%s}', $identifier),
            self::WhenProvided => \sprintf('.{?%s}', $identifier),
            self::Omit => '',
        };
    }
}
