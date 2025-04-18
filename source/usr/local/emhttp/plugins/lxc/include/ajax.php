<?php
require_once 'functions.php';
require_once 'Settings.php';
if (isset($_POST['lxc'])) {
  $settings = new Settings();
  switch ($_POST['action']) {
    case 'startCONT':
      $container = new Container($_POST['container']);
      $container->startContainer();
      break;
    case 'stopCONT':
      $container = new Container($_POST['container']);
      $container->stopContainer();
      break;
    case 'startALLCONT':
      $allContainers = getAllContainers();
      foreach ($allContainers as $cont) {
        if($cont->state == "STOPPED" || $cont->state == "FROZEN") {
          $container = new Container($cont->name);
          $container->startContainer();
        }
      }
      break;
    case 'stopALLCONT':
      $allContainers = getAllContainers();
      foreach ($allContainers as $cont) {
        if($cont->state == "RUNNING" || $cont->state == "FROZEN") {
          $container = new Container($cont->name);
          $container->stopContainer();
        }
      }
      break;
    case 'restartCONT':
      $container = new Container($_POST['container']);
      $container->restartContainer();
      break;
    case 'freezeCONT':
      $container = new Container($_POST['container']);
      $container->freezeContainer();
      break;
    case 'freezeALLCONT':
      $allContainers = getAllContainers();
      foreach ($allContainers as $cont) {
        if($cont->state == "RUNNING") {
          $container = new Container($cont->name);
          $container->freezeContainer();
        }
      }
      break;
    case 'unfreezeCONT':
      $container = new Container($_POST['container']);
      $container->unfreezeContainer();
      break;
    case 'unfreezeALLCONT':
      $allContainers = getAllContainers();
      foreach ($allContainers as $cont) {
        if($cont->state == "FROZEN") {
          $container = new Container($cont->name);
          $container->unfreezeContainer();
        }
      }
      break;
    case 'killCONT':
      $container = new Container($_POST['container']);
      $container->killContainer();
      break;
    case 'autostart':
      $container = new Container($_POST['container']);
      if ($_POST['autostart'] == 'true') {
        $container->setAutostart(1);
      } else {
        $container->setAutostart(0);
      }
      break;
    case 'destroyCONT':
      $container = new Container($_POST['container']);
      $container->destroyContainer();
      break;
    case 'snapshotCONT':
      $container = new Container($_POST['container']);
      $container->createSnapshot();
      break;
    case 'deleteSNAP':
      $container = new Container($_POST['container']);
      $container->deleteSnapshot($_POST['snapshot']);
      break;
    case 'backupCONT':
      $container = new Container($_POST['container']);
      $container->createBackup();
      break;
    case 'deleteBACKUP':
      $container = new Container($_POST['container']);
      $container->deleteBackup($_POST['backup']);
      break;
    case 'createCONT':
      createContainer($_POST['name'], $_POST['description'], $_POST['distribution'], $_POST['release'], $_POST['startcont'], $_POST['autostart'], $_POST['mac']);
      break;
    case 'copyCONT':
      copyContainer($_POST['name'], $_POST['description'], $_POST['container'], $_POST['autostart'], $_POST['mac']);
      break;
    case 'showConfig':
      $container = new Container($_POST['container']);
      $container->showConfig();
      break;
    case 'saveConfig':
      $updatedConfig = trim($_POST['updatedConfig']);
      $container = $_POST['container'];
      $containerConfig = $settings->default_path . "/" . $container . "/config";
      file_put_contents($containerConfig, $updatedConfig);
      $container = new Container($container);
      if($container->state == "RUNNING") {
        $container->restartContainer();
      }
      break;
    case 'fromSnapshot':
      createFromSnapshot($_POST['name'], $_POST['description'], $_POST['container'], $_POST['snapshot'], $_POST['autostart'], $_POST['mac']);
      break;
    case 'fromBackup':
      createFromBackup($_POST['name'], $_POST['description'], $_POST['container'], $_POST['backup'], $_POST['autostart'], $_POST['mac']);
      break;
    case 'setDescription':
      $container = new Container($_POST['container']);
      $container->setDescription($_POST['description']);
      break;
    case 'delDescription':
      $container = new Container($_POST['container']);
      $container->delDescription();
      break;
    case 'setWebUIURL':
      $container = new Container($_POST['container']);
      $container->setWebuiurl($_POST['webuiurl']);
      break;
    case 'delWebUIURL':
      $container = new Container($_POST['container']);
      $container->delWebuiurl();
      break;
    case 'createTEMPLATE':
      createfromTemplate($_POST['name'], $_POST['description'], $_POST['repository'], $_POST['webui'], $_POST['icon'], $_POST['startcont'], $_POST['autostart'], $_POST['convertbdev'], $_POST['mac'], $_POST['supportlink'], $_POST['donatelink']);
      break;
    default:
      break;
  }
}

require_once 'Container.php';
if (isset($_POST['action']) && $_POST['action'] == 'updateValues') {
    file_put_contents('/tmp/lxc/containers/active', time());
    $containerNames = json_decode($_POST['containerNames']);
    $data = [];
    foreach ($containerNames as $name) {
        $container = new Container($name);
        $cpu_usage = $container->cpu_usage;
        $memoryUse = $container->memoryUse;
        $ips = $container->ips;
        $totalBytes = $container->totalBytes;
        $data[] = ['name' => $name, 'cpu_usage' => $cpu_usage, 'memoryUse' => $memoryUse, 'ips' => $ips, 'totalBytes' => $totalBytes];
    }
    echo json_encode($data);
}
