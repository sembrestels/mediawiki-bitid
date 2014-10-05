## BitID for MediaWiki

### Requirements

The MediaWiki **minimum** version is 1.23.0.

Also, make sure you have the GMP and GD extensions for PHP. In Debian/Ubuntu install:

```sh
$ sudo apt-get install php5-gmp php5-gd
```

### Installation

Add the following line in your `LocalSettings.php`.

```php
require_once( "$IP/extensions/BitId/BitId.php" );
```

In the shell run the update script:

```sh
$ cd $IP/maintenance
$ php update.php
```
