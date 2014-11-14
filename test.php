<?php
require("Vsphere_Class.php");
/* connect to a vcenter */
$vsphere = new Vsphere("<vcenter ip address>", "<vcenter username>", "<vcenter password>");
/* set the names of each esx host this vcenter manages */
$vsphere->setEsxHosts(array("esxi1.local", "esxi2.local"));
/* get all hosts and virtual machines */
$result = $vsphere->getAllVmsFromESXHosts();

var_dump($result);