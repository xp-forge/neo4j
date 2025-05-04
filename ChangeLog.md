Neo4J for XP Framework ChangeLog
========================================================================

## ?.?.? / ????-??-??

## 2.0.0 / 2025-05-04

* **Heads up:** Dropped support for PHP < 7.4, see xp-framework/rfc#343
  (@thekid)
* Added PHP 8.5 to test matrix - @thekid

## 1.2.0 / 2024-03-24

* Made compatible with XP 12 - @thekid
* Added PHP 8.4 to the test matrix - @thekid
* Merged PR #6: Migrate to new testing library - @thekid

## 1.1.2 / 2022-02-27

* Fixed "Creation of dynamic property" warnings in PHP 8.2 - @thekid

## 1.1.1 / 2021-10-21

* Made library compatible with XP 11, `xp-forge/json` version 5.0.0
  (@thekid)

## 1.1.0 / 2021-05-13

* Merged PR #5: Add fetch() method to return single result, or NULL
  (@thekid)

## 1.0.1 / 2020-04-10

* Made compatible with new library versions for HTTP and JSON - @thekid

## 1.0.0 / 2019-12-01

* Implemented xp-framework/rfc#334: Drop PHP 5.6. The minimum required
  PHP version is now 7.0.0!
  (@thekid)

## 0.2.1 / 2019-12-01

* Made compatible with XP 10 - @thekid

## 0.2.0 / 2017-09-30

* Changed Graph constructor to accept URLs or HttpConnection instances
  (@thekid)
* Merged PR #1: Use HTTP API directly, reduce dependencies - @thekid
* Added `toString()` method to `com.neo4j.Graph` class - @thekid

## 0.1.0 / 2017-09-30

* First public release - (@thekid)
