#!/bin/bash
echo "Setting up Debian Bullseye LXC container, please wait...!"
echo

# Create the container, exit if failed
lxc-create --name $2 --template download -- --dist debian --release bullseye --arch amd64 &> /dev/null || echo "Something went wrong!"

# Inject the random generated MAC address in the config file
echo "lxc.net.0.hwaddr = $(printf '52:54:00:%02X:%02X:%02X\n' $[RANDOM%256] $[RANDOM%256] $[RANDOM%256])" >> $1/$2/config

# Inject the Autostart settings in the config file
echo "
# Autostart Settings
lxc.start.auto = 0
lxc.start.delay = 0" >> $1/$2/config

echo
echo "+----------------------------------------------------------------------------"
echo "| Starting installation from VNC and Desktop environment shortly!"
echo "| Please wait and don't close this window until the DONE button is displayed!"
echo "|"
echo "| This can take some time depending on your internet connection and"
echo "| performance from your server!"
echo "+----------------------------------------------------------------------------"

lxc-start --name $2
sleep 10

lxc-attach --name $2 -- bash -c "echo
echo '+-------------------------------------------'
echo '| Updating repositories...'
echo '+-------------------------------------------'
echo 'Please wait...!'
#Set repositories
echo 'deb http://deb.debian.org/debian bullseye contrib non-free' >> /etc/apt/sources.list

#Update and upgrade base packages
apt-get update &> /dev/null
apt-get -y upgrade &> /dev/null
echo 'Done'

#Set root password add user debian and set root password
echo
echo '+-------------------------------------------'
echo '| Setting passwords...'
echo '+-------------------------------------------'
echo 'Please wait...!'
echo -e 'Unraid\nUnraid' | passwd root

#Add user debian
useradd -rm debian -s /bin/bash

#Set password for user \"debian\"
echo -e 'debian\ndebian' | passwd debian

echo 'Done'

#Install basic dependencies
echo
echo '+-------------------------------------------'
echo '| Installing basic dependencies and noVNC...'
echo '+-------------------------------------------'
echo 'Please wait...!'
apt-get -y install wget curl procps &> /dev/null
#Install noVNC
cd /tmp && \
  wget -qO /tmp/novnc.tar.gz https://github.com/novnc/noVNC/archive/v1.2.0.tar.gz && \
  tar -xf /tmp/novnc.tar.gz && \
  cd /tmp/noVNC* && \
  sed -i 's/credentials: { password: password } });/credentials: { password: password },\n                           wsProtocols: ["'"binary"'"] });/g' app/ui.js && \
  mkdir -p /usr/share/novnc && \
  cp -r app /usr/share/novnc/ && \
  cp -r core /usr/share/novnc/ && \
  cp -r utils /usr/share/novnc/ && \
  cp -r vendor /usr/share/novnc/ && \
  cp -r vnc.html /usr/share/novnc/ && \
  cp package.json /usr/share/novnc/ && \
  cd /usr/share/novnc/ && \
  chmod -R 755 /usr/share/novnc && \
  rm -rf /tmp/noVNC* /tmp/novnc.tar.gz
echo 'Done'

#Install window manager and dependencies
echo
echo '+-------------------------------------------'
echo '| Installing window manager & TurboVNC...'
echo '+-------------------------------------------'
echo 'Please wait...!'
apt-get -y install --no-install-recommends xvfb wmctrl x11vnc websockify fluxbox screen libxcomposite-dev libxcursor1 xauth &> /dev/null && \
  sed -i '/    document.title =/c\    document.title = \"DebianBullseye - noVNC\";' /usr/share/novnc/app/ui.js

#Install TurboVNC
cd /tmp && \
  wget -qO /tmp/turbovnc.deb https://sourceforge.net/projects/turbovnc/files/2.2.6/turbovnc_2.2.6_amd64.deb/download && \
  dpkg -i /tmp/turbovnc.deb &> /dev/null && \
  rm -rf /opt/TurboVNC/java /opt/TurboVNC/README.txt && \
  cp -R /opt/TurboVNC/bin/* /bin/ && \
  rm -rf /opt/TurboVNC /tmp/turbovnc.deb && \
  sed -i '/# $enableHTTP = 1;/c\$enableHTTP = 0;' /etc/turbovncserver.conf
echo 'Done'

#Install base system for container
echo
echo '+-------------------------------------------'
echo '| Installing basic Desktop environment, this'
echo '| can take a long time...!'
echo '+-------------------------------------------'
echo 'Please wait...!'
export TZ=Europe/Rome && \
  apt-get update &> /dev/null && \
  ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && \
  echo $TZ > /etc/timezone && \
  DEBIAN_FRONTEND=noninteractive apt-get -qq -y install --no-install-recommends man-db hdparm udev whiptail reportbug init \
  vim-common iproute2 nano gdbm-l10n less iputils-ping netcat-traditional perl bzip2 gettext-base manpages file liblockfile-bin \
  python3-reportbug libnss-systemd isc-dhcp-common systemd-sysv xz-utils perl-modules debian-faq wamerican bsdmainutils systemd \
  cpio logrotate traceroute dbus kmod isc-dhcp-client telnet krb5-locales lsof debconf-i18n cron ncurses-term iptables ifupdown \
  procps rsyslog apt-utils netbase pciutils bash-completion vim-tiny groff-base apt-listchanges bind9-host doc-debian libpam-systemd \
  openssh-client xfce4 xorg dbus-x11 sudo gvfs-backends gvfs-common gvfs-fuse gvfs firefox-esr at-spi2-core gpg-agent mousepad \
  xarchiver sylpheed unzip gtk2-engines-pixbuf gnome-themes-standard lxtask xfce4-terminal p7zip unrar curl \
  xfce4-screenshooter binutils gedit zip xfce4-taskmanager fonts-vlgothic ffmpeg openssh-server &> /dev/null
echo 'Done'


#Create directories and set permissions
echo
echo '+-------------------------------------------'
echo '| Fixing permissions...'
echo '+-------------------------------------------'
echo 'Please wait...!'
if [ ! -d /tmp/xdg ]; then
  mkdir /tmp/xdg
fi
rm -R /home/debian/.dbus/session-bus/* 2> /dev/null
if [ ! -d /var/run/dbus ]; then
  mkdir -p /var/run/dbus
fi
USER_UID=\"$(cat /etc/passwd | grep \"debian\" | cut -d ':' -f3)\"
USER_GID=\"$(cat /etc/passwd | grep \"debian\" | cut -d ':' -f4)\"
chown -R ${USER_UID}:${USER_GID} /var/run/dbus/
chmod -R 770 /var/run/dbus/
chown -R ${USER_UID}:${USER_GID} /tmp/xdg
chmod -R 0700 /tmp/xdg
dbus-uuidgen > /var/lib/dbus/machine-id
rm -R /tmp/.* 2> /dev/null
mkdir -p /tmp/.ICE-unix
chown root:root /tmp/.ICE-unix/
chmod 1777 /tmp/.ICE-unix/
chown -R ${USER_UID}:${USER_GID} /home/debian
echo 'Done'

echo
echo '+-------------------------------------------'
echo '| Create & start services, please wait...'
echo '+-------------------------------------------'
echo
#Create systemd services
echo '[Unit]
Description=VNC Server
After=network-online.target
[Service]
Type=simple
User=%i
ExecStart=Xvnc -geometry 1280x1024 -depth 16 :99 -rfbport 5900 -securitytypes none
[Install]
WantedBy=multi-user.target' > /etc/systemd/system/vncserver@debian.service
echo '[Unit]
Description=VNC Server
After=vncserver@debian.service
[Service]
Type=simple
User=%i
ExecStart=websockify --web=/usr/share/novnc/ --cert=/etc/ssl/novnc.pem 8080 localhost:5900
[Install]
WantedBy=multi-user.target' > /etc/systemd/system/novnc@debian.service
echo '[Unit]
Description=XFCE4 session
After=novnc@debian.service
[Service]
Type=simple
User=%i
Environment=\"XDG_RUNTIME_DIR=/tmp/xdg\"
Environment=\"DISPLAY=:99\"
ExecStart=startxfce4
[Install]
WantedBy=multi-user.target' > /etc/systemd/system/xfce4@debian.service
#Update, enable and start services
systemctl --system daemon-reload
systemctl enable vncserver@debian
systemctl enable novnc@debian
systemctl enable xfce4@debian
systemctl start vncserver@debian
systemctl start novnc@debian
systemctl start xfce4@debian
systemctl restart sshd

echo
echo '+-----------------------------------------------------------------'
echo '| Everything done, your LXC Debian Bullseye VNC container should'
echo '| now be reachable from the browser through:'
echo '| LXCContainerIP:8080/vnc.html?autoconnect=true'
echo '|'
echo '| Or via any compatible VNC client via:'
echo '| LXCContainerIP:5900'
echo '|'
echo '| SSH is enabled for the user debian.'
echo '|'
echo '| The default root password is: Unraid'
echo '| The default user password for the user debian is: debian'
echo '|'
echo '| WARNING: It is strongly Recommended to change these passwords!'
echo '+-----------------------------------------------------------------'"
