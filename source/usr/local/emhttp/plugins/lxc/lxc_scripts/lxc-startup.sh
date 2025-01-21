#!/bin/bash
AUTOSTART_DELAY="$(cat /boot/config/plugins/lxc/plugin.cfg | grep -n "^AUTOSTART_DELAY=" | cut -d '=' -f2 | sed 's/\"//g')"

logger "LXC: Waiting ${AUTOSTART_DELAY}s for autostart from container(s) in background"
sleep ${AUTOSTART_DELAY:=10}s 2>/dev/null

logger "LXC: Executing autostart from container(s)"
if lxc-check 2>/dev/null ; then
  lxc-autostart
fi
