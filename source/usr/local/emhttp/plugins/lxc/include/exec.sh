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

function change_config(){
sed -i "/SERVICE=/c\SERVICE=${1}" "/boot/config/plugins/lxc/plugin.cfg"
sed -i "/lxc.lxcpath=/c\lxc.lxcpath=${2%/*}" "/boot/config/plugins/lxc/lxc.conf"
sed -i "/lxc.net.0.link =/c\lxc.net.0.link = ${3}" "/boot/config/plugins/lxc/default.conf"
if [ "${1}" == "disabled" ]; then
  CONTAINERS="$(lxc-ls --active)"
  TIMEOUT="$(cat /boot/config/plugins/lxc/plugin.cfg | grep -n "TIMEOUT" | cut -d '=' -f2 | sed 's/\"//g')"
  if [ -z "${TIMEOUT}" ]; then
    TIMEOUT=15
  fi
  for container in $CONTAINERS; do
    logger "LXC: Stopping Container: $container..."
    lxc-stop --timeout=${TIMEOUT} $container 2>/dev/null
    logger "LXC: Container $container successful stopped!"
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
if [ ! -z "${4}" ]; then
  lxc-autostart
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
if [ ! "$(grep "lxc.start.auto" $1/$2/config 2>/dev/null | cut -d '=' -f2 | sed 's/ //g')" ]; then
echo "
# Autostart Settings
lxc.start.auto = 1
lxc.start.delay = 0" > $1/$2/config
else
sed -i "/lxc.start.auto/c\lxc.start.auto = 1" $1/$2/config
fi
}

function disable_autostart(){
if [ ! "$(grep "lxc.start.auto" $1/$2/config 2>/dev/null | cut -d '=' -f2 | sed 's/ //g')" ]; then
echo "
# Autostart Settings
lxc.start.auto = 0
lxc.start.delay = 0" > $1/$2/config
else
sed -i "/lxc.start.auto/c\lxc.start.auto = 0" $1/$2/config
fi
}

function get_CPUs(){
echo -n "$(cat /proc/$1/status | grep "Cpus_allowed_list" | awk '{print $2}')"
}

function start_Container(){
echo -n "$(lxc-start $1)"
}

function stopp_Container(){
echo -n "$(lxc-stop $1)"
}

function freeze_Container(){
echo -n "$(lxc-freeze $1)"
}

function unfreeze_Container(){
echo -n "$(lxc-unfreeze $1)"
}

function kill_Container(){
echo -n "$(lxc-stopp -kill $1)"
}

$@
