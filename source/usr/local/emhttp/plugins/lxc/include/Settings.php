<?php

require_once 'functions.php';
class Settings {

  public $default_path;
  public $default_timeout;
  public $default_bridge;
  public $available_bridges;
  public $status;

  function __construct() {
    $this->default_path = getVariable('/boot/config/plugins/lxc/lxc.conf', 'lxc.lxcpath');
    $this->default_timeout = getVariable('/boot/config/plugins/lxc/plugin.cfg', 'TIMEOUT');
    $this->default_bridge = getVariable('/boot/config/plugins/lxc/default.conf', 'lxc.net.0.link');
    $this->available_bridges = getAvailableBridges();
    $this->status = getVariable('/boot/config/plugins/lxc/plugin.cfg', 'SERVICE');
  }

  function changeConfig($started, $default_path, $service, $timeout, $bridge) {
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
    setVariable('/boot/config/plugins/lxc/default.conf', 'lxc.net.0.link', $bridge);
    setVariable('/boot/config/plugins/lxc/plugin.cfg', 'SERVICE', $service);

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

    if ($started == "enabled") {
      exec('lxc-autostart');
    }
  }
}