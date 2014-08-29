phpd (unsupported proof of concept)
===================================

Some PHP-CLI scripts involve a large amount of code. Ordinarily, one
executes PHP-CLI commands via /usr/bin/php -- but this negates opcode
caching.  phpd is an experimental alternative which relays PHP-CLI commands
to an HTTP server -- which provides opcode caching.

It is not certain if or when there's a performance advantage to processing
CLI commands this way.

Usage
=====

```bash
## Execute phpunit with traditional /usr/bin/php
php `which phpunit` --log-junit /tmp/results.xml tests/MyExampleTest.php

## Execute phpunit inside a webserver
phpd `which phpunit` --log-junit /tmp/results.xml tests/MyExampleTest.php
```

Installation
============

 - Copy examples/stub.php.ex to the document root and change the "require" path to point to your installation.
 - Copy examples/phpd.ini.ex to $HOME/.phpd.ini and set each value
