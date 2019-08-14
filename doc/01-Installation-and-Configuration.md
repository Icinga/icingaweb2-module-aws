<a name="Installation-and-Configuration"></a>Installation
============

Requirements
------------

This module needs the [AWS PHP SDK v3](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/welcome.html).

Module installation
-------------------

Please extract or clone this module to your Icinga Web 2 module path. The
directory name must fit the module name, `aws`. This would usually lead to
`/usr/share/icingaweb2/modules/aws`.

Install AWS SDK
----------------

#### Via Composer

For this you need [Composer](https://getcomposer.org/) on your machine. 
In `/icingaweb2/modules/aws`, run `composer install` and all modules dependencies will be installed. 

#### Manual Install

Next please download and extract the latest v3 standalone ZIP archive from
the AWS PHP SDK [releases](https://github.com/aws/aws-sdk-php/releases) page.
You need to extract the AWS PHP SDK v3 to `library/vendor/aws`.

AWS IAM role credentials
------------------------

If you run Icinga Web on AWS you can use IAM roles to allow access. This is the
default and there is nothing to configure. Select IAM role and configure access
in AWS itself. 

AWS key configuration
---------------------

If you want to use access keys you need to have at least one key in `keys.ini`.
Create a file `/etc/icingaweb2/modules/aws/keys.ini` as follows:

```ini
[My readonly AWS key]
access_key_id = RANDOMANFASDFNASDOFA
secret_access_key = WhatASDmn0asdnfASNDInafsdofdasJ980hansdf
```

That's it. Now you are ready to enable the AWS module and you'll find a new
Import Source in your Icinga Director frontend. You are now ready to skip to
the [Usage](02-Usage.md) section.

Proxy usage
-----------

In case your server needs to use a proxy when connection to the AWS web service
please create `/etc/icingaweb2/modules/aws/config.ini` with a `network` section
like shown in this example:

```ini
[network]
proxy = "192.0.2.192:3128"
```

You could also pass proxy credentials in the form `user:pass@host:port`.

SSL issues
----------

In case you need to provide a specific SSL CA bundle, once again please create
a `[network]` section in your `config.ini`:

```ini
[network]
ssl_ca = "/etc/ssl/certs/ca.pem"
```
