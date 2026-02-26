UPGRADE
=======

## Upgrade FROM 0.3 to 0.4

* Support for PHP < 8.1 was dropped.

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
