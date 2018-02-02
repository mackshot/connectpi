<?php

define('WPA_CONFIG', '/etc/wpa_supplicant.conf');

class Network
{
	public $SSID;
	public $Signal;
	public $Freq;
	public $Protocol;
	public $Security;
	public $Speed;
	public $Channel;
	public $Raw;
	public $Mode;

	public function __construct ($ssid, $signal, $freq, $protocol, $security, $speed, $channel, $raw)
	{
		$this->SSID = $ssid;
		$this->Signal = $signal;
		$this->Freq = $freq;
		$this->Protocol = $protocol;
		$this->Security = $security;
		$this->Speed = $speed;
		$this->Channel = $channel;
		$this->Raw = $raw;
		if (strstr($security, "WPA") !== FALSE)
			$this->Mode = "WPA";
		else if (strstr($security, "WEP") !== FALSE)
			$this->Mode = "WEP";
		else
			$this->Mode = "";
	}
}

abstract class ConnectBase
{
	protected $Device;

	protected abstract function InternalDisconnect();

	private static $Instances = array();

	protected static function AddInstance($instance)
	{
		self::$Instances[] = $instance;
	}

	public static function GetDevices()
	{
		$res = sh_exec("ls /sys/class/net", false, false);
		$res = str_replace("lo", "", $res);
		$devices = preg_split("/\s+/", $res, -1, PREG_SPLIT_NO_EMPTY);
		return $devices;
	}

	public static function Status()
	{
		foreach (self::$Instances as $instance)
		{
			if (strstr($instance->Device, "wlan") !== FALSE)
				sh_exec("iwconfig " . $instance->Device);
			sh_exec("ifconfig " . $instance->Device);
		}
		sh_exec("route");
		sh_exec("traceroute google.de");
		sh_exec("ping google.de -c 5");

	}

	protected function Disconnect()
	{
		sh_exec("ip route delete default");
		foreach (ConnectBase::$Instances as $instance)
			$instance->InternalDisconnect();
	}

	protected function ConfigureAndCheckConnection()
	{
		sh_exec("dhclient " . $this->Device);
		$ipNeigh = sh_exec("ip neigh | grep " . $this->Device);
		if (preg_match("/([\d|\.]+)\s+dev\s+" . $this->Device . "/", $ipNeigh, $matches) == false)
		{
			error("No Gateway found!");
			return;
		}
		$gateway = $matches[1];
		sh_exec("ip route add default via " . $gateway);

		sh_exec("route");
		sh_exec("traceroute google.de");
		sh_exec("ping google.de -c 5");
	}
}

class Wlan extends ConnectBase
{
	public function __construct($device)
	{
		$this->Device = $device;
		ConnectBase::AddInstance($this);
	}

	protected function InternalDisconnect()
	{
		sh_exec('kill $(pgrep -f "wpa_supplicant")');
		sh_exec("rm -r /var/run/wpa_supplicant/*");
	}

	public function Scan()
	{
		$data = shell_exec("sudo iwlist " . $this->Device . " scan");
		$data = explode("Scan completed :", $data);
		if (count($data) < 2)
			return;
		$data = $data[1];

		$networks = preg_split("/\s+Cell\s/", $data, -1, PREG_SPLIT_DELIM_CAPTURE);
		if (empty($networks))
			return;
		array_shift($networks);
		if (count($networks) == 0)
			return array();

		$networkData = array();
		foreach ($networks as $network)
		{
			$network = "Cell " . $network;
			preg_match("/\s+ESSID:\"(.+)\"\n/", $network, $matches);
			$ssid = $matches[1];
			$ssid = str_replace('\x00', '', $ssid);
			preg_match("/\s+Signal level=(\d+)\/(\d+)/", $network, $matches);
			$signal = (($matches[1]/$matches[2])*100) . "%";
			preg_match("/\s+Protocol:IEEE (.*)\n/", $network, $matches);
			$protocol = $matches[1];
			preg_match("/\s+Frequency:(\d+.\d+ GHz) \(Channel (\d+)\)\n/", $network, $matches);
			$freq = $matches[1];
			$channel = $matches[2];
			$security = array();
			if (preg_match("/\s+IE: WPA Version\s/", $network))
				$security[] = "WPA";
			if (preg_match("/\s+IE: IEEE 802.11i\/WPA2\s/", $network))
				$security[] = "WPA2";
			$allLines = str_replace("\n", " ", $network);
			$substr = substr($allLines, strpos($allLines, "Bit Rates:"));
			$substr = substr($substr, 0, strpos($substr, "Extra:"));
			preg_match_all("/(\d+(.\d+)?) Mb\/s/", $substr, $matches);
			$speed = $matches[1];
			sort($speed);
			$networkData[] = new Network($ssid, $signal, $freq, $protocol, implode(', ', $security), implode(', ', $speed), $channel, $network);
		}
		return $networkData;
	}

	public function Connect($ssid, $passphrase, $mode)
	{
		$this->Disconnect();

		switch ($mode)
		{
			case "WPA":
				sh_exec("wpa_passphrase \"" . $ssid . "\" \"" . $passphrase . "\" > " . WPA_CONFIG); 
				sh_exec("wpa_supplicant -i".$this->Device." -c". WPA_CONFIG . " -Dwext -B");
				break;
			case "WEP":
				$data = "network={\n\tssid=\"".$ssid."\"\n\tkey_mgmt=NONE\n\twep_key0=\"".$passphrase."\"\n\twep_tx_keyidx=0\n}\n";
				file_put_contents(WPA_CONFIG, $data);
				sh_exec("wpa_supplicant -i".$this->Device." -c". WPA_CONFIG . " -Dwext -B");
				break;
			default:
				$data = "network={\n\tssid=\"".$ssid."\"\n\tkey_mgmt=NONE\n\tpriority=100\n}";
				file_put_contents(WPA_CONFIG, $data);
				sh_exec("wpa_supplicant -i".$this->Device." -c". WPA_CONFIG . " -Dwext -B");
				break;
		}

		sleep(5);

		$count = 1;
		while (true)
		{
			sleep(2);
			print "Check connection #" . $count . "\n";
			$iwConfig = sh_exec("iwconfig " . $this->Device . " 2>&1", false);
			if (strpos($iwConfig, 'ESSID:"' . $ssid . '"') > 0)
				break;

			$count++;
			if ($count > 10)
			{
				error("Not connected to '" . $ssid . "'!");
				return;
			}
		}

		$this->ConfigureAndCheckConnection();

	}
}

class UsbTethering extends ConnectBase
{

	public function __construct($device)
	{
		$this->Device = $device;
		ConnectBase::AddInstance($this);
	}

	protected function InternalDisconnect()
	{
	}

	public function Connect()
	{
		$this->Disconnect();

		$this->ConfigureAndCheckConnection();
	}

}

function error($text)
{
	print "error: " . $text . "\n";
}

function sh_exec($command, $printOutput = true, $printCommand = true)
{
	if ($printCommand)
		print "" . $command . "\n";
	$response = shell_exec("sudo " . $command);
	if ($printOutput && strlen($response) > 0)
	{
		$parts = explode("\n", $response);
		foreach ($parts as $part)
		{
			print "  > " . $part . "\n";
		}
	}
	return $response;
}
