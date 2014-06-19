## BitID for MediaWiki

### Requirements

The MediaWiki **minium** version is 1.23.0.

Also, make sure you have the GMP extension for PHP. In Debian/Ubuntu install:

```sh
$ sudo apt-get install php5-gmp
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