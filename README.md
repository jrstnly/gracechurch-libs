# Grace Church Libraries

A collection of PHP classes required for various Grace Church systems


## Installing

Add private respository into composer config
```
composer config repositories.gracechurch-libs vcs https://github.com/jrstnly/gracechurch-libs.git
```

Add to composer required packages
```
composer require jrstnly/gracechurch-libs:dev-master
```


## Acknowledgments
* CCB Event Parser built off of work done by Ev Free Fullerton https://github.com/EvFreeFullerton/CCBLiveMap/blob/master/scripts/CCBEventParser.php
* Zebra Printer class taken from robgridley/zebra and edited to allow response from printer when sending commands https://github.com/robgridley/zebra
