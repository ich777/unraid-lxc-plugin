#!/bin/bash

# Create contaifrom snapshot or copy container from existing container or create container from template
if [ "$7" == "snapshot" ]; then
  logger "LXC: Creating new container '$3' from container '$8' snapshot '$9'"
  echo "Creating container, please wait until the DONE button is displayed!"
  echo
  # Create container from snapshot, exit if failed
  lxc-snapshot -n $8 -r $9 $3 || echo "Something went wrong!"
  logger "LXC: Container '$3' created"
elif [ "$7" == "copy" ]; then
  logger "LXC: Creating new container '$3' from existing container '$8'"
  echo "Copying container, please wait until the DONE button is displayed!"
  echo
  # Create container from existing, exit if failed
  CONT_STATUS="$(lxc-info --name $8 | grep State: | cut -d ':' -f2 | sed -e 's/^[ \t]*//')"
  lxc-stop --name $8
  lxc-copy -n $8 -N $3 || echo "Something went wrong!"
  if [ "${CONT_STATUS}" == "RUNNING" ]; then
    lxc-start --name $8
  fi
  logger "LXC: Container '$3' created"
else
  logger "LXC: Creating new container '$3'"
  echo "Creating container, please wait until the DONE button is displayed!"
  echo
  # Create the container, exit if failed
  lxc-create --name $3 --template download -- --dist $4 --release $5 --arch amd64 || echo "Something went wrong!"
  logger "LXC: Container '$3' created"
fi

# Inject the random generated MAC address in the config file
if [ ! "$(grep "lxc.net.0.hwaddr" $1/$3/config 2>/dev/null | cut -d '=' -f2 | sed 's/ //g')" ]; then
  echo "lxc.net.0.hwaddr = $2" >> $1/$3/config
else
  sed -i "/lxc.net.0.hwaddr/c\lxc.net.0.hwaddr = $2" $1/$3/config
fi

# Inject the Autostart settings in the config file
if [ "$6" == "true" ]; then
  echo
  echo "Autostart Enabled!"
  if [ ! "$(grep "lxc.start.auto" $1/$3/config 2>/dev/null | cut -d '=' -f2 | sed 's/ //g')" ]; then
    echo "
# Autostart Settings
lxc.start.auto = 1
lxc.start.delay = 0" >> $1/$3/config
  else
    sed -i "/lxc.start.auto/c\lxc.start.auto = 1" $1/$3/config
  fi
else
  if [ ! "$(grep "lxc.start.auto" $1/$3/config 2>/dev/null | cut -d '=' -f2 | sed 's/ //g')" ]; then
    echo "
# Autostart Settings
lxc.start.auto = 0
lxc.start.delay = 0" >> $1/$3/config
  else
    sed -i "/lxc.start.auto/c\lxc.start.auto = 0" $1/$3/config
  fi
fi

echo
echo "To connect to the container, start the container first, open up a Unraid terminal and type in:"
echo "'lxc-attach $3' (without quotes)."
echo
echo "It is recommended to attach to the corresponding shell by typing in for example:"
echo "'lxc-attach $3 /bin/bash'"
