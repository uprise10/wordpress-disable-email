Wordpress Disable Email plugin
===============================

Disables Email through wp_mail() on current server hostname. SEmail will by disabled by default, so to enable the wp_mail() funciotnality, the given host name must be EXACT the same as current server hostname. 

You can also override the $to address. A single address, or multiple, comma sperated addresses.

Note: The plugin performs a check on the $_SERVER['HTTP_HOST'] variable.