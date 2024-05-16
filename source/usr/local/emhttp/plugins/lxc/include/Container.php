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
  public $backups;
  public $backup_path;
  public $cpu_usage;
  public $ips;
  public $distribution;
  public $memoryUse;
  public $setmemory;
  public $totalBytes;
  public $pid;
  public $uptime;
  public $settings;
  public $config;
  public $path;
  public $description;
  public $lxcwebui;
  public $supportlink;
  public $donatelink;

  function __construct($name) {
    $this->settings = new Settings();
    $this->name = $name;
    $this->path = $this->settings->default_path . '/' . $this->name;
    $this->config = $this->path . '/config';
    $this->state = getContainerStats($this->name, "State");
    $this->autostart = getVariable($this->config, 'lxc.start.auto');
    $this->mac = getVariable($this->config, 'lxc.net.0.hwaddr');
    $this->snapshots = $this->getSnapshots();
    $this->backups = $this->getBackups();
    $this->backup_path = realpath($this->settings->backup_path);
    if ($this->state === "RUNNING") {
      file_put_contents('/tmp/lxc/containers/active', time());
      $container_stats = parse_ini_file('/tmp/lxc/containers/' . $this->name);
      if ($container_stats === null) {
        $container_stats = array("CPU" => "na", "MEMORY" => "", "IPS" => "");
      } else {
        $this->cpu_usage = $container_stats['CPU'];
        $this->memoryUse = $container_stats['MEMORY'];
        $this->ips = $container_stats['IPS'];
      }
    } else {
      $this->cpu_usage = "";
      $this->memoryUse = "";
      $this->ips = "";
    }
    $this->distribution = trim(exec("grep -oP '(?<=dist )\w+' " . $this->config . " | head -1 | sed 's/\"//g'"));
    $setmembytes = trim(shell_exec("grep 'lxc.cgroup.memory.limit_in_bytes' " . $this->config . " | awk -F= '{ print $2 }'"));
    if (empty($setmembytes)) {
      $setmembytes = trim(shell_exec('awk \'/MemTotal/ { printf "%.0f\n", $2 * 1024 }\' /proc/meminfo'));
    }
    if ($setmembytes >= 1024 * 1024 * 1024) {
      $this->setmemory = round($setmembytes / (1024 * 1024 * 1024), 2) . 'GiB';
    } elseif ($setmembytes >= 1024 * 1024) {
      $this->setmemory = round($setmembytes / (1024 * 1024), 2) . 'MiB';
    } else {
      $this->setmemory = $setmembytes . 'Bytes';
    }
    $this->totalBytes = getContainerStats($this->name, "Total bytes");
    $this->pid = getContainerStats($this->name, "PID");
    $starttime = shell_exec("ps -p " . $this->pid . " -o lstart --no-headers");
    if ($starttime !== null){
      $timenow = time();
      $starttime = strtotime($starttime);
      $seconds_elapsed = $timenow - $starttime;
      $days = floor($seconds_elapsed / 86400);
      $hours = floor(($seconds_elapsed % 86400) / 3600);
      $minutes = floor(($seconds_elapsed % 3600) / 60);
      $seconds = $seconds_elapsed % 60;

      if ($days > 0) {
        $this->uptime = "$days day" . ($days > 1 ? "s" : "");
      } elseif ($hours > 0) {
        $this->uptime = "$hours hour" . ($hours > 1 ? "s" : "");
      } elseif ($minutes > 0) {
        $this->uptime = "$minutes minute" . ($minutes > 1 ? "s" : "");
      } else {
        $this->uptime = "$seconds second" . ($seconds > 1 ? "s" : "");
      }
    } else {
      $this->uptime = "";
    }
    $this->cpus = $this->getCpus();
    $this->description = getVariable($this->config, '#container_description');
    $webui = getVariable($this->config, '#container_webui');
    if (empty($webui)) {
      $this->lxcwebui = null;
    } else {
      $this->lxcwebui = $webui;
    }
    $this->supportlink = getVariable($this->config, '#container_supportlink');
    $this->donatelink = getVariable($this->config, '#container_donatelink');
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

  private function getBackups() {
    $backups = array();
    exec("ls -1 " . realpath($this->settings->backup_path) . "/" . $this->name . "/ 2>/dev/null", $backupList);
    if (isset($backupList)) {
      foreach ($backupList as $backup){
        $pattern = '/^(.*?)_(\d+\.\d+\.\d+)_(\d{4}-\d{2}-\d{2})(\.tar\.xz)$/';
        preg_match($pattern, $backup, $sorted);
        $backups[] = new Backup($sorted[1], $sorted[3], $sorted[2]);
      }
    }
    return $backups;
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
    exec('lxc-start ' . $this->name . ' 2>&1', $output, $retval);
  if ($retval == 1) {
      exec('logger "LXC: error: Container ' . $this->name . ' failed to start"');
      foreach ($output as $error) {
        exec('logger "LXC: ' . $error . '"');
      }
  } else {
      exec('logger "LXC: Container ' . $this->name . ' started"');
      // add sleep to wait for IP address
      sleep(3);
    }
  }

  function stopContainer() {
    exec('logger "LXC: Stopping container ' . $this->name . '"');
    exec('lxc-stop --timeout=' . $this->settings->default_timeout . ' ' . $this->name);
    exec('logger "LXC: Container ' . $this->name . ' stopped"');
  }
  function restartContainer() {
    $this->stopContainer();
    sleep(1);
    $this->startContainer();
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
    if (is_dir($this->path . '/rootfs/var/empty')) {
      exec('find '. $this->path . '/rootfs/var/empty -exec chattr -i {} \;');
    }
    exec('lxc-destroy -s ' . $this->name . ' 2>&1', $output, $retval);
    if ($retval == 1) {
      echo '<p style="color:red;">';
      echo "ERROR, failed to destroy " . $this->name . "!<br/><br/>";
      exec("logger LXC: error: Failed to destroy Container " . $name);
      foreach ($output as $error) {
        exec('logger "LXC: ' . $error . '"');
        echo $error . "<br/>";
      }
    } else {
    if (file_exists($this->settings->default_path . '/custom-icons/' . $this->name . '.png')) {
        exec('rm ' . $this->settings->default_path . '/custom-icons/' . $this->name . '.png');
    }
      $settings = new Settings();
      if($settings->default_bdevtype == "zfs") {
        $dataset = (explode('/', $this->path)[2] ?? '') . '/zfs_lxccontainers/' . $this->name;
        $check_dataset = shell_exec('zfs list -H -o name ' . $dataset);
        if (trim($check_dataset) == trim($dataset)) {
          exec('zfs destroy ' . $dataset);
        }
      }
      echo '<p>Container ' . $this->name . ' destroyed!</p>';
      exec('logger "LXC: Container ' . $this->name . ' destroyed"');
    }
  }

  function setAutostart($autostart) {
    setVariable($this->config, 'lxc.start.auto', $autostart);
  }

  function deleteSnapshot($snapshot) {
    exec('logger "LXC: Deleting snapshot ' . $snapshot . ' from container ' . $this->name . '"' );
    exec('umount ' . $this->path . '/snaps/' . $snapshot . '/rootfs');
    exec('lxc-snapshot -d ' . $snapshot . ' ' . $this->name . ' 2>&1', $output, $retval);
    if ($retval == 1) {
      exec("logger LXC: error: Failed to remove Snapshot " . $snapshot . " from  Container " . $name);
      foreach ($output as $error) {
        exec('logger "LXC: ' . $error . '"');
      }
    } else {
      exec('logger "LXC: Snapshot ' . $snapshot . ' from container ' . $this->name. ' deleted"');
    }
  }

  function createSnapshot() {
    $settings = new Settings();
    $this->stopContainer();
    exec('lxc-snapshot ' . $this->name . ' 2>&1', $output, $retval);
    if ($retval == 1) {
      echo '<p style="color:red;">';
      echo "ERROR, failed to create snapshot from " . $this->name . "!<br/><br/>";
      exec("logger LXC: error: Failed to create snapshot from Container " . $name);
      foreach ($output as $error) {
        exec('logger "LXC: ' . $error . '"');
        echo $error . "<br/>";
      }
    } else {
      if($settings->default_bdevtype == "zfs") {
        $datasets = shell_exec("zfs list -r -H -o name " . (explode('/', $settings->default_path)[2] ?? '') . "/zfs_lxccontainers/" . $this->name);
        foreach (explode("\n", trim($datasets)) as $dataset) {
          $dataset_name = str_replace((explode('/', $settings->default_path)[2] ?? '') . "/zfs_lxccontainers/" . $this->name . "/", '', $dataset);
          if (preg_match('/snap\d+/', $dataset_name)) {
            shell_exec("zfs set canmount=on " . (explode('/', $settings->default_path)[2] ?? '') . "/zfs_lxccontainers/" . $this->name . "/" . $dataset_name);
            shell_exec("zfs mount " . (explode('/', $settings->default_path)[2] ?? '') . "/zfs_lxccontainers/" . $this->name . "/" . $dataset_name);
          }
        }
      }
      echo '<p>Snapshot  from container ' . $this->name . ' created!</p>';
      exec('logger "LXC: Snapshot from container ' . $this->name. ' created"');
      if ($this->state == "RUNNING") {
        $this->startContainer();
      }
    }
  }

  function createBackup() {
    exec('lxc-autobackup ' . $this->name . ' 2>&1', $output, $retval);
    if ($retval == 1) {
      echo '<p style="color:red;">';
      echo "ERROR, failed to create backup from " . $this->name . "!<br/><br/>";
      exec("logger LXC: error: Failed to create backup from Container " . $name);
      foreach ($output as $error) {
        exec('logger "LXC: ' . $error . '"');
        echo $error . "<br/>";
      }
    } else {
      echo '<p>Backup from container ' . $this->name . ' created!</p>';
      exec('logger "LXC: Backup from container ' . $this->name. ' created"');
    }
  }

  function deleteBackup($backup) {
    exec('logger "LXC: Deleting backup ' . $backup . ' from container ' . $this->name . '"' );
    unlink($this->backup_path . "/" . $this->name . "/" . $backup . ".tar.xz");
    exec('logger "LXC: Backup ' . $backup . ' from container ' . $this->name. ' deleted"');
    $files = glob($this->backup_path . "/" . $this->name . '/*');
    if (empty($files)) {
      rmdir($this->backup_path . "/" . $this->name);
      exec('logger "LXC: Backup directory for container ' . $this->name . ' empty, deleting directory"');
    }
  }

  function setMac($mac) {
    setVariable($this->config, 'lxc.net.0.hwaddr', $mac);
  }

  function setDescription($desc){
    setVariable($this->config, '#container_description', $desc);
  }

  function delDescription(){
    setVariable($this->config, '#container_description', '');
  }

  function setWebuiurl($webuiurl){
    setVariable($this->config, '#container_webui', $webuiurl);
  }

  function delWebuiurl(){
    setVariable($this->config, '#container_webui', '');
  }

  function setSupportlink($supporturl){
    setVariable($this->config, '#container_supportlink', $supporturl);
  }
  
  function setDonatelink($donateurl){
    setVariable($this->config, '#container_donatelink', $donateurl);
  }

  function addConfig($configadditions){
    file_put_contents($this->config, "\n\n#ADDITIONAL ENTRIES FROM UNRAID CA APP TEMPLATE\n" . preg_replace('/<br\s*\/?>/', "\n", $configadditions), FILE_APPEND);
  }

  function showConfig() {
    echo nl2br("Configuration file location: " . $this->config . "\n\n");
    while (@ ob_end_flush());
    $proc = popen("cat " . $this->config, "r");
    while (!feof($proc)) {
      echo nl2br(fread($proc, 4096) . "\n");
      @ flush();
    }
  }
}
