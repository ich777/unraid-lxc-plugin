#!/bin/bash
DATA_DIR=$(pwd)
LAT_V=$(wget -qO- https://api.github.com/repos/lxc/lxc/tags | jq -r '.[].name' | sed 's/^lxc-//' | sed 's/^v//' | sed '/^[^0-9]/d' | sort -V | tail -1)
CPU_COUNT=$(nproc --all)
APP_NAME=LXC

# Create temporary directory
cd ${DATA_DIR}
mkdir -p ${DATA_DIR}/v$LAT_V

# Clone and checkout newest Tag
git clone https://github.com/lxc/lxc
cd ${DATA_DIR}/lxc
git checkout v${LAT_V}

# Setup and build LXC
meson setup -Dprefix=/usr \
  --libdir=/usr/lib \
  -Dprefix=/usr \
  -Dwerror=false \
  -Dapparmor=false \
  -Dselinux=false \
  -Dpam-cgroup=true \
  build
meson compile -C build

# Install build to temporary directory
DESTDIR=${DATA_DIR}/${LAT_V} make install -j${CPU_COUNT}

# Move lib folder to correct path and remove unnecessary files
cd ${DATA_DIR}/${LAT_V}
cp -R ${DATA_DIR}/${LAT_V}/lib/ ${DATA_DIR}/${LAT_V}/usr/
rm -rf ${DATA_DIR}/${LAT_V}/usr/lib/*.la
rm -rf ${DATA_DIR}/${LAT_V}/etc/lxc/default.conf ${DATA_DIR}/${LAT_V}/etc/lxc/lxc.conf
rm -rf ${DATA_DIR}/${LAT_V}/var/cache/lxc
rm -rf ${DATA_DIR}/${LAT_V}/lib/

# Create Slackware package
makepkg -l n -c n ${DATA_DIR}/v$LAT_V/${APP_NAME}-${LAT_V}-2.txz
cd ${DATA_DIR}/v$LAT_V
md5sum ${APP_NAME}-${LAT_V}-2.txz | awk '{print $1}' > ${APP_NAME}-${LAT_V}-2.txz.md5

## Cleanup
rm -R ${DATA_DIR}/lxc ${DATA_DIR}/${LAT_V}
chown $UID:$GID ${DATA_DIR}/v$LAT_V/*