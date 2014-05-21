## BitID for MediaWiki

### Installation

Make sure you have the GMP extension for PHP. In Debian/Ubuntu install:

```
$ sudo apt-get install php5-gmp
```

Add the following line in your `LocalSettings.php`.

```php
require_once( "$IP/extensions/BitId/BitId.php" );
```
