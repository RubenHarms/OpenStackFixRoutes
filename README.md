# OpenStackFixRoutes
Fix IP routes for multiple ethernet interfaces on Cloud VPS openstack platform, **currently for Red Hat based linux distributions only**

# Install dependencies:

``` bash
 yum install php git
```

# Setup script

``` bash
cd /usr/src
git clone https://github.com/RubenHarms/OpenStackFixRoutes.git
cd OpenStackFixRoutes
sh setup.sh
```

# Run script
``` bash
./routefix
```
