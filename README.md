Rollerworks Semver Component
============================

Semantic Versioning helper library.
Validation, incrementing (get next possible version(s)). 

Requirements
------------

You need at least PHP 7.0 

Installation
------------

To install this package, add `rollerworks/version` to your composer.json

```bash
$ php composer.phar require rollerworks/version
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

Now, Composer will automatically download all required files, and install them
for you.

Basic usage
-----------

```php
require 'vendor/autoload.php';

use Rollerworks\Component\Version\Version;
use Rollerworks\Component\Version\VersionsValidator;

// Creates an immutable Version value-object.
// Any call to this object will produce a new Version object
$version = Version::fromString('v1.3.2');

$newVersion = $version->increase('major');  // v2.0.0
$newVersion = $version->increase('minor');  // v1.4.0
$newVersion = $version->increase('patch');  // v1.3.3
$newVersion = $version->increase('stable'); // v1.4.0

$newVersion = $version->increase('alpha'); // v1.4.0-ALPHA1
$newVersion = $version->increase('beta');  // v1.4.0-BETA1
$newVersion = $version->increase('rc');    // v1.4.0-RC1

// ...
// Increasing minor or patch is prohibited until the meta-ver (alpha,beta,rc) is 0

$version = Version::fromString('v1.4.0-BETA1');
$newVersion = $version->increase('beta');   // v1.4.0-BETA2
$newVersion = $version->increase('rc');     // v1.4.0-RC1
$newVersion = $version->increase('rc');     // v1.4.0-RC1
$newVersion = $version->increase('major');  // v1.4.0
$newVersion = $version->increase('stable'); // v1.4.0

// Version validation
// ... //

$tags = [
    '0.1.0',
    'v1.0.0-beta1',
    'v1.0.0-beta2',
    'v1.0.0-beta6',
    'v1.0.0-beta7',
    '1.0.0',
    'v1.0.1',
    'v1.1.0',
    'v2.0.0',
    'v3.5-beta1',
];

// Return an array with major version as key, and the highest possible
// version for that major as Version object
$versions = VersionsValidator::getHighestVersions($tags);

// [
//     0 => Version::fromString('0.1.0'),
//     1 => Version::fromString('1.1.0'),
//     2 => Version::fromString('2.0.0'),
//     3 => Version::fromString('3.5.0-beta1'),
// ]

// $possibleVersions is a returning reference holding a list of acceptable versions
VersionsValidator::isVersionContinues($versions, Version::fromString('v0.2.0'), $possibleVersions); // true
VersionsValidator::isVersionContinues($versions, Version::fromString('v0.1.1'), $possibleVersions); // true
VersionsValidator::isVersionContinues($versions, Version::fromString('v1.3.2'), $possibleVersions); // false
```

Versioning
----------

For transparency and insight into the release cycle, and for striving
to maintain backward compatibility, this package is maintained under
the Semantic Versioning guidelines as much as possible.

Releases will be numbered with the following format:

`<major>.<minor>.<patch>`

And constructed with the following guidelines:

* Breaking backward compatibility bumps the major (and resets the minor and patch)
* New additions without breaking backward compatibility bumps the minor (and resets the patch)
* Bug fixes and misc changes bumps the patch

For more information on SemVer, please visit <http://semver.org/>.

License
-------

The source of this package is subject to the MIT license that is bundled
with this source code in the file [LICENSE](LICENSE).
