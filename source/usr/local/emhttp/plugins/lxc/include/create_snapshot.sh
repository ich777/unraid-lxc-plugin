#!/bin/bash
echo "Creating snapshot, please wait until the DONE button is displayed!"
echo

CONT_STATUS="$(lxc-info --name $2 | grep State: | cut -d ':' -f2 | sed -e 's/^[ \t]*//')"
lxc-stop --timeout=${1} $2
lxc-snapshot $2 &
pid=$!

# If this script is killed, kill the snapshot.
trap "kill $pid 2> /dev/null" EXIT

# While copy is running...
while kill -0 $pid 2> /dev/null; do
    echo '.......'
    sleep 5
done

# Disable the trap on a normal exit.
trap - EXIT
if [ "${CONT_STATUS}" == "RUNNING" ]; then
  lxc-start $2
fi

echo
echo "Snapshot created"
echo