<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

    require 'connect.php';
    $wlan = new Wlan('wlanE');

    $devices = ConnectBase::GetDevices();
    $usbOn = in_array('usb0', $devices);
    if ($usbOn)
	$usb = new UsbTethering('usb0');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>connectpi</title>
    <script src="jquery-3.3.1.min.js"></script>
    <style>
	body {
	    margin: 0; padding: 0;
	    background-color: #666;
	    font-family: sans-serif;
	}
	table tr td {
	    padding: 5px;
	}
	th {
	    background-color: #999;
	}
	tr.even {
	    background-color: #ddd;
	}
	tr.odd {
	    background-color: #bbb;
	}
	textarea.console {
	    background-color: #000;
	    font-family: monospace;
	    width: 100%;
	    box-sizing: border-box;
	    color: #fff;
	}
	div.menu, div.status, div.content, div.system {
	    padding: 10px 10px 0 10px;
	}
	div.menu, div.status {
	    color: #fff;
	}
	div.status {
	    text-weight: bold;
	}
	table {
	    background-color: #fff;
	}
	span.devices {
	    font-size: 90%;
	}
	.text-right {
	    text-align: right;
	}
    </style>
</head>

<body>

    <div class="status">Checking connection ...</div>
    <div class="menu">
	<div>
	    <button onclick="window.location.href = '?action=status'">Status</button>
	    <button onclick="window.location.href = '?action=wlan'">WLAN Search</button>
	    <?php if ($usbOn) { ?>
	    <button onclick="window.location.href = '?action=usb-connect'">USB Connect</button>
	    <?php } ?>
	    <span class="devices">Devices: <?php print implode(", ", $devices); ?></span>
	</div>
    </div>
    <div class="content">
	<?php
	    $action = '';
	    if (isset($_GET['action']))
		$action = $_GET['action'];

	    switch ($action)
	    {
		default:
		case 'status':
		    ?><textarea class="console" readonly="readonly"><?php
		    print $wlan->Status();
		    ?></textarea><?php
		    break;
		case 'wlan':
		    $scanResult = $wlan->Scan();
		    if (empty($scanResult))
		    {
			print "No Networks found!";
		    }
		    else
		    {
			?>
			<table>
			    <tr>
				<th>SSID</th>
				<th>Signal</th>
				<th>Channel (Freq)</th>
				<th>Security</th>
				<th>Protocol</th>
				<th>Speed (Mb/s)</th>
				<th>Connect</th>
			    </tr>
			<?php
			$c = 0;
			
			usort($scanResult, function($a, $b) { return ($a->SignalRaw < $b->SignalRaw); });

			foreach ($scanResult as $network)
			{
			    ?>
			    <tr class="<?php if ($c % 2 == 0) print 'even'; else print 'odd'; ?>">
				<td><?php print $network->SSID; ?></td>
				<td class="text-right"><?php print $network->Signal; ?></td>
				<td><?php print $network->Channel; ?> (<?php print $network->Freq; ?>)</td>
				<td><?php print $network->Security ?></td>
				<td><?php print $network->Protocol ?></td>
				<td><?php print $network->Speed; ?></td>
				<td>
				    <input type="hidden" class="ssid" value="<?php print $network->SSID; ?>"/>
				    <input type="hidden" class="mode" value="<?php print $network->Mode; ?>"/>
				    <?php
				    if ($network->Mode == "WPA" || $network->Mode == "WEP")
				    {
					?>
					<input type="text" class="passphrase" />
					<?php
				    }
				    ?>
				    <button class="connect">connect</button>
				</td>
			    </tr>
			    <?php 
			    $c++;
			}
			?>
			</table>
			<?php
		    }
		    break;
		case 'wlan-connect':
		    ?><textarea class="console" readonly="readonly"><?php
		    print $wlan->Connect(base64_decode($_GET['ssid']), base64_decode($_GET['passphrase']), $_GET['mode']);
		    ?></textarea><?php
		    break;
		case 'usb-connect':
		    ?><textarea class="console" readonly="readonly"><?php
		    print $usb->Connect();
		    ?></textarea><?php
		    break;
	    }
	?>
    </div>
    <div class="system text-right">
	    <button onclick="system('reboot')">Reboot</button>
	    <button onclick="system('shutdown')">Shutdown</button>
    </div>

    <script>
	$('button.connect').on('click', function() {
	    var ssid = $(this).closest('tr').find('td input.ssid').val();
	    var mode = $(this).closest('tr').find('td input.mode').val();
	    var passphrase = $(this).closest('tr').find('td input.passphrase').val();
	    var url = '?action=wlan-connect&ssid=' + window.btoa(ssid) + '&mode=' + mode + '&passphrase=' + window.btoa(passphrase);
	    window.location.href = url;
	});

	$('textarea.console').each(function() {
	    var offset = this.offsetHeight - this.clientHeight;
	    var resizeTextarea = function(el) {
		$(el).css('height', 'auto').css('height', el.scrollHeight + offset);
	    };
	    resizeTextarea(this);
	});

	var $status;
	var $body;

	var updateStatus = function () {
	    $.ajax({
		url: "status.php",
		dataType: "json",
		success: function(data) {
		    if (data[0]) {
			$body.css('background-color', 'darkgreen');
		    } else {
			$body.css('background-color', 'darkred');
		    }
		    $status.text(data[1]);
		},
		error: function() {
		    $status.text("No connection to connectpi");
		    $body.css('background-color', 'darkviolet');
		}
	    });
	}

	var system = function(mode) {
	    alert('Perform ' + mode);
	    $.ajax({
		url: "system.php",
		data: { mode: mode },
		method: "POST",
	    });
	}

	$(function() {
		$status = $(".status");
		$body = $("body");
		updateStatus();
		setInterval(updateStatus, 10000);
	});
    </script>

</body>

</html> 