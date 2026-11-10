UPGRADE
=======

## Upgrade FROM 0.3 to 0.4

* Support for PHP < 8.3 was dropped.

* The `Version` class is now explicitly read-only.

* Metaver is renamed to pre-release (`preRelease` for properties)
  to better align with the official Semver terminology.

* The stability now uses an enum.

  ```php
  $version = Version::fromString('v0.2.0');
  
  // Old
  $version->getNextIncreaseOf('stable');
  
  // New
  $version->getNextIncreaseOf(VersionType::Stable);
  
  // ---
  
  // Property contains now an instance of the `Stability` Enum.
  $version->stability;
  ```  
  
* The `Version::__toString` method now returns a formatted string instead. 
  Formatted in accordance with the `$outputFormat` format.

  Use `$version->full` to get the normalized version string.
  Or use `$version->format()` to get the version in a specific format.

  The `format()` method still expects a version is formatted in compliance 
  with the SemVer formatting of `{major}.{minor}.{patch}` (where patch might be omitted).

  Either:

   ```php
   // {major} and {minor} are required, other identifiers might be omitted
   // identifiers starting with a `?` are only filled-in when they have a value
   // *and* in the case of a provided {build} the {patch} is always filled.
   //
   // The v (or V) prefix is allowed, but nothing else.
   // pre-release is formatted as: -{pre-release:casing}[separator '-', '.' or omitted][number]
   // Either: `-{pre-release:title}.x` formats as `-Alpha.5`
   //
   // Shown with full format (same as `full` property)
   // See methods PHPDoc for all available format options.
   $version->format('{major}.{minor}.{patch}.{build}-{pre-release:title}.x');
   ```
  
* The `Version::getNextVersionCandidates()` method now only considers a patch version
  when the provided version has a patch version.

* The `Version::getNextVersionCandidates()` method now also considers a build version
  when the provided version has a build version.

> [!TIP]
> The `Version::fromString()` now accepts (bitwise) options to either fill-in 
> any missing patch or build identifier. With `Version::INPUT_FILL_PATCH`
> and/or `Version::INPUT_FILL_BUILD` respectively.
>
> Or alternatively remove the patch or build identifier with `Version::INPUT_INORE_PATCH`
> and/or `Version::INPUT_IGNORE_BUILD` respectively.
>
> In both cases (filling or ignoring) the patch will be properly handled to conform
> to the semantic versioning specification. 
> _The patch identifier cannot be omitted when build is set._

## Upgrade FROM 0.2 to 0.3

* The method `increase` of `Version` is renamed to `getNextIncreaseOf`.

* The `VersionValidator` has been renamed to `ContinuesVersionsValidator`.

* The `ContinuesVersionsValidator` has api changed to separate the
  validation from providing suggestions.
  
  Before:

  ```php
  VersionsValidator::isVersionContinues($versions, Version::fromString('v0.2.0'), $possibleVersions);
  ```

  After:

  ```php
  $validator = new ContinuesVersionsValidator(...$existingVersions); // Expects the versions as a variadic arguments
 
  $result = ContinuesVersionsValidator::isContinues(Version::fromString('v0.2.0'));
  $possibleVersions = $validator->getPossibleVersions(); // Must be called after isContinues(), otherwise empty
  ```
   
  **Note:** The suggested versions did not take existing versions into account.

  Now, instead if a newer major or minor version already exists it only allows
  a patch release for the bounded minor version. If both 1.1 and 2.0 exist then
  1.2 is no longer suggested, nor considered an acceptable increment.
  
  Otherwise all possible suggestions are accepted.
