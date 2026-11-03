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

## Client mode: box uses the phone's hotspot for internet

The same dongle can instead join a phone hotspot, so the box has internet (deploys fetch from GitHub) without
the LAN. Same script, `MODE=client`; it installs `wpasupplicant`, stops/disables `hostapd` + `dnsmasq` and
rewrites `/etc/network/interfaces.d/kropek-ap` (mode 600, holds the hotspot password) to `inet dhcp` with
`wpa-ssid`/`wpa-psk`. Running the script without `MODE` switches back to the AP.

```sh
su -c 'MODE=client SSID=PhoneHotspot PASS=hotspotpassword sh /home/kropek/kiosk-ap-setup.sh'  # join phone
su -c 'sh /home/kropek/kiosk-ap-setup.sh'                                                    # back to AP
```

- The phone hotspot must be **2.4 GHz** (RT5370 has no 5 GHz) and WPA2 (or WPA2/WPA3 mixed).
- The box's IP is assigned by the phone and printed by the script. The kiosk also shows it on screen for
  30 s after connecting (`net.php` + overlay in `kropek.html`, see [page.md](page.md)), and it's in the
  phone's hotspot "connected devices" list. Open `http://<that IP>/deploy.php` from the phone.
- The first switch needs internet for `apt-get` (do it while the LAN cable is in).
- With the LAN also up, the wired default route has the lower metric and wins.

## Notes

- No NAT/forwarding: clients reach only the box, not the LAN or internet. Phones say "no internet" — choose
  to stay connected. Everything served over HTTP (including the open deploy button, see [deploy.md](deploy.md))
  is reachable by anyone with the Wi-Fi password.
- Wrong country/no country → hostapd may refuse the channel; change `country_code` in the script.
