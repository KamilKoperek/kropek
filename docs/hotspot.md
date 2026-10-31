# Wi-Fi hotspot (USB RT5370)

A USB Wi-Fi dongle on the box is a WPA2 access point, so a phone/laptop can connect directly and open the
PHP server without the LAN.

```
client ──Wi-Fi "kropek"──> wlx1cbfcefc3015 10.42.0.1/24 ──> php -S 0.0.0.0:80  (http://10.42.0.1/)
           hostapd (WPA2)      dnsmasq: DHCP .10–.200, DNS "kropek" → 10.42.0.1
```

| Piece | Where |
|-------|-------|
| Adapter | Ralink RT5370 `148f:5370`, driver `rt2800usb`, firmware `rt2870.bin` from `firmware-misc-nonfree` |
| Interface | `wlx1cbfcefc3015` (name comes from the MAC — another dongle gets another name) |
| Static IP | `/etc/network/interfaces.d/kropek-ap` (ifupdown, `allow-hotplug`) |
| AP | `/etc/hostapd/hostapd.conf` (mode 600, holds the password), `hostapd.service` unmasked + enabled; 2.4 GHz ch 6, 802.11n, `country_code=PL` |
| DHCP/DNS | `/etc/dnsmasq.d/kropek-ap.conf`, `dnsmasq.service`; bound only to the AP interface (`bind-dynamic`, not `lo`/`enp1s0`) |
| Setup | `~/kiosk-ap-setup.sh` (root, idempotent; `SSID=` / `PASS=` override, otherwise keeps or generates the password and prints it) |

```sh
su -c 'sh /home/kropek/kiosk-ap-setup.sh'                 # first setup / re-apply
su -c 'PASS=newpassword sh /home/kropek/kiosk-ap-setup.sh' # change password
su -c 'grep ^wpa_passphrase /etc/hostapd/hostapd.conf'    # show password
systemctl status hostapd dnsmasq
```

## Notes

- No NAT/forwarding: clients reach only the box, not the LAN or internet. Phones say "no internet" — choose
  to stay connected. Everything served over HTTP (including the open deploy button, see [deploy.md](deploy.md))
  is reachable by anyone with the Wi-Fi password.
- Wrong country/no country → hostapd may refuse the channel; change `country_code` in the script.
