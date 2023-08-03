<?php
require_once 'functions.php';
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
    case 'restartCONT':
      $container = new Container($_POST['container']);
      $container->restartContainer();
      break;
    case 'freezeCONT':
      $container = new Container($_POST['container']);
      $container->freezeContainer();
      break;
    case 'unfreezeCONT':
      $container = new Container($_POST['container']);
      $container->unfreezeContainer();
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
      createContainer($_POST['name'], $_POST['distribution'], $_POST['release'], $_POST['autostart'], $_POST['mac']);
      break;
    case 'copyCONT':
      copyContainer($_POST['name'], $_POST['container'], $_POST['autostart'], $_POST['mac']);
      break;
    case 'showConfig':
      $container = new Container($_POST['container']);
      $container->showConfig();
      break;
    case 'fromSnapshot':
      createFromSnapshot($_POST['name'], $_POST['container'], $_POST['snapshot'], $_POST['autostart'], $_POST['mac']);
      break;
    case 'fromBackup':
      createFromBackup($_POST['name'], $_POST['container'], $_POST['backup'], $_POST['autostart'], $_POST['mac']);
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
    default:
      break;
  }
}

require_once 'Container.php';
if (isset($_POST['action']) && $_POST['action'] == 'updateValues') {
    $containerNames = json_decode($_POST['containerNames']);
    $data = [];
    foreach ($containerNames as $name) {
        $container = new Container($name);
        $ips = $container->ips;
        $totalBytes = $container->totalBytes;
        $data[] = ['name' => $name, 'ips' => $ips, 'totalBytes' => $totalBytes];
    }
    echo json_encode($data);
}
