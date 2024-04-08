# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.2] - 2024-04-08

### Changed

- fixed bug in generateParameterName method

## [2.0.1] - 2024-03-27

### Changed

- fixed bug in fetchObjects method

## [2.0.0] - 2024-03-13

### Changed

- update to PHP 8.0
- added types to all methods

## [1.5.1] - 2023-01-10

### Added

- added `generateParametersFromArray`
- update method annotations

## [1.4.1] - 2022-06-29

### Added

- support hydration objects (auto cast types from annotations and property types). See `demo/hydration.php`
- added `enableResultCache` and `disableResultCache` methods
- deprecated `useResultCache` method

## [1.3.2] - 2021-02-10

### Changed

- fixed bug in delete query with join

## [1.3.1] - 2021-02-10

### Added

- support delete with joins

## [1.2.2] - 2021-01-21

### Changed

- fixed bug in update query alias

## [1.2.1] - 2020-11-10

### Changed

- opimize result cachek
- added result cache ky prefix option

## [1.1.1] - 2020-10-02

### Changed

- add 'alias' parameter in update method

## [1.0.3] - 2020-06-08

### Changed

- remove ';' from query

## [1.0.1] - 2020-05-25

### Added

- moved from hemiframe
- CHANGELOG.MD
- implement PHP Stan
