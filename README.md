# magento-connect-composer-plugin
A Composer plugin to install packages from Magento Connect using `composer`, without using a custom `satis` install like http://packages.firegento.com/

This tool replaces using `http://packages.firegento.com/` as I've found it to be unreliable, there are many packages found on connect that are not present. There seems to be a slight lag
on new versions also. I created this plugin instead of contributing as it seems the source for the connect crawling is not public. I believe this tool is slightly more simple in its inner workings.


## Setup

### Require this plugin:

```shell
composer require aydin-hassan/magento-connect-composer-plugin
```

### Define your connect packages:

```json
{
    "name": "some-magento-project",
    "require": {
        "aydin-hassan/magento-connect-composer-plugin" : "~1.0"
    },
    "extra": {
        "connect-packages" : {
            "Adyen_Payment": "~2.3",
            "Aijko_WidgetImageChooser" : "*"
        }
    }
}
```

Note: The connect packages should be the conenct extenison key. The exact same case should be used as the connect server is case-sensitive. All versions available on connect will be available to you
and you can use `composer`'s special operators such as `^` , `~` and `1.*`.

### Update!
```shell
composer update
```

Tip: If you use this plugin inconjunction with [magento-composer-installer](https://github.com/Cotya/magento-composer-installer) the modules will also be installed to your `magento-root-dir`. 

## Finding modules

Either use: 
* [Magento Connect](http://www.magentocommerce.com/magento-connect/) or 
* [Magento Extensions Download](http://ext.topmage.com/)

If you use connect you will need an account to view the extension key.


## Notes
The first time you install the package, if you already have your extra defined, the connect packages will not be downloaded. This is due to the plugin needing to process the extra
before composer performs dependency solving. Any events after the package has been installed for the first time are too late. However, a message will be printed in this circumstance
telling you to run `composer update` again.

This problem is irrelevent if you first install the plugin then define your conenct packages. This issue does not occur when installing from the lock file.
