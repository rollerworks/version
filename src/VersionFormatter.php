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

final class VersionFormatter
{
    /**
     * Full Output-format (with optional `{patch}`, optional `{build}`, uppercase pre-release without seperator).
     *
     * Either: "1.0-ALPHA1", "1.0.0", "1.0.0.0", "1.0.0.5-ALPHA1"
     */
    public const string FORMAT_DEFAULT = '{major}.{minor}.{?patch}.{?build}-{metaver:upper}x';

    /**
     * Full Output-format (with `{patch}`, `{build}`, uppercase pre-release without seperator).
     *
     * Either: "1.0.0-ALPHA1", "1.0.0.0", or "1.0.0-ALPHA1", "1.0.0.5" (when build number is set)
     */
    public const string FORMAT_FULL = '{major}.{minor}.{patch}.{build}-{metaver:upper}x';

    /**
     * Full Output-format (with `{patch}`, without `{build}`, uppercase pre-release without no seperator).
     *
     * Either: "1.0.0-ALPHA1", "1.0.0"
     */
    public const string FORMAT_WITHOUT_BUILD = '{major}.{minor}.{patch}-{metaver:upper}x';

    private int $position = 0;
    private string $prefix;
    private IdentifierMarker $patchPattern;
    private IdentifierMarker $buildPattern;

    /** @var array{casing: string, separator: string}|null */
    private ?array $metaverPattern = null;

    /**
     * @throws \InvalidArgumentException when the format is invalid
     */
    public function format(Version $version, string $format = self::FORMAT_DEFAULT): string
    {
        $this->prefix = '';
        $this->metaverPattern = ['casing' => 'upper', 'separator' => ''];

        if ($format === self::FORMAT_FULL) {
            $this->patchPattern = IdentifierMarker::Always;
            $this->buildPattern = IdentifierMarker::Always;
        } elseif ($format === self::FORMAT_DEFAULT) {
            $this->patchPattern = IdentifierMarker::WhenProvided;
            $this->buildPattern = IdentifierMarker::WhenProvided;
        } elseif ($format === self::FORMAT_WITHOUT_BUILD) {
            $this->patchPattern = IdentifierMarker::Always;
            $this->buildPattern = IdentifierMarker::Omit;
        } else {
            $this->parse($format);
        }

        $versionString = $this->prefix;
        $versionString .= \sprintf('%d.%d', $version->major, $version->minor);

        $versionString .= $this->formatVersionIdentifier($this->patchPattern, $version->patch);
        $versionString .= $this->formatVersionIdentifier($this->buildPattern, $version->build);

        if ($this->metaverPattern !== null && $version->major > 0 && $version->stability->isLowerThan(Stability::Stable)) {
            $versionString .= \sprintf('-%s%s%d', $version->stability->label($this->metaverPattern['casing']), $this->metaverPattern['separator'], $version->metaver);
        }

        return $versionString;
    }

    /**
     * Modifies the format of the version string by applying the provided options.
     *
     * Use `null` to keep the orginal formatting of the specific identifier.
     *
     * **Note:** When the `{patch}` identifier becomes omitted, but `{build}`
     * is provided, the `{patch}` identifier must be included.
     *
     * @param 'v'|'V'|null                 $prefix
     * @param 'upper'|'lower'|'title'|null $metaverCashing
     * @param '-'|''|null                  $metaverSeparator
     *
     * @throws \InvalidArgumentException when the format is invalid or when the metaver options are invalid
     */
    public function modifyFormat(string $original, ?string $prefix = null, ?IdentifierMarker $patch = null, ?IdentifierMarker $build = null, ?bool $metaVer = null, ?string $metaverCashing = null, ?string $metaverSeparator = null): string
    {
        $this->parse($original);

        if (! \in_array($prefix, ['v', 'V', null], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid prefix "%s" provided. Expects "v" or "V".', $prefix));
        }

        if (! \in_array($metaverCashing, ['upper', 'lower', 'title', null], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid metaver casing "%s" provided. Expects "upper", "lower", "title", or null.', $metaverCashing));
        }

        if (! \in_array($metaverSeparator, ['-', '_', '', null], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid metaver separator "%s" provided. Expects "-", "_", "" (empty string), or null.', $metaverSeparator));
        }

        $this->prefix = $prefix ?? $this->prefix;
        $this->patchPattern = $patch ?? $this->patchPattern;
        $this->buildPattern = $build ?? $this->buildPattern;

        if ($this->patchPattern === IdentifierMarker::Omit && $this->buildPattern !== IdentifierMarker::Omit) {
            throw new \InvalidArgumentException('Build cannot be used when "patch" identifier is omited.');
        }

        if ($metaVer === false && ($metaverCashing || $metaverSeparator)) {
            throw new \InvalidArgumentException('Metaver options "$metaverCashing" and/or "$metaverSeparator" cannot be provided when metaver is disabled, pass null to these arguments or remove them from the invocation.');
        }

        $format = $this->prefix . '{major}.{minor}' . $this->patchPattern->toFormat('patch') . $this->buildPattern->toFormat('build');

        if ($metaVer ?? ($this->metaverPattern !== null)) {
            $format .= \sprintf(
                '-{metaver:%s}%sx',
                $metaverCashing ?? $this->metaverPattern['casing'] ?? 'upper',
                $metaverSeparator ?? $this->metaverPattern['separator'] ?? ''
            );
        }

        return $format;
    }

    private function parse(string $format): void
    {
        $length = \strlen($format);

        if ($length < 15) {
            throw new \InvalidArgumentException(\sprintf('Invalid format "%s" provided. Expects something like: "{major}.{minor}.{patch}".', $format));
        }

        if (! preg_match('/^(?P<prefix>[vV])?\{major}\.\{minor}?/', $format, $matches)) {
            throw new \InvalidArgumentException(\sprintf('Invalid format "%s" provided. Expects format to start with optional (v or V) followed by "{major}.{minor}"', $format));
        }

        $this->prefix = $matches['prefix'] ?? '';
        $this->position = \strlen($matches[0]);

        $this->metaverPattern = null;
        $this->patchPattern = $this->matchInFormat($format, 'patch');
        $this->buildPattern = $this->matchInFormat($format, 'build');

        if ($this->buildPattern !== IdentifierMarker::Omit && $this->patchPattern === IdentifierMarker::Omit) {
            throw new \InvalidArgumentException(\sprintf('Invalid format "%s" provided. The "{build}" identifier cannot be used unless the "{patch}" identifier is also provided (before the "{build}" identifier) .', $format));
        }

        // No meta-version
        if ($this->position >= $length) {
            return;
        }

        if (! preg_match('/^-\\{metaver(?::(?P<casing>[^}]+))?}(?P<separator>[-.]?)x/', substr($format, $this->position), $matches)) {
            throw new \InvalidArgumentException(\sprintf('Invalid format "%s" provided at position "%s". Expected something like ("-{metaver}-x", "-{metaver}.x", or "-{metaver}x". As no other seperators are accepted) at this position. Note that the dash (-) prefix is mandatory, and the "x" cannot be omitted.', $format, substr($format, $this->position)));
        }

        if ($matches['casing'] !== '' && ! \in_array($matches['casing'], ['upper', 'lower', 'title'], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid format "%s" provided at position "%s". Unknown casing "%s" for "{metaver}" identifier. Expected either "upper", "lower" or "title".', $format, substr($format, $this->position), $matches['casing']));
        }

        $this->metaverPattern = [
            'casing' => $matches['casing'] ?: 'upper',
            'separator' => $matches['separator'],
        ];

        $this->position += \strlen($matches[0]);

        if ($length > $this->position) {
            throw new \InvalidArgumentException(\sprintf('Invalid format "%s" provided at position "%s". Expected nothing more at this position. Note that any custom suffix like is not accepted.', $format, substr($format, $this->position)));
        }
    }

    private function formatVersionIdentifier(IdentifierMarker $marker, ?int $ver): string
    {
        if ($marker === IdentifierMarker::Always) {
            return '.' . ($ver ?? 0);
        }

        if ($marker === IdentifierMarker::WhenProvided && $ver !== null) {
            return '.' . $ver;
        }

        return '';
    }

    private function matchInFormat(string $input, string $expected): IdentifierMarker
    {
        if (! preg_match(\sprintf('/^\\.\\{(?P<optional>\\??)%s}/s', $expected), substr($input, $this->position), $matches)) {
            return IdentifierMarker::Omit;
        }

        $this->position += \strlen($matches[0]);

        return $matches['optional'] === '?' ? IdentifierMarker::WhenProvided : IdentifierMarker::Always;
    }
}
