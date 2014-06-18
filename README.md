## BitID for MediaWiki

### Installation

Make sure you have the GMP extension for PHP. In Debian/Ubuntu install:

```sh
$ sudo apt-get install php5-gmp
```

Add the following line in your `LocalSettings.php`.

```php
require_once( "$IP/extensions/BitId/BitId.php" );
```

In the shell run the update script:

```sh
$ cd $IP/maintenance
$ php update.php
```