# connectpi

```apt-get install mc php hostapd dnsmasq bridge-utils```

## determine mac-address of device for external usage
```ifconfig```

## insert the following line into: /etc/udev/rules.d/10-network-device.rules
```SUBSYSTEM=="net", ACTION=="add", ATTR{address}=="24:05:0f:9a:55:75", NAME="wlanE"```

## register bridge
```
brctl addbr br0
brctl addif br0 wlan0
```

## define /etc/network/interfaces
```
auto eth0
iface eth0 inet manual

auto wlan0
iface wlan0 inet manual

auto br0
iface br0 inet static
address 192.168.222.1
netmast 255.255.255.0
broadcast 192.168.222.255
gateway 192.168.222.1
network 192.168.222.0
dns-nameservers 8.8.8.8 8.8.4.4
bridge_ports wlan0 eth0

auto wlanE
allow-hotplug wlanE
iface wlanE inet dhcp

auto usb0
allow-hotplug usb0
iface usb0 inet dhcp
```

## grant sudo rights to apache
```usermod -a -G sudo www-data```

## insert the following line into: /etc/sudoers
```www-data ALL=(ALL) NOPASSWD: ALL```

## insert the following line into: /etc/modules
```r8712u```

https://www.raspberrypi.org/documentation/configuration/wireless/access-point.md

## configure hostapd: /etc/hostapd/hostapd.conf
```
interface=wlan0
bridge=br0
#driver=nl80211
ssid=NetworkName
hw_mode=g
channel=7
wmm_enabled=0
macaddr_acl=0
auth_algs=1
ignore_broadcast_ssid=0
wpa=2
wpa_passphrase=TopSecretPassword
wpa_key_mgmt=WPA-PSK
wpa_pairwise=TKIP
rsn_pairwise=CCMP
```

#/etc/default/hostapd
```DAEMON_CONF="/etc/hostapd/hostapd.conf"```
#/etc/init.d/hostapd
```DAEMON_CONF="/etc/hostapd/hostapd.conf"```

## add the following lines at the end of: /etc/dhcpcd.conf
```
deny interfaces wlan0
deny interfaces eth0

interface wlan0
    static ip_address=192.168.222.1/24
```

## configure dnsmasq: /etc/dnsmasq.conf
```
interface=br0
  dhcp-range=192.168.222.100,192.168.222.200,255.255.255.0,24h
```

## iptables routing
```iptables -t nat -A POSTROUTING -s 192.168.222.0/24 ! -d 192.168.222.0/24  -j MASQUERADE```

```
touch /etc/wpa_supplicant.conf
chmod 777 /etc/wpa_supplicant.conf
```

## enable routing /etc/sysctl.conf
```net.ipv4.ip_forward=1```

## make hostapd to autorun
```
update-rc.d hostapd defaults
update-rc.d hostapd enable
```

## deny apache access to all others /etc/apache2/sites-enabled/000-default.conf
```
<Directory /var/www/html>
    Order Allow,Deny
    Allow from 192.168.222.0/24
</Directory>
```


## TODO:
- WEP
