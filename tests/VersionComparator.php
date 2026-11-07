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

namespace Rollerworks\Component\Version\Tests;

use Rollerworks\Component\Version\Version;
use SebastianBergmann\Comparator\Comparator;
use SebastianBergmann\Comparator\ComparisonFailure;
use SebastianBergmann\Exporter\Exporter;

/**
 * @internal
 */
final class VersionComparator extends Comparator
{
    public function accepts(mixed $expected, mixed $actual): bool
    {
        return $expected instanceof Version && $actual instanceof Version;
    }

    public function assertEquals(mixed $expected, mixed $actual, float $delta = 0.0, bool $canonicalize = false, bool $ignoreCase = false): void
    {
        \assert($expected instanceof Version);
        \assert($actual instanceof Version);

        if (! $expected->equalTo($actual, $expected->build === null)) {
            $exporter = new Exporter();

            throw new ComparisonFailure(
                $expected,
                $actual,
                $exporter->export($expected),
                $exporter->export($actual),
                \sprintf(
                    '%s is not the same as "%s".',
                    $actual->full,
                    $expected->full,
                ),
            );
        }
    }
}
