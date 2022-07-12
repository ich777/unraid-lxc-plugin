function status(){
echo -n "$(cat /boot/config/plugins/lxc/plugin.cfg | grep -n "SERVICE" | cut -d '=' -f2 | sed 's/\"//g')"
}

function default_path(){
echo -n "$(cat /boot/config/plugins/lxc/lxc.conf | grep -n "lxc.lxcpath" | cut -d '=' -f2 | sed 's/\"//g')"
}

function avail_bridges(){
echo -n "$(brctl show|grep -Po '^(vir)?br\d\S*')"
}

function selected_bridge(){
echo -n "$(cat /boot/config/plugins/lxc/default.conf | grep -n "lxc.net.0.link" | cut -d '=' -f2 | sed 's/\"//g' | sed 's/ //g')"
}

function selected_timeout(){
echo -n "$(cat /boot/config/plugins/lxc/plugin.cfg | grep -n "TIMEOUT" | cut -d '=' -f2 | sed 's/\"//g')"
}

function change_config(){
SERVICE_STATUS="$(cat /boot/config/plugins/lxc/plugin.cfg | grep -n "SERVICE" | cut -d '=' -f2 | sed 's/\"//g')"
sed -i "/SERVICE=/c\SERVICE=${1}" "/boot/config/plugins/lxc/plugin.cfg"
sed -i "/TIMEOUT=/c\TIMEOUT=${4}" "/boot/config/plugins/lxc/plugin.cfg"
sed -i "/lxc.lxcpath=/c\lxc.lxcpath=${2%/*}" "/boot/config/plugins/lxc/lxc.conf"
sed -i "/lxc.net.0.link =/c\lxc.net.0.link = ${3}" "/boot/config/plugins/lxc/default.conf"
if [ "${1}" == "disabled" ]; then
  CONTAINERS="$(lxc-ls --active)"
  TIMEOUT="$(cat /boot/config/plugins/lxc/plugin.cfg | grep -n "TIMEOUT" | cut -d '=' -f2 | sed 's/\"//g')"
  if [ -z "${TIMEOUT}" ]; then
    TIMEOUT=15
  fi
  for container in $CONTAINERS; do
    logger "LXC: Stopping container '$container'"
    lxc-stop --timeout=${TIMEOUT} $container 2>/dev/null
    logger "LXC: Container '$container' stopped"
  done
fi
if [ -d /var/cache/lxc ]; then
  rm -rf /var/cache/lxc
fi
if [ ! -f /etc/lxc/default.conf ]; then
  ln -s /boot/config/plugins/lxc/default.conf /etc/lxc/default.conf
fi
if [ ! -f /etc/lxc/lxc.conf ]; then
  ln -s /boot/config/plugins/lxc/lxc.conf /etc/lxc/lxc.conf
fi
if [ ! -z "${5}" ]; then
  if [ "${SERVICE_STATUS}" != "enabled" ]; then
    lxc-autostart
  fi
  if [ ! -d ${2%/*} ]; then
    mkdir -p ${2%/*}/cache
  fi
  ln -s ${2%/*}/cache /var/cache/lxc
fi
}

function get_autostart(){
echo -n "$(grep "lxc.start.auto" $1/$2/config 2>/dev/null | cut -d '=' -f2 | sed 's/ //g')"
}

function enable_autostart(){
logger "LXC: Enabled Autostart from container '$2'"
if [ ! "$(grep "lxc.start.auto" $1/$2/config 2>/dev/null | cut -d '=' -f2 | sed 's/ //g')" ]; then
echo "
# Autostart Settings
lxc.start.auto = 1
lxc.start.delay = 0" >> $1/$2/config
else
sed -i "/lxc.start.auto/c\lxc.start.auto = 1" $1/$2/config
fi
}

function disable_autostart(){
logger "LXC: Disabled Autostart from container '$2'"
if [ ! "$(grep "lxc.start.auto" $1/$2/config 2>/dev/null | cut -d '=' -f2 | sed 's/ //g')" ]; then
echo "
# Autostart Settings
lxc.start.auto = 0
lxc.start.delay = 0" >> $1/$2/config
else
sed -i "/lxc.start.auto/c\lxc.start.auto = 0" $1/$2/config
fi
}

function get_CPUs(){
echo -n "$(cat /proc/$1/status | grep "Cpus_allowed_list" | awk '{print $2}')"
}

function get_IPs(){
echo -n "$(lxc-info $1 -iH | sed ':a;N;$!ba;s/\n/<\/br>/g')"
}

function get_distribution(){
echo -n "$(grep -oP '(?<=dist )\w+' $1/$2/config | head -1 | sed 's/\"//g')"
}

function start_Container(){
logger "LXC: Starting container '$1'"
lxc-start $1
logger "LXC: Container '$1' started"
}

function stop_Container(){
logger "LXC: Stopping container '$1'"
lxc-stop --timeout=${2} $1
logger "LXC: Container '$1' stopped"
}

function freeze_Container(){
logger "LXC: Freezing container '$1'"
lxc-freeze $1
logger "LXC: Container '$1' frozen"
}

function unfreeze_Container(){
logger "LXC: Unfreezing container '$1'"
lxc-unfreeze $1
logger "LXC: Container '$1' unfrozen"
}

function kill_Container(){
logger "LXC: Killing container '$1'"
lxc-stop --kill $1
logger "LXC: Container '$1' killed"
}

function destroy_Container(){
logger "LXC: Destroying container '$1'"
lxc-stop --kill $1
sleep 0.5
umount $2/$1/rootfs
SNAPSHOTS="$(lxc-snapshot -L $1 | awk {'print $1'})"
for snapshot in $SNAPSHOTS; do
  umount $2/$1/snaps/$snapshot/rootfs
done
lxc-destroy -s $1
logger "LXC: Container '$1' destroyed"
}

function get_snapshot(){
SNAPSHOTS="$(lxc-snapshot -L $1)"
for snapshot in "$SNAPSHOTS"; do
  echo "$snapshot"
done
}

function delete_snapshot(){
logger "LXC: Deleting Snapshot '$1' from container '$2'"
umount $3/$2/snaps/$1/rootfs
lxc-snapshot -d $1 $2
logger "LXC: Snapshot '$1' from container '$2' deleted"
}

$@
