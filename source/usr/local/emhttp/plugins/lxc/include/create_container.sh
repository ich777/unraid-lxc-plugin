#!/bin/bash


echo $1 $2 $3 $4 $5 $6

## Create the container, exit if failed
#lxc-create --name $3 --template download -- --dist $4 --release $5 --arch amd64 || exit 1

## Inject the random generated MAC address in the config file
#if [ ! "$(grep "lxc.net.0.hwaddr" $1/$3/config 2>/dev/null | cut -d '=' -f2 | sed 's/ //g')" ]; then
#  echo "lxc.net.0.hwaddr = $2" >> $1/$3/config
#else
#  sed -i "/lxc.net.0.hwaddr/c\lxc.net.0.hwaddr = $2" $1/$3/config
#fi

## Inject the Autostart settings in the config file
#if [ "$5" == "on" ]; then
#  if [ ! "$(grep "lxc.start.auto" $1/$3/config 2>/dev/null | cut -d '=' -f2 | sed 's/ //g')" ]; then
#    echo "
## Autostart Settings
#lxc.start.auto = 1
#lxc.start.delay = 0" >> $1/$3/config
#  else
#    sed -i "/lxc.start.auto/c\lxc.start.auto = 1" $1/$3/config
#  fi
#else
#  if [ ! "$(grep "lxc.start.auto" $1/$3/config 2>/dev/null | cut -d '=' -f2 | sed 's/ //g')" ]; then
#    echo "
## Autostart Settings
#lxc.start.auto = 0
#lxc.start.delay = 0" >> $1/$3/config
#  else
#    sed -i "/lxc.start.auto/c\lxc.start.auto = 0" $1/$3/config
#  fi
#fi
