#!/bin/bash
AUTOSTART_DELAY="$(cat /boot/config/plugins/lxc/plugin.cfg | grep -n "^AUTOSTART_DELAY=" | cut -d '=' -f2 | sed 's/\"//g')"

logger "LXC: Waiting ${AUTOSTART_DELAY}s for autostart from container(s) in background"
sleep ${AUTOSTART_DELAY:=10}s 2>/dev/null

if [ "$(cat /boot/config/plugins/lxc/plugin.cfg | grep -n "^SERVICE=" | cut -d '=' -f2 | sed 's/\"//g')" == "enabled" ]; then
  logger "LXC: Executing autostart from container(s)"
  lxc-autostart
fi
