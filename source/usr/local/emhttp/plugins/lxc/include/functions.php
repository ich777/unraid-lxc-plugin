<?php

require_once 'Container.php';
require_once 'Settings.php';

function getNewMacAddress() {
  return "52:54:00:" .strtoupper(implode(':', str_split(substr(md5(mt_rand()), 0, 6), 2)));
}

function getVariable($path, $option) {
  $content = file_get_contents($path);
  if (preg_match('/^' . preg_quote($option, '/') . '\s*=\s*(.*)$/m', $content, $matches)) {
    return trim($matches[1]);
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

function createContainer($name, $description, $distribution, $release, $startcont, $autostart, $mac) {
  $settings = new Settings();
  if($settings->default_bdevtype == "zfs") {
    $bdev = "--bdev=zfs --zfsroot=" . (explode('/', $settings->default_path)[2] ?? '') . "/zfs_lxccontainers/" . $name;
  } elseif($settings->default_bdevtype == "btrfs") {
    $bdev = "--bdev=btrfs";
  } else {
    $bdev = "--bdev=dir";
  }
  exec("logger LXC: Creating container " . $name);
  while (@ ob_end_flush());
  exec("lxc-create --name " . $name . " " . $bdev . " --template download -- --dist " . $distribution . " --release " . $release . " --arch amd64 2>&1", $output, $retval);
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
    if($settings->default_bdevtype == "zfs") {
      exec("zfs set canmount=on " . (explode('/', $settings->default_path)[2] ?? '') . "/zfs_lxccontainers/" . $name . "/" . $name);
      exec("zfs mount " . (explode('/', $settings->default_path)[2] ?? '') . "/zfs_lxccontainers/" . $name . "/" . $name);
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
    echo '<p style="color:green;">';
    foreach ($output as $message) {
      echo $message . "<br/>";
    }
    echo '</p>';
    if ($startcont == "true") {
      $container->startContainer($name);
    }
  }
  if (!empty($description)) {
    $container->setDescription($description);
  }
}

function copyContainer($name, $description, $container, $autostart, $mac) {
  $oldContainer = new Container($container);
  $running = $oldContainer->state;
  $oldContainer->stopContainer();
  exec("logger LXC: Copying container " . $container);
  exec('lxc-copy -n ' . $container . ' -N ' . $name);
  exec("logger LXC: Container " . $container . " copied to " . $name);
  $newdescription = $description;
  $container = new Container($name);
  $container->setMac($mac);
  if ($autostart == "true") {
    $autostart = 1;
  } else {
    $autostart = 0;
  }

  $container->setAutostart($autostart);

  if (!empty($newdescription)) {
    $container->setDescription($newdescription);
  }

  if ($running == "RUNNING") {
    $oldContainer->startContainer();
  }
}

function createFromSnapshot($name, $description, $container, $snapshot, $autostart, $mac) {
  exec("logger LXC: Creating " . $name . " from container " . $container . "-" . $snapshot);
  exec('lxc-snapshot -n ' . $container . ' -r ' . $snapshot . ' -N ' . $name);
  exec("logger LXC: Container " . $name . " created from " . $container . "-" . $snapshot);
  $newdescription = $description;
  $container = new Container($name);
  $container->setMac($mac);
  if ($autostart == "true") {
    $autostart = 1;
  } else {
    $autostart = 0;
  }

  $container->setAutostart($autostart);

  if (!empty($newdescription)) {
    $container->setDescription($newdescription);
  }
}

function createFromBackup($name, $description, $container, $backup, $autostart, $mac) {
  exec('lxc-autobackup --restore --gui-restore=' . $container . '_' . $backup . ' --name=' . $container . ' --newname=' . $name);
  $newdescription = $description;
  $container = new Container($name);
  $container->setMac($mac);
  if ($autostart == "true") {
    $autostart = 1;
  } else {
    $autostart = 0;
  }

  $container->setAutostart($autostart);

  if (!empty($newdescription)) {
    $container->setDescription($newdescription);
  }
}

function downloadLXCproducts($url) {
  $filename = "lxcimages";
  $path = '/tmp/lxc';
  $url = escapeshellarg($url);
  if (!is_dir($path)) {
    mkdir($path, 0755, true);
    sleep(1);
  }
  if (!file_exists($path . '/' . $filename . '.json')) {
      shell_exec("wget -q -O " . $path . '/' . $filename . '.json' . " " . $url);
  } else {
    $fileage =  time() - filemtime($path . '/' . $filename . '.json');
    if ($fileage > 3600) {
      unlink($path . '/' . $filename . '.json');
      shell_exec("wget -q -O " . $path . '/' . $filename . '.json' . " " . $url);
    }
  }
}

function createfromTemplate($name, $description, $repository, $webui, $icon, $startcont, $autostart, $convertbdev, $mac, $supportlink, $donatelink) {
  $container = new Container($name);
  $settings = new Settings();
  $repositoryurl = parse_url($repository);
  $repositorypath = explode('/', trim($repositoryurl['path'], '/'));

  exec("logger LXC: Creating container " . $name . " from repository: " . $repository);

  if (is_dir($settings->default_path . "/" . $name)) {
    echo '<p style="color:red;">ERROR, failed to create container ' . $name . '<br/>Already exists!</p>';
    exec("logger LXC: error: Failed to create Container " . $name . ", container already exists");
    die();
  } else {
    mkdir($settings->default_path . "/" . $name, 0755, true);
  }

  if (!is_dir($settings->default_path . "/cache/template_cache")) {
    mkdir($settings->default_path . "/cache/template_cache", 0755, true);
  }

  if (isset($settings->github_user)) {
    $githubauth = "-u " . $settings->github_user . ":" . $settings->github_token . " ";
  }  else {
    $githubauth = "";
  }

  $githubjson = shell_exec("curl " . $githubauth . "-s https://api.github.com/repos/" . $repositorypath[0] . "/" . $repositorypath[1] . "/releases/latest");
  $githubjson = json_decode($githubjson, true);

  if (isset($githubjson['assets'])) {
    $assets = $githubjson['assets'];
    $download_assets = [];
    foreach ($assets as $asset) {
      if (strpos($asset['name'], '.tar.xz') !== false && strpos($asset['name'], '.md5') === false) {
        $download_assets[] = ['filename' => $asset['name'], 'url' => $asset['browser_download_url']];
      }
    }
  } else {
    echo '<p style="color:red;">ERROR, failed to create container ' . $name . '<br/>Found no assets on GitHub, please try again later!</p>';
    exec("logger LXC: error: Failed to create Container " . $name . ", found no assets on GitHub, please try again later");
    rmdir($settings->default_path . "/" . $name);
    die();
  }

  exec("logger LXC: Downloading container archive for " . $name);

  exec('wget -q -O ' . $settings->default_path . '/cache/template_cache/' . $download_assets[0]['filename'] . ' "' . $download_assets[0]['url'] . '"', $output, $retval);
  if ($retval == 1) {
    echo '<p style="color:red;">ERROR, failed to create container ' . $name . '<br/>Download failed!</p>';
    exec("logger LXC: error: Failed to create Container " . $name . ", download failed");
    rmdir($settings->default_path . "/" . $name);
    unlink($settings->default_path . '/cache/template_cache/' . $download_assets[0]['filename']);
    die();
  } else {
    exec('wget -q -O ' . $settings->default_path . '/cache/template_cache/' . $download_assets[0]['filename'] . '.md5 "' . $download_assets[0]['url'] . '.md5"', $output, $retval);
    if ($retval == 1) {
      echo '<p style="color:red;">ERROR, failed to create container ' . $name . '<br/>Download from md5 failed!</p>';
      exec("logger LXC: error: Failed to create Container " . $name . ", download from md5 failed");
      rmdir($settings->default_path . "/" . $name);
      unlink($settings->default_path . '/cache/template_cache/' . $download_assets[0]['filename']);
      unlink($settings->default_path . '/cache/template_cache/' . $download_assets[0]['filename'] . '.md5');
      die();
    }
  }

  exec("logger LXC: Download from container archive for " . $name . " successful");

  $md5file = exec('md5sum ' .$settings->default_path . '/cache/template_cache/' . $download_assets[0]['filename'] . ' | awk \'{print $1}\'');
  $md5check = exec('cat ' . $settings->default_path . '/cache/template_cache/' . $download_assets[0]['filename'] . '.md5 | awk \'{print $1}\'');

  if ($md5file != $md5check) {
    echo '<p style="color:red;">ERROR, failed to create container ' . $name . '<br/>MD5 Checksum Error!</p>';
    exec("logger LXC: error: Failed to create Container " . $name . ", checksum error");
    rmdir($settings->default_path . "/" . $name);
    unlink($settings->default_path . '/cache/template_cache/' . $download_assets[0]['filename']);
    unlink($settings->default_path . '/cache/template_cache/' . $download_assets[0]['filename'] . '.md5');
    die();
  }

  echo '<p style="color:green;">Unpacking the archive<br/><br/>---</p>';
  exec("logger LXC: Unpacking archive for container " . $name);

  exec('tar -C ' . $settings->default_path . '/' . $name . ' -xf ' . $settings->default_path . '/cache/template_cache/' . $download_assets[0]['filename'] . ' 2>/dev/null', $output, $retval);
  if ($retval == 1) {
    echo '<p style="color:red;">ERROR, failed to create container ' . $name . '<br/>Extraction failed!</p>';
    exec("logger LXC: error: Failed to create Container " . $name . ", extraction failed");
    rmdir($settings->default_path . "/" . $name);
    unlink($settings->default_path . '/cache/template_cache/' . $download_assets[0]['filename']);
    unlink($settings->default_path . '/cache/template_cache/' . $download_assets[0]['filename'] . '.md5');
    die();
  }

  $lxc_config = "# Container specific configuration\n" .
                "lxc.rootfs.path = dir:" . $settings->default_path . "/" . $name . "/rootfs\n" .
                "lxc.uts.name = " . $name . "\n\n" .
                "# Network configuration\n" .
                file_get_contents('/boot/config/plugins/lxc/default.conf') . "\n" .
                "lxc.net.0.hwaddr=00:00:00:00:00:00\n" .
                "lxc.start.auto=0\n";

  file_put_contents($settings->default_path . "/" . $name . "/config", $lxc_config, FILE_APPEND | LOCK_EX);

  $container = new Container($name);
  $container->setMac($mac);
  if ($autostart == "true") {
    $autostart = 1;
  } else {
    $autostart = 0;
  }

  $container->setAutostart($autostart);

  if (!empty($description)) {
    $container->setDescription($description);
  }

  if (!empty($webui)) {
    $container->setWebuiurl($webui);
  }

  if (!empty($supportlink)) {
    $container->setSupportlink($supportlink);
  }

  if (!empty($donatelink)) {
    $container->setDonatelink($donatelink);
  }

  if (!empty($icon)) {
    if (!is_dir($settings->default_path . '/custom-icons')) {
      mkdir($settings->default_path . '/custom-icons', 0755, true);
    }
    exec('wget -q -O ' . $settings->default_path . '/custom-icons/' . $name . '.png "' . $icon . '"', $output, $retval);
    if ($retval == 1) {
      exec("logger LXC: error: download from icon for container " . $name . " failed");
      unlink($settings->default_path . '/custom-icons/' . $name . '.png');
    }
  }

  exec("sed -i '/lxc\.mount\.entry.*/d' " . $settings->default_path . "/" . $name . "/config"); 

  unlink($settings->default_path . '/cache/template_cache/' . $download_assets[0]['filename']);
  unlink($settings->default_path . '/cache/template_cache/' . $download_assets[0]['filename'] . '.md5');

  if ($convertbdev == "true") {
    if (preg_match('/\b(zfs)\b/', $settings->default_bdevtype)) {
      exec('lxc-dirtozfs -q ' . $name, $output, $retval);
      $bdevtype = "ZFS";
    } elseif (preg_match('/\b(btrfs)\b/', $settings->default_bdevtype)) {
      exec('lxc-dirtobtrfs -q ' . $name, $output, $retval);
      $bdevtype = "BTRFS";
    }

    echo '<p style="color:green;">Converting container ' . $name . ' to ' . $bdevtype . '</p>';
    exec("logger LXC: Converting Container " . $name . " to " . $bdevtype);

    if ($retval == 1) {
      echo '<p style="color:red;">ERROR, Conversion from container ' . $name . '<br/> to ' . $bdevtype . ' failed!<br/><br/>---</p>';
      exec("logger LXC: error: Converstion from Container " . $name . " to " . $bdevtype . " failed");
      rmdir($settings->default_path . "/" . $name);
      unlink($settings->default_path . '/cache/template_cache/' . $download_assets[0]['filename']);
    } else {
      echo '<p style="color:green;">Conversion to ' . $bdevtype . ' from container ' . $name . ' done<br/><br/>---</p>';
      exec("logger LXC: Conversion to " . $bdevtype . " from Container " . $name . " done");
    }
  }

  echo '<p style="color:green;">You just created a container from the repository: ' . $repository . '<br/>Please check out the  <a href="' . $repository . '" target="_blank" rel="noopener noreferrer">README</a> from the repository for further infromation!</p>';
  exec("logger LXC: Container " . $name . " created");

  if ($startcont == "true") {
    $container->startContainer($name);
  }
}
