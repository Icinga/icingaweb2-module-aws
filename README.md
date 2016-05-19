AWS module for Icinga Web 2
===========================

This is a simple AWS module. Currently it is nothing but a import source
provider for Icinga Director. You need to extract the AWS PHP SDK v2 to
`library/vendor/aws` and at least one AWS access key in `keys.ini`. Create
a file `/etc/icingaweb2/modules/aws/keys.ini` as follows:

```ini
[My readonly AWS key]
access_key_id = RANDOMANFASDFNASDOFA
secret_access_key = WhatASDmn0asdnfASNDInafsdofdasJ980hansdf
```

Proxy usage
-----------

In case your server needs to use a proxy when connection to the AWS web service
please create `/etc/icingaweb2/modules/aws/config.ini` with a `network` section
like shown in this example:

```
[network]
proxy = "192.0.2.192:3128"
```

You could also pass proxy credentials in the form `user:pass@host:port`.
