<?php
require_once 'Settings.php';

class Snapshot {

  public $name;
  public $date;
  public $time;

  function __construct($name, $date, $time) {
    $this->name = $name;
    $this->date = $date;
    $this->time = $time;
  }
}