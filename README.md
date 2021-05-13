Neo4J connectivity
==================

[![Build status on GitHub](https://github.com/xp-forge/neo4j/workflows/Tests/badge.svg)](https://github.com/xp-forge/neo4j/actions)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Requires PHP 7.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_0plus.svg)](http://php.net/)
[![Supports PHP 8.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-8_0plus.svg)](http://php.net/)
[![Latest Stable Version](https://poser.pugx.org/xp-forge/neo4j/version.png)](https://packagist.org/packages/xp-forge/neo4j)

This library implements Neo4J connectivity via its REST API.

Examples
--------
Running a query can be done via `open()` (which yields one record at a time) or `query()` (which collects the results in an array):

```php
use com\neo4j\Graph;
use util\cmd\Console;

$g= new Graph('http://user:pass@neo4j-db.example.com:7474/db/data');
$q= $g->open('MATCH (t:Topic) RETURN t.name, t.canonical');
foreach ($q as $record) {
  Console::writeLine('#', $record['t.canonical'], ': ', $record['t.name']);
}
```

Formatting parameters uses *printf*-like format tokens. These will take care of proper escaping and type casting:

```php
use com\neo4j\Graph;
use util\cmd\Console;

$g= new Graph('http://user:pass@neo4j-db.example.com:7474/db/data');
$g->query('CREATE (p:Person) SET t.name = %s, t.id = %d', $name, $id);
```

Batch statements can be executed via the `execute()` method.

Format characters
-----------------

* `%s`: Format a string
* `%d`: Format a decimal number
* `%f`: Format a floating point numer
* `%b`: Format a boolean
* `%v`: Copy value into parameter
* `%l`: Copy label into query
* `%c`: Copy literal into query
* `%%`: A literal percent sign

Positional parameters (starting at 1) may be used, e.g. `%2$s`.
