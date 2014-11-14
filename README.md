PHPVmwareVsphere
================

A PHP Class for communicating with the Vmware Vsphere WSDL API

About:

This class is designed to consume read only data from the Vmware Vsphere (WSDL) web services API. I wrote it because I could not find any other library that provided this functionality and "just worked". Vmware do not provide a PHP SDK.
		
Requirements:	

1) This class uses PHP SoapClient so please ensure you have this enabled, you can check this by running phpinfo(); or php -m from the command line.

2) This class requires a connection to a vcenter that manages one or many ESX hosts with an appropriate username and password that can access the data.

Testing:

This has been tested on Vmware Vsphere 5.5 using PHP 5.3.10 on linux (but should work on windows as well)

Usage:

require("Vsphere_Class.php");

$vsphere = new Vsphere("ip address", "username", "password");

$vsphere->setEsxHosts(array("esxi1.local", "esxi2.local"));

$result = $vsphere->getAllVmsFromESXHosts();

var_dump($result);
