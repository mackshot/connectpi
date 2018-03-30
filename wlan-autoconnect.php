<?php

require __DIR__ . '/config.php';

ob_start();
require 'status.php';
$connected = json_decode(ob_get_clean());

if ($connected[0])
    return;

$wlan = new Wlan(WLAN);
$scanResult = $wlan->Scan();

$wlanDatabase = new WlanDatabase();
$knownNetworks = $wlanDatabase->GetAll();

if (!empty($scanResult) && !empty($knownNetworks))
{
    shuffle($scanResult);
    shuffle($knownNetworks);
    $break = false;
    foreach ($scanResult as $sNetwork)
    {
	if ($break) break;
	foreach ($knownNetworks as $kNetwork)
	{
	    if ($break) break;
	    if ($sNetwork->Ssid == $kNetwork->Ssid && $sNetwork->Mode == $kNetwork->Mode)
	    {
		$wlan->Connect($kNetwork);
		$break = true;
	    }
	}
    }
}
