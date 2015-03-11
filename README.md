# OpenStackFixRoutes
Fix IP routes for multiple ethernet interfaces on Cloud VPS openstack platform, **currently for Red Hat based linux distributions only.**

## Install dependencies:

``` bash
 yum install php git
```

## Setup script

``` bash
cd /usr/src
git clone https://github.com/RubenHarms/OpenStackFixRoutes.git
cd OpenStackFixRoutes
sh setup.sh
```

## Run script
``` bash
./routefix
```

## Direct Admin

If you use DA, you just have to add the IP addresses after running this script. If you already added the IP addresses in DA, please remove them before you run this script. You could safely ignore the warnings of DA.


# Donations

This software is free to use! If you love this software, please buy me a cup of coffee!

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=3M7CDKARQEZXN"><img src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif" alt="[paypal]" /></a>

