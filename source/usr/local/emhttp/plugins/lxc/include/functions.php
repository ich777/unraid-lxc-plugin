<?php

require_once 'Container.php';
require_once 'Settings.php';

function getNewMacAddress() {
  return "52:54:00:" .strtoupper(implode(':', str_split(substr(md5(mt_rand()), 0, 6), 2)));
}

function getVariable($path, $option) {
  $content = file_get_contents($path);
  preg_match('/^' . $option . '[^\r\n]*/m',$content,$line);
  return trim(explode('=', $line[0])[1]);
}

function getContainerStats($container, $option) {
  exec("lxc-info " . $container, $content);
  foreach($content as $index => $string) {
    if (strpos($string, $option) !== FALSE)
      return trim(explode(':', $string)[1]);
  }
}

function getAvailableBridges() {
  $bridges = array();
  exec("brctl show", $output);
  foreach ($output as $line) {
    if (preg_match('/^(vir)?br\d\S*/', $line, $matches)) {
      $bridges[] = strtok($matches[0], " ");
    }
  }
  return $bridges;
}

function getActiveContainers() {
  $containers = array();
  exec("lxc-ls --active", $output);
  foreach ($output as $line) {
    $containers[] = $line;
  }

  return $containers;
}

function getAllContainers() {
  $containers = shell_exec("lxc-ls");
  if (!empty($containers)) {
    $containers = preg_replace('!\s+!', ' ', $containers);
    $containers = explode(" ",trim($containers));

    $allContainers = array();
    foreach ($containers as $container) {
      $allContainers[] = new Container($container);
    }
  } else {
    $allContainers = array();
  }

  return $allContainers;
}

function setVariable($file, $variable, $value) {
  $contents = file_get_contents($file);
  $newFile = [];
  $lines = explode("\n", $contents);
  $found = false;

  foreach($lines as $line) {
    if (strpos($line, $variable) === 0) {
      $newFile[] = $variable . "=" . $value;
      $found = true;
    } else {
      $newFile[] = $line;
    }
  }
  if (!$found) {
    $newFile[] = $variable . "=" . $value;
  }

  file_put_contents($file, implode(PHP_EOL, $newFile));
}

function getCpus(){
  exec('cat /sys/devices/system/cpu/*/topology/thread_siblings_list|sort -nu', $cpus);
  $allCpus = array();
  $vCpus = array();
  foreach ($cpus as $cpu) {
    $cpu = explode(',', $cpu);
    $allCpus[] = $cpu[0];
    $vCpus[] = $cpu[1];
  }

  sort($allCpus);
  sort($vCpus);

  return array('allcpus' => $allCpus, 'vcpus' => $vCpus);
}

function createContainer($name, $distribution, $release, $autostart, $mac) {
  exec("logger LXC: Creating container " . $name);
  while (@ ob_end_flush());
  $proc = popen("lxc-create --name " . $name . " --template download -- --dist " . $distribution . " --release " . $release . " --arch amd64", "r");
  while (!feof($proc)) {
    echo nl2br(fread($proc, 4096) . "\n");
    @ flush();
  }

  exec("logger LXC: Container " . $name . " created");
  $container = new Container($name);
  $container->setMac($mac);
  if ($autostart == "true") {
    $autostart = 1;
  } else {
    $autostart = 0;
  }

  $container->setAutostart($autostart);
}

function copyContainer($name, $container, $autostart, $mac) {
  $oldContainer = new Container($container);
  $running = $oldContainer->state;
  $oldContainer->stopContainer();
  exec("logger LXC: Copying container " . $container);
  exec('lxc-copy -n ' . $container . ' -N ' . $name);
  exec("logger LXC: Container " . $container . " copied to " . $name);
  $container = new Container($name);
  $container->setMac($mac);
  if ($autostart == "true") {
    $autostart = 1;
  } else {
    $autostart = 0;
  }

  $container->setAutostart($autostart);

  if ($running == "RUNNING") {
    $oldContainer->startContainer();
  }
}

function createFromSnapshot($name, $container, $snapshot, $autostart, $mac) {
  exec("logger LXC: Creating " . $name . " from container " . $container . "-" . $snapshot);
  exec('lxc-snapshot -n ' . $container . ' -r ' . $snapshot . ' -N ' . $name);
  exec("logger LXC: Container " . $name . " created from " . $container . "-" . $snapshot);
  $container = new Container($name);
  $container->setMac($mac);
  if ($autostart == "true") {
    $autostart = 1;
  } else {
    $autostart = 0;
  }

  $container->setAutostart($autostart);
}
