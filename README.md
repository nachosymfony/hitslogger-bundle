# hitslogger-bundle

## Installation
### Step 1 - Install required composer modules

```
composer require nacholibre/hitslogger-bundle
```

### Step 2 - Add modules in AppKernel.php

```
<?php
// app/AppKernel.php

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...
            new nacholibre\HitsLoggerBundle\nacholibreHitsLoggerBundle(),
        );

        // ...
    }

    // ...
}
```

### Step 3 - Register bundle routing
```
# app/config/routing.yml

nacholibre_hits_logger:
    resource: "@nacholibreHitsLoggerBundle/Controller/"
    type:     annotation
    prefix:   /cpanel/hits_logger
```

### Step 4 - Configure your mappings
```
# app/config/config.yml

nacholibre_hits_logger:
    history_count: 50
    redis_service_name: "@snc_redis.default"
```
