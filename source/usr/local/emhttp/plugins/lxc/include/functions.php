<?php

require_once 'Container.php';
require_once 'Settings.php';

function getNewMacAddress() {
  return "52:54:00:" .strtoupper(implode(':', str_split(substr(md5(mt_rand()), 0, 6), 2)));
}

function getVariable($path, $option) {
  $content = file_get_contents($path);
  if (preg_match('/^' . $option . '[^\r\n]*/m', $content, $line) && isset($line[0])) {
    return trim(explode('=', $line[0])[1]);
  }
  return null;
}

function getContainerStats($container, $option) {
  exec("lxc-info " . $container, $content);
  foreach($content as $index => $string) {
    if (strpos($string, $option) !== FALSE)
      return trim(explode(':', $string)[1]);
  }
}

function getAvailableInterfaces() {
  $interfaces = array();
  exec("ip -o link show | awk -F': ' '{print $2}'", $output);
  foreach ($output as $line) {
    $interfaceName = trim($line);
	  $interfaceName = explode('@', $interfaceName)[0];
    if (preg_match('/^(virbr|vhost|bond|eth|br)\d\S*/', $interfaceName)) {
        $interfaces[] = $interfaceName;
    }
    $sortOrder = ['br', 'eth', 'bond', 'vhost', 'virbr'];
    usort($interfaces, function ($a, $b) use ($sortOrder) {
      $posA = array_search(substr($a, 0, strpos($a, '0')), $sortOrder);
      $posB = array_search(substr($b, 0, strpos($b, '0')), $sortOrder);
      return $posA - $posB;
    });
  }
return $interfaces;
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

  if($contents === false) {
    return;
  }

  $newFile = [];
  $lines = explode("\n", $contents);
  $found = false;

  if(count($lines) <= 1) {
    return;
  }

  foreach($lines as $line) {
    if (strpos($line, $variable) === 0) {
      $newFile[] = $variable . "=" . $value;
      $found = true;
    } else {
      $newFile[] = $line;
    }
  }
  if (!$found && count($newFile) >= 1 ) {
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
  exec("lxc-create --name " . $name . " --template download -- --dist " . $distribution . " --release " . $release . " --arch amd64 2>&1", $output, $retval);
  if ($retval == 1) {
    echo '<p style="color:red;">';
    echo "ERROR, failed to create container " . $name . "!<br/><br/>";
    exec("logger LXC: error: Failed to create Container " . $name);
    foreach ($output as $error) {
      exec('logger "LXC: ' . $error . '"');
	  echo $error . "<br/>";
    }
    echo '</p>';
  } else {
    exec("logger LXC: Container " . $name . " created");
    $container = new Container($name);
    $container->setMac($mac);
    if ($autostart == "true") {
      $autostart = 1;
    } else {
      $autostart = 0;
    }
    $container->setAutostart($autostart);
	echo '<p style="color:green;">';
    foreach ($output as $message) {
	  echo $message . "<br/>";
    }
	echo '</p>';
  }
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

function createFromBackup($name, $container, $backup, $autostart, $mac) {
  exec('lxc-autobackup --restore --gui-restore=' . $container . '_' . $backup . ' --name=' . $container . ' --newname=' . $name);
  $container = new Container($name);
  $container->setMac($mac);
  if ($autostart == "true") {
    $autostart = 1;
  } else {
    $autostart = 0;
  }

  $container->setAutostart($autostart);
}

function downloadLXCproducts($url) {
  $urlparse = parse_url($url);
  $filename = "lxcimages";
  $path = '/tmp/lxc';
  if (!is_dir($path)) {
    mkdir($path, 0755, true);
  }
  if (!file_exists($path . '/' . $filename . '.json')) {
      $json = file_get_contents($url);
      file_put_contents($path . '/' . $filename . '.json', $json);
  } else {
    $fileage =  time() - filemtime($path . '/' . $filename . '.json');
    if ($fileage > 3600) {
      unlink($path . '/' . $filename . '.json');
      $json = file_get_contents($url);
      file_put_contents($path . '/' . $filename . '.json', $json);
    }
  }
}

function prepareContainer($name, $description, $configadditions, $preinstall, $install, $postinstall, $webui, $iconurl, $startcont) {
  $container = new Container($name);
  $settings = new Settings();

  if (!empty($description)) {
    $container->setDescription($description);
  }

  if (!empty($webui)) {
    $container->setWebuiurl($webui);
  }

  if (!empty($configadditions)) {
    $container->addConfig($configadditions);
  }

  if (!empty($iconurl)) {
    $path = $settings->default_path . '/custom-icons';
    $icon = file_get_contents($iconurl);
    if (!is_dir($path)) {
      mkdir($path, 0755, true);
    }
    file_put_contents($path . '/' . $name . '.png' , $icon);
  }

  if ($startcont == "true") {
    $container->startContainer($name);
  }
}