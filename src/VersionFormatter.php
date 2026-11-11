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
    public const string FORMAT_DEFAULT = '{major}.{minor}.{?patch}.{?build}-{pre-release:upper}x';

    /**
     * Full Output-format (with `{patch}`, `{build}`, uppercase pre-release without seperator).
     *
     * Either: "1.0.0-ALPHA1", "1.0.0.0", or "1.0.0-ALPHA1", "1.0.0.5" (when build number is set)
     */
    public const string FORMAT_FULL = '{major}.{minor}.{patch}.{build}-{pre-release:upper}x';

    /**
     * Full Output-format (with `{patch}`, without `{build}`, uppercase pre-release without no seperator).
     *
     * Either: "1.0.0-ALPHA1", "1.0.0"
     */
    public const string FORMAT_WITHOUT_BUILD = '{major}.{minor}.{patch}-{pre-release:upper}x';

    private int $position = 0;
    private string $prefix;
    private IdentifierMarker $patchPattern;
    private IdentifierMarker $buildPattern;

    /** @var array{casing: string, separator: string}|null */
    private ?array $preReleasePattern = null;

    /**
     * @throws \InvalidArgumentException when the format is invalid
     */
    public function format(Version $version, string $format = self::FORMAT_DEFAULT): string
    {
        $this->prefix = '';
        $this->preReleasePattern = ['casing' => 'upper', 'separator' => ''];

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

        if ($this->preReleasePattern !== null && $version->major > 0 && $version->stability->isLowerThan(Stability::Stable)) {
            $versionString .= \sprintf('-%s%s%d', $version->stability->label($this->preReleasePattern['casing']), $this->preReleasePattern['separator'], $version->preRelease);
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
     * @param 'upper'|'lower'|'title'|null $preReleaseCasing
     * @param '-'|''|null                  $preReleaseSeparator
     *
     * @throws \InvalidArgumentException when the format is invalid or when the pre-release options are invalid
     */
    public function modifyFormat(string $original, ?string $prefix = null, ?IdentifierMarker $patch = null, ?IdentifierMarker $build = null, ?bool $preRelease = null, ?string $preReleaseCasing = null, ?string $preReleaseSeparator = null): string
    {
        $this->parse($original);

        if (! \in_array($prefix, ['v', 'V', null], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid prefix "%s" provided. Expects "v" or "V".', $prefix));
        }

        if (! \in_array($preReleaseCasing, ['upper', 'lower', 'title', null], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid pre-release casing "%s" provided. Expects "upper", "lower", "title", or null.', $preReleaseCasing));
        }

        if (! \in_array($preReleaseSeparator, ['-', '_', '', null], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid pre-release separator "%s" provided. Expects "-", "_", "" (empty string), or null.', $preReleaseSeparator));
        }

        $this->prefix = $prefix ?? $this->prefix;
        $this->patchPattern = $patch ?? $this->patchPattern;
        $this->buildPattern = $build ?? $this->buildPattern;

        if ($this->patchPattern === IdentifierMarker::Omit && $this->buildPattern !== IdentifierMarker::Omit) {
            throw new \InvalidArgumentException('Build cannot be used when "patch" identifier is omited.');
        }

        if ($preRelease === false && ($preReleaseCasing || $preReleaseSeparator)) {
            throw new \InvalidArgumentException('Pre-release options "$preReleaseCasing" and/or "$preReleaseSeparator" cannot be provided when pre-release is disabled, pass null to these arguments or remove them from the invocation.');
        }

        $format = $this->prefix . '{major}.{minor}' . $this->patchPattern->toFormat('patch') . $this->buildPattern->toFormat('build');

        if ($preRelease ?? ($this->preReleasePattern !== null)) {
            $format .= \sprintf(
                '-{pre-release:%s}%sx',
                $preReleaseCasing ?? $this->preReleasePattern['casing'] ?? 'upper',
                $preReleaseSeparator ?? $this->preReleasePattern['separator'] ?? ''
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

        $this->preReleasePattern = null;
        $this->patchPattern = $this->matchInFormat($format, 'patch');
        $this->buildPattern = $this->matchInFormat($format, 'build');

        if ($this->buildPattern !== IdentifierMarker::Omit && $this->patchPattern === IdentifierMarker::Omit) {
            throw new \InvalidArgumentException(\sprintf('Invalid format "%s" provided. The "{build}" identifier cannot be used unless the "{patch}" identifier is also provided (before the "{build}" identifier) .', $format));
        }

        // No pre-release
        if ($this->position >= $length) {
            return;
        }

        if (! preg_match('/^-\\{pre-release(?::(?P<casing>[^}]+))?}(?P<separator>[-.]?)x/', substr($format, $this->position), $matches)) {
            throw new \InvalidArgumentException(\sprintf('Invalid format "%s" provided at position "%s". Expected something like ("-{pre-release}-x", "-{pre-release}.x", or "-{pre-release}x". As no other seperators are accepted) at this position. Note that the dash (-) prefix is mandatory, and the "x" cannot be omitted.', $format, substr($format, $this->position)));
        }

        if ($matches['casing'] !== '' && ! \in_array($matches['casing'], ['upper', 'lower', 'title'], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid format "%s" provided at position "%s". Unknown casing "%s" for "{pre-release}" identifier. Expected either "upper", "lower" or "title".', $format, substr($format, $this->position), $matches['casing']));
        }

        $this->preReleasePattern = [
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
