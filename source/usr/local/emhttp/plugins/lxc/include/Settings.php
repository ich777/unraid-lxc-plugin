<?php

require_once 'functions.php';
class Settings {

  public $default_path;
  public $default_timeout;
  public $default_startdelay;
  public $default_bridge;
  public $available_bridges;
  public $status;
  public $default_cont_url;
  public $backup_enabled;
  public $backup_path;
  public $backup_threads;
  public $backup_compression;
  public $backup_use_snapshot;

  function __construct() {
    $this->default_path = getVariable('/boot/config/plugins/lxc/lxc.conf', 'lxc.lxcpath');
    $this->default_timeout = getVariable('/boot/config/plugins/lxc/plugin.cfg', 'TIMEOUT');
    $this->default_startdelay = getVariable('/boot/config/plugins/lxc/plugin.cfg', 'AUTOSTART_DELAY');
    $this->default_bridge = getVariable('/boot/config/plugins/lxc/default.conf', 'lxc.net.0.link');
    $this->available_bridges = getAvailableBridges();
    $this->status = getVariable('/boot/config/plugins/lxc/plugin.cfg', 'SERVICE');
    $this->default_cont_url = getVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_CONTAINER_URL');
    $this->backup_enabled = getVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_BACKUP_SERVICE');
    $this->backup_path = getVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_BACKUP_PATH');
    $this->backup_keep = getVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_BACKUP_KEEP');
    $this->backup_threads = getVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_BACKUP_THREADS');
    $this->backup_compression = getVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_BACKUP_COMPRESSION');
    $this->backup_use_snapshot = getVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_BACKUP_USE_SNAPSHOT');
  }

  function changeConfig($started, $default_path, $service, $timeout, $startdelay, $bridge, $default_cont_url, $backup_enabled, $backup_path, $backup_keep, $backup_threads, $backup_compression, $backup_use_snapshot) {
    $activeContainers = getActiveContainers();

    foreach ($activeContainers as $container) {
      exec('logger "LXC: Stopping container ' . $container . '"');
      exec('lxc-stop --timeout='. $this->default_timeout . ' ' . $container . ' 2>/dev/null');
      exec('logger "LXC: Container ' . $container . ' stopped"');
    }

    if (substr($default_path, -1) == "/") {
      $default_path = substr($default_path, 0, -1);
    }

    setVariable('/boot/config/plugins/lxc/lxc.conf', 'lxc.lxcpath', $default_path);
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'TIMEOUT', $timeout);
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'AUTOSTART_DELAY', $startdelay);
    setVariable('/boot/config/plugins/lxc/default.conf', 'lxc.net.0.link', $bridge);
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'SERVICE', $service);
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_CONTAINER_URL', $default_cont_url);
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_BACKUP_SERVICE', $backup_enabled);
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_BACKUP_PATH', $backup_path);
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_BACKUP_KEEP', $backup_keep);
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_BACKUP_THREADS', $backup_threads);
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_BACKUP_COMPRESSION', $backup_compression);
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_BACKUP_USE_SNAPSHOT', $backup_use_snapshot);

    unlink('/var/cache/lxc');

    if (!is_file('/etc/lxc/default.conf')) {
      symlink( "/boot/config/plugins/lxc/default.conf", "/etc/lxc/default.conf");
    }

    if (!is_file('/etc/lxc/lxc.conf')) {
      symlink("/boot/config/plugins/lxc/lxc.conf", "/etc/lxc/lxc.conf");
    }

    if (!file_exists($default_path)) {
      mkdir($default_path);
      mkdir($default_path . '/cache');
    }

    symlink($default_path . "/cache", "/var/cache/lxc");

    $service_status = parse_ini_file('/boot/config/plugins/lxc/plugin.cfg')['SERVICE'];
    if ($started == "enabled" && $service_status == "enabled") {
      exec('lxc-autostart');
    }

    exec("sed -i '/^DOWNLOAD_SERVER=\"*/c\DOWNLOAD_SERVER=\"" . escapeshellarg($default_cont_url) . "\"' /usr/share/lxc/templates/lxc-download");
  }

  function changeMisc($timeout, $startdelay ,$default_cont_url) {
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'TIMEOUT', $timeout);
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'AUTOSTART_DELAY', $startdelay);
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_CONTAINER_URL', $default_cont_url);

    exec("sed -i '/^DOWNLOAD_SERVER=\"*/c\DOWNLOAD_SERVER=\"" . escapeshellarg($default_cont_url) . "\"' /usr/share/lxc/templates/lxc-download");
  }

  function changeBackup($backup_enabled, $backup_path, $backup_keep, $backup_threads, $backup_compression, $backup_use_snapshot) {
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_BACKUP_SERVICE', $backup_enabled);
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_BACKUP_PATH', $backup_path);
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_BACKUP_KEEP', $backup_keep);
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_BACKUP_THREADS', $backup_threads);
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_BACKUP_COMPRESSION', $backup_compression);
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'LXC_BACKUP_USE_SNAPSHOT', $backup_use_snapshot);
    if (!file_exists($backup_path)) {
     mkdir($backup_path);
   }
  }

}
