Neo4J Connector
===============

[![Build Status on TravisCI](https://secure.travis-ci.org/xp-forge/neo4j.svg)](http://travis-ci.org/xp-forge/neo4j)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Required PHP 5.6+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-5_6plus.png)](http://php.net/)
[![Supports PHP 7.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_0plus.png)](http://php.net/)
[![Supports HHVM 3.4+](https://raw.githubusercontent.com/xp-framework/web/master/static/hhvm-3_4plus.png)](http://hhvm.com/)
[![Latest Stable Version](https://poser.pugx.org/xp-forge/neo4j/version.png)](https://packagist.org/packages/xp-forge/neo4j)

This library implements Neo4J connectivity via its REST API.

Examples
--------
Running a query:

```php
use com\neo4j\Graph;
use util\cmd\Console;

$g= new Graph('http://user:pass@neo4j-db.example.com:7474/db/data');
$q= $g->open('match (t:Topic) return t.name, t.canonical');
foreach ($q as $record) {
  Console::writeLine('#', $record['t.canonical'], ': ', $record['t.name']);
}
```