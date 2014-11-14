<?php
/**
* A PHP Class to communicate with the Vmware Vsphere WSDL API
*
* @version 1.0.0
* @author Samir Majen <samirmajen@hotmail.com>
* @copyright 2014 Samir Majen <samirmajen@hotmail.com>
* @license http://choosealicense.com/licenses/mit/ MIT license
* @link https://github.com/samirmajen/PHPVmwareVsphere
*/

class Vsphere  {
	private $host;						/* the vcenter host ip address */
	private $username; 					/* vcenter username - user must have sufficient privileges to access the data */
	private $password;					/* vcenter password */
	private $client;					/* soap client instance */
	private $serviceContent;			/* vcenter WSDL serviceContent instance */
	private	$esxHosts		= array();	/* list of esx hosts that the vcenter manages */
	private $serverData		= array();	/* data structure for servers */
	private $hostData		= array();	/* data structure for hosts */
	
	public function __construct($vcenter_ip, $vcenter_username, $vcenter_password) {
		$this->setHost($vcenter_ip);
		$this->setUsername($vcenter_username);
		$this->setPassword($vcenter_password);
		$this->connect();
	}
	
	/* set vcenter host ip address/hostname */
	private function setHost($host) {
		$this->host = $host;
	}
	
	/* set vcenter username */
	private function setUsername($username) {
		$this->username = $username;
	}
	
	/* set vcenter password */
	private function setPassword($password) {
		$this->password = $password;
	}
	
	/* set array of esxhosts that are managed by the vcenter */
	public function setEsxHosts($hosts) {
		$this->esxHosts = $hosts;
	}
	
	/* this method connects to the vcenter WSDL service */
	public function connect() {
		/* create a new instance of a SoapClient */
		try {
			$this->client = new SoapClient("https://" . $this->host . "/sdk/vimService.wsdl", array('login' => $this->username, 'password' => $this->password, "trace" => 1, "location" => "https://" . $this->host . "/sdk/"));  
		} catch (Exception $e) {
           echo "Error: failed to connect to soapclient with error: " . $e->getMessage() . "<br/>";
        }	
		/* setup service content using serviceInstance */
		$soapmessage["_this"] 	= new Soapvar ("ServiceInstance", XSD_STRING, "ServiceInstance");
		$result 				= $this->client->RetrieveServiceContent($soapmessage);	
		$this->serviceContent 	= $result->returnval;
		/* authenticate with the vcenter */
		$this->login();
	}
	
	/* this method authenticates with the vmware web service */
	private function login() {
		/* setup a session using the sessionManager */
		$soapmessage["_this"] 		= $this->serviceContent->sessionManager;
		$soapmessage["userName"]	= $this->username;
		$soapmessage["password"]	= $this->password;
		$result 					= $this->client->Login($soapmessage);
		$usersession 				= $result->returnval;
	}
	
	/* the vmware api returns a lot of data, process it and only extract the bits we want */
	private function extractRelevantData($ret) {
		$vmName = isSET($ret->returnval->obj->_) ? $ret->returnval->obj->_ : ''; /* get the unique virtual machine name */
		$name 	= isSET($ret->returnval->propSet[3]->val->name) ? $ret->returnval->propSet[3]->val->name : ''; /* get actual name of this server */
		$osName = isSET($ret->returnval->propSet[3]->val->guestFullName) ? $ret->returnval->propSet[3]->val->guestFullName : ''; /* get the guest OS this vm is running */
		$UUID 	= isSET($ret->returnval->propSet[3]->val->uuid) ? $ret->returnval->propSet[3]->val->uuid : ''; /* the unique name across all vcenters */
		$memory = isSET($ret->returnval->propSet[3]->val->hardware->memoryMB) ? $ret->returnval->propSet[3]->val->hardware->memoryMB : 0; /* get the memory this device has */
		$cpus 	= isSET($ret->returnval->propSet[3]->val->hardware->numCPU) ? $ret->returnval->propSet[3]->val->hardware->numCPU : ''; /* get the number of cpus this device has (not the cores) */
		$cores 	= isSET($ret->returnval->propSet[3]->val->hardware->numCoresPerSocket) ? $ret->returnval->propSet[3]->val->hardware->numCoresPerSocket : ''; /* get the number of cores per cpu */
		$ip 	= isSET($ret->returnval->propSet[27]->val->guest->ipAddress) ? $ret->returnval->propSet[27]->val->guest->ipAddress : ''; /* get the ip address of this server (TODO: they may be more than one) */
		$parent	= isSET($ret->returnval->propSet[19]->val->_) ? $ret->returnval->propSet[19]->val->_ : ''; /* get the parent folder name */
		$status	= isSET($ret->returnval->propSet[27]->val->overallStatus) ? $ret->returnval->propSet[27]->val->overallStatus : ''; /* get the overall status of this device */
		$host	= isSET($ret->returnval->propSet[25]->val->host->type) ? $ret->returnval->propSet[25]->val->host->type : ''; /* get the host system type */
		$maxCpu	= isSET($ret->returnval->propSet[25]->val->maxCpuUsage) ? $ret->returnval->propSet[25]->val->maxCpuUsage : ''; /* get the maximum cpu usage */
		$maxMem	= isSET($ret->returnval->propSet[25]->val->maxMemoryUsage) ? $ret->returnval->propSet[25]->val->maxMemoryUsage : ''; /* get the maximum memory usage */
		$uptime	= isSET($ret->returnval->propSet[27]->val->quickStats->uptimeSeconds) ? $ret->returnval->propSet[27]->val->quickStats->uptimeSeconds : ''; /* get the uptime in seconds */
		$tmpl	= isSET($ret->returnval->propSet[3]->val->template) ? $ret->returnval->propSet[3]->val->template : ''; /* is this a template? */
		$state	= isSET($ret->returnval->propSet[25]->val->powerState) ? $ret->returnval->propSet[25]->val->powerState : ''; /* is this machine on or off */
		$vmdks	= (isSET($ret->returnval->propSet[15]->val->file)) ? $ret->returnval->propSet[15]->val->file : array(); /* get the vmdk file names */
		$disks	= (isSET($ret->returnval->propSet[12]->val->disk)) ? $ret->returnval->propSet[12]->val->disk : array(); /* get the logical drive letters */
		$hardware=isSET($ret->returnval->propSet[3]->val->hardware->device) ? $ret->returnval->propSet[3]->val->hardware->device : ''; /* get all of the hardware associated with this device */
		
		$data = array(
			"VMName" 		=> $vmName,
			"Name" 			=> $name,
			"Uuid" 			=> $UUID,
			"OSName" 		=> $osName,
			"Memory" 		=> $memory,
			"CPUs" 			=> $cpus,
			"Cores" 		=> $cores,
			"IpAddress" 	=> $ip,
			"Parent" 		=> $parent,
			"Status" 		=> $status,
			"Host" 			=> $host,
			"MaxCpu" 		=> $maxCpu,
			"MaxMem" 		=> $maxMem,
			"Uptime" 		=> $uptime,
			"Template" 		=> $tmpl,
			"State"			=> $state,
			"Vmdks"			=> $vmdks,
			"Disks"			=> $disks,
			"Hardware"		=> $hardware,
		);
		
		return $data;
	}
	
	/* only extract the host data we are interested in */
	private function extractRelevantHostData(&$data) {
		$vmName = isSET($data->returnval->obj->_) ? $data->returnval->obj->_ : ''; /* get the unique virtual machine name */
		$name 	= isSET($data->returnval->propSet[15]->val) ? $data->returnval->propSet[15]->val : ''; /* get actual name of this server */
		$osName = 'ESX'; /* this is a host so just default to ESX */
		$UUID 	= isSET($data->returnval->propSet[13]->val->systemInfo->uuid) ? $data->returnval->propSet[13]->val->systemInfo->uuid : ''; /* the unique name across all vcenters */
		$memory = isSET($data->returnval->propSet[22]->val->hardware->memorySize) ? $data->returnval->propSet[22]->val->hardware->memorySize : 0; /* get the memory this device has */
		$cpus 	= isSET($data->returnval->propSet[13]->val->cpuInfo->numCpuCores) ? $data->returnval->propSet[13]->val->cpuInfo->numCpuCores : ''; /* get the number of cpus this device has (not the cores) */
		$cores 	= isSET($data->returnval->propSet[13]->val->cpuPkg[0]->threadId) ? sizeof($data->returnval->propSet[13]->val->cpuPkg[0]->threadId) : ''; /* get the number of cores per cpu */
		$ip 	= ''; /* get the ip address of this server (TODO: there may be more than one) */
		$parent	= isSET($data->returnval->propSet[4]->val->Event->datacenter->name) ? $data->returnval->propSet[4]->val->Event->datacenter->name : ''; /* get the parent folder name */
		$status	= isSET($data->returnval->propSet[22]->val->overallStatus) ? $data->returnval->propSet[22]->val->overallStatus : ''; /* get the overall status of this device e.g. green, yellow etc */
		$host	= ''; /* get the host system type */
		$maxCpu	= isSET($data->returnval->propSet[22]->val->quickStats->overallCpuUsage) ? $data->returnval->propSet[22]->val->quickStats->overallCpuUsage : ''; /* get the maximum cpu usage */
		$maxMem	= isSET($data->returnval->propSet[22]->val->quickStats->overallMemoryUsage) ? $data->returnval->propSet[22]->val->quickStats->overallMemoryUsage : ''; /* get the maximum memory usage */
		$uptime	= isSET($data->returnval->propSet[22]->val->quickStats->uptime) ? $data->returnval->propSet[22]->val->quickStats->uptime : ''; /* get the uptime in seconds */
		$state	= isSET($data->returnval->propSet[22]->val->runtime->powerState) ? $data->returnval->propSet[22]->val->runtime->powerState : ''; /* is this machine on or off */
		$vendor	= isSET($data->returnval->propSet[13]->val->systemInfo->vendor) ? $data->returnval->propSet[13]->val->systemInfo->vendor : ''; /* get the vendor e.g. HP */
		$hyperv	= isSET($data->returnval->propSet[3]->val->product->fullName) ? $data->returnval->propSet[3]->val->product->fullName : ''; /* get the hypervisor version and build */

		/* build up our return array of data */
		$hostData = array(
			"VMName" 	=> $vmName,
			"Name" 		=> $name,
			"Uuid" 		=> $UUID,
			"OSName" 	=> $osName,
			"Memory" 	=> $memory,
			"CPUs" 		=> $cpus,
			"Cores" 	=> $cores,
			"IpAddress" => $ip,
			"Parent" 	=> $parent,
			"Status" 	=> $status,
			"Host" 		=> $host,
			"MaxCpu" 	=> $maxCpu,
			"MaxMem" 	=> $maxMem,
			"Uptime" 	=> $uptime,
			"State"		=> $state,
			"Vendor"	=> $vendor,
			"Hypervisor"=> $hyperv,
		);
		
		return $hostData;
	}
	
	/* 
		this method returns properties of a MoRefID - this is useful because searching normally needs the display name rather than the ref id but the refid is returned by the initial search 
	*/
	private function getPropertyByMoRefID($id) {
		$soapmsg["_this"] = $this->serviceContent->propertyCollector;
		$soapmsg['specSet']['propSet']['type'] 	= 'VirtualMachine';
		$soapmsg['specSet']['propSet']['all'] 	= 1;
		$soapmsg["specSet"]["objectSet"]['obj'] = $id;
		$ret = $this->client->RetrieveProperties($soapmsg);

		return $ret;
	}

	/* get all properties for an esx host */	
	private function getHostProperties($host) {
		$soap_message["_this"] 		= $this->serviceContent->searchIndex;
		$soap_message["dnsName"] 	= $host;
		$soap_message["vmSearch"] 	= false;
		$dcResult = $this->client->FindByDnsName($soap_message);
		$dcentity = $dcResult->returnval;
	
		$soapmsg["_this"] = $this->serviceContent->propertyCollector;
		$soapmsg['specSet']['propSet']['type'] 	= 'HostSystem';
		$soapmsg['specSet']['propSet']['all'] 	= true;
		$soapmsg["specSet"]["objectSet"]["obj"] = $dcentity;

		$ret = $this->client->RetrieveProperties($soapmsg);
		
		return $ret;
	}

	/* get all devices from an array of ESX hosts */
	public function getAllVmsFromESXHosts() {
		$numberOfEsxHosts 	= sizeof($this->esxHosts);
		$esxHostCount		= 1;
		
		try {			
			foreach ($this->esxHosts as $esxHost) {
				echo "Processing ESX Host: " . $esxHost . " " . $esxHostCount . " of " . $numberOfEsxHosts . "<br/>";
				$properties 	= $this->getHostProperties($esxHost);
				$numberOfVMs 	= sizeof($properties->returnval->propSet[27]->val->ManagedObjectReference);
				$vmCount		= 1;
				/* extract host vm data */
				foreach ($properties->returnval->propSet[27]->val->ManagedObjectReference as $vm) {
					$result 	= $this->getPropertyByMoRefID($vm);
					$data 		= $this->extractRelevantData($result);
					echo "Processing VM: " . $data['Name'] . " " . $vmCount . " of " . $numberOfVMs . "<br/>";

					if ($data['Template'] !== true) { /* don't add templates to our list of servers */
						/* add the host to this vm */
						$data['HostedOn'] = $esxHost;
						/* add our device to the server hierarchy */
						$this->serverData[] = $data;
					}
					
					$vmCount++;
				}
				/* extract host data */
				$this->hostData[] = $this->extractRelevantHostData($properties);
				
				$esxHostCount++;
			}

			return array("hosts" => $this->hostData, "servers" => $this->serverData);
		} catch (Exception $e) { 
			printf("%s\n",$e->__toString());                           
		}
		
		return false;
	}
}