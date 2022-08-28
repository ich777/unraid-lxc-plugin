<?php

require_once 'Settings.php';
require_once 'Snapshot.php';
class Container {

  public $name;
  public $state;
  public $autostart;
  public $mac;
  public $cpus;
  public $snapshots;
  public $ips;
  public $distribution;
  public $memoryUse;
  public $kMemUse;
  public $totalBytes;
  public $pid;
  public $settings;
  public $config;
  public $path;

  function __construct($name) {
    $this->settings = new Settings();
    $this->name = $name;
    $this->path = $this->settings->default_path . '/' . $this->name;
    $this->config = $this->path . '/config';
    $this->state = getContainerStats($this->name, "State");
    $this->autostart = getVariable($this->config, 'lxc.start.auto');
    $this->mac = getVariable($this->config, 'lxc.net.0.hwaddr');
    $this->snapshots = $this->getSnapshots();
    $this->ips = trim(exec("lxc-info " . $this->name . " -iH"));
    $this->distribution = trim(exec("grep -oP '(?<=dist )\w+' " . $this->config . " | head -1 | sed 's/\"//g'"));
    $this->memoryUse = getContainerStats($this->name, "Memory use");
    $this->kMemUse = getContainerStats($this->name, "KMem use");
    $this->totalBytes = getContainerStats($this->name, "Total bytes");
    $this->pid = getContainerStats($this->name, "PID");
    $this->cpus = $this->getCpus();

  }

  private function getSnapshots() {
    $snapshots = array();
    exec("lxc-snapshot -L " . $this->name, $snapshotList);
    if ($snapshotList[0] != "No snapshots") {
      foreach ($snapshotList as $snapshot){
        $sorted = explode(" ", $snapshot);
        $snapshots[] = new Snapshot($sorted[0], str_replace(":", "_", $sorted[2]), $sorted[3]);
      }
    }
    return $snapshots;
  }

  private function getCpus() {
    $cpus = getVariable($this->config, 'lxc.cgroup.cpuset.cpus');
    if (empty($cpus)) {
      return exec("cat /proc/" . $this->pid . "/status | grep 'Cpus_allowed_list' | awk '{print $2}'");
    } else {
      return $cpus;
    }
  }

  function startContainer() {
    exec('logger "LXC: Starting container ' . $this->name . '"');
    exec('lxc-start ' . $this->name);
    exec('logger "LXC: Container ' . $this->name . ' started"');
  }

  function stopContainer() {
    exec('logger "LXC: Stopping container ' . $this->name . '"');
    exec('lxc-stop --timeout=' . $this->settings->default_timeout . ' ' . $this->name);
    exec('logger "LXC: Container ' . $this->name . ' stopped"');
  }

  function freezeContainer() {
    exec('logger "LXC: Freezing container ' . $this->name . '"');
    exec('lxc-freeze ' . $this->name);
    exec('logger "LXC: Container ' . $this->name . ' frozen"');
  }

  function unfreezeContainer() {
    exec('logger "LXC: Unfreezing container ' . $this->name . '"');
    exec('lxc-unfreeze ' . $this->name);
    exec('logger "LXC: Container ' . $this->name . ' unfrozen"');
  }

  function killContainer() {
    if ($this->state != "STOPPED") {
      exec('logger "LXC: Killing container ' . $this->name . '"');
    }
    exec('lxc-stop --kill ' . $this->name);
    if ($this->state != "STOPPED") {
      exec('logger "LXC: Container ' . $this->name . ' killed"');
    }
  }

  function destroyContainer() {
    if ($this->state != "STOPPED") {
      exec('logger "LXC: Destroying container ' . $this->name . '"');
    }

    foreach ($this->snapshots as $snapshot) {
      $this->deleteSnapshot($snapshot->name);
    }

    $this->killContainer();
    exec('umount ' . $this->path . '/rootfs');
    exec('lxc-destroy -s ' . $this->name);
    exec('logger "LXC: Container ' . $this->name . ' destroyed"');
  }

  function setAutostart($autostart) {
    setVariable($this->config, 'lxc.start.auto', $autostart);
  }

  function deleteSnapshot($snapshot) {
    exec('logger "LXC: Deleting snapshot ' . $snapshot . ' from container ' . $this->name . '"' );
    exec('umount ' . $this->path . '/snaps/' . $snapshot . '/rootfs');
    exec('lxc-snapshot -d ' . $snapshot . ' ' . $this->name);
    exec('logger "LXC: Snapshot ' . $snapshot . ' from container ' . $this->name. ' deleted"');
  }

  function createSnapshot() {
    $this->stopContainer();
    exec('lxc-snapshot ' . $this->name);
    if ($this->state == "RUNNING") {
      $this->startContainer();
    }
  }

  function setMac($mac) {
    setVariable($this->config, 'lxc.net.0.hwaddr', $mac);
  }

  function showConfig() {
    while (@ ob_end_flush());
    $proc = popen("cat " . $this->config, "r");
    while (!feof($proc)) {
      echo nl2br(fread($proc, 4096) . "\n");
      @ flush();
    }
  }
}
