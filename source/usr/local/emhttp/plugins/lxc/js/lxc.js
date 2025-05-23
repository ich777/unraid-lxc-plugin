//Don't execute commands again if page is refreshed
if ( window.history.replaceState ) {
  window.history.replaceState( null, null, window.location.href );
}

function postAction(name, id) {
  let postData = {
    'lxc'   : '',
    'action'     : name,
    'container': id
  };
  $.post("/plugins/lxc/include/ajax.php", postData).done(function(response){
    parent.window.location.reload();
  });
}

function saveConfig(name) {
  swal({
    title: "Saving",
    text: "Saving configuration for LXC container: <span style=\"font-weight:bold\">" + name + "</span>, please wait...",
    closeOnEsc: false,
    showConfirmButton: false,
    allowOutsideClick: false,
    html: true
  });
  let updatedConfig = $('#configEditor').val();
  $.ajax({
    type: "POST",
    url: '/plugins/lxc/include/ajax.php',
    data: {
      'lxc': '',
      'action': 'saveConfig',
      'container': name,
      'updatedConfig': updatedConfig
    },
    success: function(response) {
      location.reload();
    },
  });
}

function startConsole(name, navigate) {
  openTerminal('lxc', name);
  if (navigate) {
    setTimeout(function() {
      top.Shadowbox.close();
      location.href = '/LXC';
    }, 1500);
  }
}

// Function to wait for an element to be available
function waitForElement(elementPath, callBack){
  window.setTimeout(function(){
    if($(elementPath).length){
      callBack(elementPath, $(elementPath));
    }else{
      waitForElement(elementPath, callBack);
    }
  },500)
}

// Function that shows the dialog
function showDialog(callback, text) {
  swal({
    title:"Proceed?",
    text: text,
    type:'warning',
    html:true,
    showCancelButton:true,
    confirmButtonText: "Proceed",
    cancelButtonText:"Cancel"
  },
  function(confirm){
    callback(confirm);
  });
}

function showPrompt(callback, title, text, placeholder) {
  swal({
    title: title,
    text: text,
    type: "input",
    showCancelButton: true,
    closeOnConfirm: true,
    inputPlaceholder: placeholder
  },
  function(confirm) {
    callback(confirm);
  });
}

// Prevent closing shadowbox when editing config
document.addEventListener('keydown', function(event) {
  const activeElement = document.activeElement;
  if (activeElement && activeElement.tagName.toLowerCase() === "textarea") {
    event.stopPropagation();
    return;
  }
}, true);



// Function that shows the status dialog
function showStatus(action, id, title, text) {
  let statusInterval;

  Shadowbox.open({
    content: '<div id="dialogContent" class="logLine spacing"></div>',
    player: 'html',
    title: title,
    onClose: function () {
      location.reload();
    },
    height: Math.min(screen.availHeight, 800),
    width: Math.min(screen.availWidth, 1200)
  });
  waitForElement("#dialogContent", function () {
    let dialogContent = $("#dialogContent");
    $.ajax({
      type: "POST",
      url: '/plugins/lxc/include/ajax.php',
      data: {
        'lxc': '',
        'action': action,
        'container': id
      },
      xhr: function() {
        // get the native XmlHttpRequest object
        const xhr = $.ajaxSettings.xhr();
        // set the onprogress event handler
        xhr.onprogress = function() {
          // replace the '#output' element inner HTML with the received part of the response
          dialogContent.html("<p>" + title + " " + id + ", please wait until the DONE button is displayed!</p><p>" + xhr.responseText + "</p>");
        }
        return xhr;
      },
      beforeSend: function () {
        dialogContent.append("<p>" + title + " " + id + ", please wait until the DONE button is displayed!</p>");
        statusInterval = setInterval(function () {
          dialogContent.append(".");
        }, 5000);
      },
      success: function (data) {
    if (data.toLowerCase().indexOf("error, failed to ") === -1) {
          dialogContent.append("<p/>");
    }
        dialogContent.append('<p class="centered"><button class="logLine" type="button" onclick="top.Shadowbox.close(); location.href = \'/LXC\'">Done</button></p>');
        clearInterval(statusInterval);
      }
    });
  });
}


function showDropdown(contName) {
  setTimeout(function() {
    document.getElementById("dropdown_" + contName).classList.toggle("show_lxc");
  }, 100);
}

// Function that creates a new container
function createContainer(name, description, distribution, release, startcont, autostart, mac) {
  let statusInterval;

  Shadowbox.open({
    content: '<div id="dialogContent" class="logLine spacing"></div>',
    player: 'html',
    title: "Create Container",
    onClose: function () {
      location.href = '/LXC';
    },
    height: Math.min(screen.availHeight, 800),
    width: Math.min(screen.availWidth, 1200)
  });
  waitForElement("#dialogContent", function () {
    let dialogContent = $("#dialogContent");
    $.ajax({
      type: "POST",
      url: '/plugins/lxc/include/ajax.php',
      data: {
        'lxc': '',
        'action': 'createCONT',
        'name': name,
        'description': description,
        'distribution': distribution,
        'release': release,
        'startcont': startcont,
        'autostart': autostart,
        'mac': mac
      },
      xhr: function() {
        // get the native XmlHttpRequest object
        const xhr = $.ajaxSettings.xhr();
        // set the onprogress event handler
        xhr.onprogress = function() {
          // replace the '#output' element inner HTML with the received part of the response
          dialogContent.html("<p>Creating container, please wait until the DONE button is displayed!</p><p>" + xhr.responseText + "</p>");
        }
        return xhr;
      },
      beforeSend: function () {
        dialogContent.append("<p>Creating container, please wait until the DONE button is displayed!</p>");
        statusInterval = setInterval(function () {
          dialogContent.append(".");
        }, 5000);
      },
      success: function (data) {
    if (data.toLowerCase().indexOf("error, failed to create container") === -1) {
          dialogContent.append("<p>To connect to the console from the container, start the container and select Console from the context menu.</p>");
          dialogContent.append("<p>If you want to connect to the container console from the Unraid terminal, start the container and type in:</p>");
          dialogContent.append("<p>lxc-attach " + name + "</p>")
          dialogContent.append('<p>It is recommended to attach to the corresponding shell by typing in for example:</p>');
          dialogContent.append("<p>lxc-attach " + name + " /bin/bash</p>");
    }
        let HTMLline = '<p class="centered">';
        if (startcont) {
            HTMLline += '<button type="button" onclick="startConsole(\'' + name + '\', true);">Open Terminal</button>';
        }
        HTMLline += '<button class="logLine" type="button" onclick="top.Shadowbox.close(); location.href = \'/LXC\';">Done</button>';
        HTMLline += '</p>';
        dialogContent.append(HTMLline);
        clearInterval(statusInterval);
      }
    });
  });
}

// Function that creates a new container from the Template
function createContainerCAApp(name, description, repository, webui, icon, startcont, autostart, convertbdev, mac, supportlink, donatelink) {
  let statusInterval;

  Shadowbox.open({
    content: '<div id="dialogContent" class="logLine spacing"></div>',
    player: 'html',
    title: "Create Container from Template",
    onClose: function () {
      location.href = '/LXC';
    },
    height: Math.min(screen.availHeight, 800),
    width: Math.min(screen.availWidth, 1200)
  });
  waitForElement("#dialogContent", function () {
    let dialogContent = $("#dialogContent");
    $.ajax({
      type: "POST",
      url: '/plugins/lxc/include/ajax.php',
      data: {
        'lxc': '',
        'action': 'createTEMPLATE',
        'name': name,
        'description': description,
        'repository': repository,
        'webui': webui,
        'icon': icon,
        'startcont': startcont,
        'autostart': autostart,
        'convertbdev': convertbdev,
        'mac': mac,
        'supportlink': supportlink,
        'donatelink': donatelink

      },
      xhr: function() {
        // get the native XmlHttpRequest object
        const xhr = $.ajaxSettings.xhr();
        // set the onprogress event handler
        xhr.onprogress = function() {
          // replace the '#output' element inner HTML with the received part of the response
          dialogContent.html("<p>Creating container " + name + " from repository: " + repository + "<br/>Please wait until the DONE button is displayed, this can take a few minutes depending on the container size and connection speed...</p><p>" + xhr.responseText + "</p>");
        }
        return xhr;
      },
      beforeSend: function () {
        dialogContent.append("<p>Creating container " + name + " from repository: " + repository + "<br/>Please wait until the DONE button is displayed, this can take a few minutes depending on the container size and connection speed...</p>");
        statusInterval = setInterval(function () {
          dialogContent.append(".");
        }, 5000);
      },
      success: function (data) {
    if (data.toLowerCase().indexOf("error, failed to create container") === -1) {
          dialogContent.append("<p>Check out the README.md from the container if further steps are necessary to configure the container!</p>");
    }
        let HTMLline = '<p class="centered">';
        if (startcont) {
            HTMLline += '<button type="button" onclick="startConsole(\'' + name + '\', true);">Open Terminal</button>';
        }
        HTMLline += '<button class="logLine" type="button" onclick="top.Shadowbox.close(); location.href = \'/LXC\';">Done</button>';
        HTMLline += '</p>';
        dialogContent.append(HTMLline);
        clearInterval(statusInterval);
      }
    });
  });
}

// Function that copies a container
function createCopy(name, description, container, autostart, mac) {
  let statusInterval;

  Shadowbox.open({
    content: '<div id="dialogContent" class="logLine spacing"></div>',
    player: 'html',
    title: "Copy Container",
    onClose: function () {
      location.href = '/LXC';
    },
    height: Math.min(screen.availHeight, 800),
    width: Math.min(screen.availWidth, 1200)
  });
  waitForElement("#dialogContent", function () {
    let dialogContent = $("#dialogContent");
    $.ajax({
      type: "POST",
      url: '/plugins/lxc/include/ajax.php',
      data: {
        'lxc': '',
        'action': 'copyCONT',
        'name': name,
        'description': description,
        'container': container,
        'autostart': autostart,
        'mac': mac
      },
      xhr: function() {
        // get the native XmlHttpRequest object
        const xhr = $.ajaxSettings.xhr();
        // set the onprogress event handler
        xhr.onprogress = function() {
          // replace the '#output' element inner HTML with the received part of the response
          dialogContent.html("<p>Copying container, please wait until the DONE button is displayed!</p><p>" + xhr.responseText + "</p>");
        }
        return xhr;
      },
      beforeSend: function () {
        dialogContent.append("Copying container, please wait until the DONE button is displayed!");
        statusInterval = setInterval(function () {
          dialogContent.append("<p>......</p>");
        }, 5000);
      },
      success: function (data) {
        dialogContent.append("<p>To connect to the console from the container, start the container and select Console from the context menu.</p>");
        dialogContent.append("<p>If you want to connect to the container console from the Unraid terminal, start the container and type in:</p>");
        dialogContent.append("<p>lxc-attach " + name + "</p>")
        dialogContent.append('<p>It is recommended to attach to the corresponding shell by typing in for example:</p>');
        dialogContent.append("<p>lxc-attach " + name + " /bin/bash</p>");
        dialogContent.append('<p class="centered"><button class="logLine" type="button" onclick="top.Shadowbox.close(); location.href = \'/LXC\'">Done</button></p>');
        clearInterval(statusInterval);
      }
    });
  });
}

function showSpinner() {
  // Show spinner
  document.querySelector('.spinner').style.display = 'block';
}

$(function() {
  // Disables all spaces in input fields
  $('.forbidSpace').on({
    keydown: function (e) {
      if (e.which === 32)
        return false;
    }
  });

  // Listener for deleting snapshots
  $(".deleteSNAP").on("click", function() {
    let id = this.id.split(" ")[0];
    let snapshot = this.id.split(" ")[1];

    swal({
        title: "Proceed?",
        text: "<span style=\"color:red;font-weight:bold;\">ATTENTION</span><br/>Do you really want to destroy this Snapshot? This is IRREVERSIBLE and will delete the snapshot and all data in it!",
        type: 'warning',
        html: true,
        showCancelButton: true,
        confirmButtonText: "Proceed",
        cancelButtonText: "Cancel"
      },
      function (p) {
        if (p) {
          let postData = {
            'lxc'   : '',
            'action'     : 'deleteSNAP',
            'container': id,
            'snapshot': snapshot
          };
          $.post("/plugins/lxc/include/ajax.php", postData).done(function(){
            parent.window.location.reload();
          });
        }
      });
  });

  // Listener for deleting backups
  $(".deleteBACKUP").on("click", function() {
    let id = this.id.split(" ")[0];
    let backup = this.id.split(" ")[1];

    swal({
        title: "Proceed?",
        text: "<span style=\"color:red;font-weight:bold;\">ATTENTION</span><br/>Do you really want to delete this backup? This is IRREVERSIBLE and will delete the backup!",
        type: 'warning',
        html: true,
        showCancelButton: true,
        confirmButtonText: "Proceed",
        cancelButtonText: "Cancel"
      },
      function (p) {
        if (p) {
          let postData = {
            'lxc'   : '',
            'action'     : 'deleteBACKUP',
            'container': id,
            'backup': backup
          };
          $.post("/plugins/lxc/include/ajax.php", postData).done(function(){
            parent.window.location.reload();
          });
        }
      });
  });

  // Listener for restoring from snapshot form
  $(document).on("submit", "form#fromSnapshot", function (event) {
    event.preventDefault();
    let statusInterval;
    let name = this.contName.value;
    let description = this.contDesc.value;
    let autostart = this.contAutostart.checked;
    let mac = this.contMac.value;
    Shadowbox.open({
      content: '<div id="dialogContent" class="logLine spacing"></div>',
      player: 'html',
      title: "Restoring Container from snapshot",
      onClose: function () {
        location.href = '/LXC';
      },
      height: Math.min(screen.availHeight, 800),
      width: Math.min(screen.availWidth, 1200)
    });
    waitForElement("#dialogContent", function () {
      let dialogContent = $("#dialogContent");
      $.ajax({
        type: "POST",
        url: '/plugins/lxc/include/ajax.php',
        data: {
          'lxc': '',
          'action': 'fromSnapshot',
          'name': name,
          'description': description,
          'container': container,
          'snapshot': snapshot,
          'autostart': autostart,
          'mac': mac
        },
        xhr: function() {
          // get the native XmlHttpRequest object
          const xhr = $.ajaxSettings.xhr();
          // set the onprogress event handler
          xhr.onprogress = function() {
            // replace the '#output' element inner HTML with the received part of the response
            dialogContent.html("<p>Creating container, please wait until the DONE button is displayed!</p><p>" + xhr.responseText + "</p>");
          }
          return xhr;
        },
        beforeSend: function () {
          dialogContent.append("<p>Creating container, please wait until the DONE button is displayed!</p>");
          statusInterval = setInterval(function () {
            dialogContent.append(".");
          }, 5000);
        },
        success: function (data) {
          dialogContent.append("<p style=\"color:green;\">Restoring container " + name + " done!</p>");
          dialogContent.append("<p>To connect to the console from the container, start the container and select Console from the context menu.</p>");
          dialogContent.append("<p>If you want to connect to the container console from the Unraid terminal, start the container and type in:</p>");
          dialogContent.append("<p>lxc-attach " + name + "</p>")
          dialogContent.append('<p>It is recommended to attach to the corresponding shell by typing in for example:</p>');
          dialogContent.append("<p>lxc-attach " + name + " /bin/bash</p>");
          dialogContent.append('<p class="centered"><button class="logLine" type="button" onclick="top.Shadowbox.close(); location.href = \'/LXC\'">Done</button></p>');
          clearInterval(statusInterval);
        }
      });
    });
  });

  // Listener for restoring from backup form
  $(document).on("submit", "form#fromBackup", function (event) {
    event.preventDefault();
    let statusInterval;
    let name = this.contName.value;
    let description = this.contDesc.value;
    let autostart = this.contAutostart.checked;
    let mac = this.contMac.value;
    Shadowbox.open({
      content: '<div id="dialogContent" class="logLine spacing"></div>',
      player: 'html',
      title: "Restoring Container from backup",
      onClose: function () {
        location.href = '/LXC';
      },
      height: Math.min(screen.availHeight, 800),
      width: Math.min(screen.availWidth, 1200),
    });
    waitForElement("#dialogContent", function () {
      let dialogContent = $("#dialogContent");
      $.ajax({
        type: "POST",
        url: '/plugins/lxc/include/ajax.php',
        data: {
          'lxc': '',
          'action': 'fromBackup',
          'name': name,
          'description': description,
          'container': container,
          'backup': backup,
          'autostart': autostart,
          'mac': mac
        },
        xhr: function() {
          // get the native XmlHttpRequest object
          const xhr = $.ajaxSettings.xhr();
          // set the onprogress event handler
          xhr.onprogress = function() {
            // replace the '#output' element inner HTML with the received part of the response
            dialogContent.html("<p>Creating container, please wait until the DONE button is displayed!</p><p>" + xhr.responseText + "</p>");
          }
          return xhr;
        },
        beforeSend: function () {
          dialogContent.append("<p>Creating container, please wait until the DONE button is displayed!</p>");
          statusInterval = setInterval(function () {
            dialogContent.append(".");
          }, 5000);
        },
        success: function (data) {
          dialogContent.append("<p style=\"color:green;\">Restoring container " + name + " done!</p>");
          dialogContent.append("<p>To connect to the console from the container, start the container and select Console from the context menu.</p>");
          dialogContent.append("<p>If you want to connect to the container console from the Unraid terminal, start the container and type in:</p>");
          dialogContent.append("<p>lxc-attach " + name + "</p>")
          dialogContent.append('<p>It is recommended to attach to the corresponding shell by typing in for example:</p>');
          dialogContent.append("<p>lxc-attach " + name + " /bin/bash</p>");
          dialogContent.append("<p>Note: You will only see as much backups as you have configured in the Global backups settings (oldest backups will be deleted first).</p>");
          dialogContent.append('<p class="centered"><button class="logLine" type="button" onclick="top.Shadowbox.close(); location.href = \'/LXC\'">Done</button></p>');
          clearInterval(statusInterval);
        },
      });
    });
  });

  // Listener for add container form
  $(document).on('submit','form#addContainer',function(event){
    event.preventDefault();
    let regex = /^([0-9A-F]{2}[:-]){5}([0-9A-F]{2})$/;
    if (!regex.test(this.contMac.value)) {
      this.contMac.classList.add("err");
      $("#emac").css('visibility', 'visible')
      return false;
    }
    let distribution = this.contDistribution.value;
    let release = this.contRelease.value;
    let name = this.contName.value;
    let description = this.contDesc.value;
    let startcont = this.contStart.checked;
    let autostart = this.contAutostart.checked;
    let mac = this.contMac.value;

    createContainer(name, description, distribution, release, startcont, autostart, mac);
  });

  // Listener for copying container
  $(document).on('submit','form#copyCont',function(event){
    event.preventDefault();
    let regex = /^([0-9A-F]{2}[:-]){5}([0-9A-F]{2})$/;
    if (!regex.test(this.contMac.value)) {
      this.contMac.classList.add("err");
      $("#emac").css('visibility', 'visible')
      return false;
    }
    let name = this.contName.value;
    let description = this.contDesc.value;
    let container = this.contSnap.value;
    let autostart = this.contAutostart.checked;
    let mac = this.contMac.value;
    createCopy(name, description, container, autostart, mac);
  });

  // Listener for all button actions
  $(".stopCONT, .restartCONT, .freezeCONT, .killCONT, .unfreezeCONT, .startCONT, .startALLCONT, .stopALLCONT, .freezeALLCONT, .unfreezeALLCONT").on("click", function(e) {
    e.stopImmediatePropagation();
    showSpinner();
    postAction($(this).attr("class"), this.id);
  });


  // Listener to edit container config
  document.addEventListener('click', function(e) {
    if (e.target.id === "editconfig") {
      e.stopImmediatePropagation();
      let container = $(e.target).attr("class");

      Shadowbox.open({
        content: '<div id="dialogContent" class="logLine spacing"></div>',
        player: 'html',
        title: "Show/Edit Configuration",
        onClose: function() {
          location.reload();
        },
        height: Math.min(screen.availHeight, 750),
        width: Math.min(screen.availWidth, 900)
      });

      waitForElement("#dialogContent", function() {
        $.ajax({
          type: "POST",
          url: '/plugins/lxc/include/ajax.php',
          data: {
            'lxc': '',
            'action': "showConfig",
            'container': container
          },
          success: function(data) {
            let dialogContent = $("#dialogContent");
            dialogContent.append('<textarea id="configEditor" style="width: 800px; height: 600px; margin: 0 auto; display: block; background-color: white; color: black; z-index: 9999; border: 1px solid #ccc; padding: 10px;">' + data + '</textarea>');
            dialogContent.append('<p class="centered" style="color: red;">WARNING: Saving the configuration will restart a running container!</p>');
            dialogContent.append('<p class="centered"><button class="logLine" type="button" onclick="saveConfig(\'' + container + '\')">Save</button><button class="logLine" type="button" onclick="top.Shadowbox.close();">Done</button></p>');
          }
        });
      });
    } else if ($(e.target).attr("class") === "btn_dropdown") {
      var dropdowns = document.getElementsByClassName("dropdown-menu");
      for (var i = 0; i < dropdowns.length; i++) {
        var openDropdown = dropdowns[i];
        if (openDropdown.classList.contains('show_lxc')) {
          openDropdown.classList.remove('show_lxc');
        }
      }
    }
  });

  let checkboxes = $("input[type=checkbox].lxc")
  checkboxes.change(function(e) {
    e.stopImmediatePropagation();
    let postData = {
      'lxc'   : '',
      'action'     : "autostart",
      'container': this.id,
      'autostart': $(this).is(':checked')
    };
    $.post("/plugins/lxc/include/ajax.php", postData).done(function(response){});
  });

  $(document).mouseup(function (e) {
    if ($(e.target).closest(".btn_dropdown").length === 0) {
      $('.dropdown-menu').removeClass('show_lxc');
    }
  });

  // Listener to destroy container
  $(".destroyCONT").on("click", function() {
    let id = this.id
    showDialog(function(response) {
      if (response) {
        showStatus("destroyCONT", id, "Destroy Container", "Destroying Container " + id);
      }
    }, "<span style=\"color:red;font-weight:bold;\">ATTENTION</span><br/>Do you really want to destroy the LXC Container: <span style=\"font-weight:bold\">" + id + "</span>?<br/>This is IRREVERSIBLE and will delete the container, snapshots and all data in it!<br/><br/><i>Backups will not be deleted!</i>");
  });

  // Listener to snapshot container
  $(".snapshotCONT").on("click", function() {
    let id = this.id;
    showDialog(function(response) {
      if (response) {
        showStatus("snapshotCONT", id, "Snapshot Container", "Snapshotting Container " + id);
      }
    }, "This action will stop the LXC Container and start it again if it was running.");
  });

  // Listener to backup container
  $(".backupCONT").on("click", function() {
    let id = this.id;
    showDialog(function(response) {
      if (response) {
        showStatus("backupCONT", id, "Backup Container", "Backup Container " + id);
      }
    }, "This action will stop the LXC Container and start it again if it was running.<br/><br/><span style=\"font-weight:bold;\">Note:</span> <i>You will only see as much backups as you have configured in the global backup settings (oldest backups will be deleted first).</i>");
  });

  // Listener to set description
  $(".descCONT").on("click", function() {
    let id = this.id;
    showPrompt(function(response) {
      if (response === "") {
        let postData = {
          'lxc'   : '',
          'action'     : "delDescription",
          'container': id
        };    
        $.post("/plugins/lxc/include/ajax.php", postData).done(function(response){
          parent.window.location.reload();
        });
      } else if (response != undefined && response != null && response != false && response != "" && response.length <= 50 && /^[\w.]+/.test( response )) {
        let postData = {
          'lxc'   : '',
          'action'     : "setDescription",
          'container': id,
          'description': response
        };
        $.post("/plugins/lxc/include/ajax.php", postData).done(function(response){
          parent.window.location.reload();
        });
      }
    }, 'Description', 'Max. 50 alphanumeric characters.\nLeave empty to delete the description.', 'Description or empty')
  });

  $(".webuiCONT").on("click", function() {
    let id = this.id;
    showPrompt(function(response) {
      if (response === "") {
        let postData = {
          'lxc'   : '',
          'action'     : "delWebUIURL",
          'container': id
        };    
        $.post("/plugins/lxc/include/ajax.php", postData).done(function(response){
          parent.window.location.reload();
        });
      } else if (response != undefined && response != null && response != false && response != "" && /^(https?|ftp):\/\/[^\s/$.?#].[^\s]*$/i.test( response )) {
        let postData = {
          'lxc'   : '',
          'action'     : "setWebUIURL",
          'container': id,
          'webuiurl': response
        };
        $.post("/plugins/lxc/include/ajax.php", postData).done(function(response){
          parent.window.location.reload();
        });
      }
    }, 'WebUI URL', 'Enter your URL like: http://192.168.0.10:8080 or https://subdomain.yourdomain.net\nLeave empty to delete the WebUI URL.', 'WebUI URL or empty')
  });

  // Listener for add container from CA App
  $(document).on('submit','form#addContainerCAApp',function(event){
    event.preventDefault();
    let name = this.contName.value;
    let description = this.contDesc.value;
    let repository = this.contRepo.value;
    let webui = this.contWebUI.value;
    let icon = this.contIcon.value;
    let startcont = this.contStart.checked;
    let autostart = this.contAutostart.checked;
    let convertbdev = this.contConvBDEV.checked;
    let mac = this.contMac.value;
    let supportlink = this.supportLink.value;
    let donatelink = this.donateLink.value;

    createContainerCAApp(name, description, repository, webui, icon, startcont, autostart, convertbdev, mac, supportlink, donatelink);
  });

})

$('.autostart').switchButton({labels_placement:'right', on_label:"On", off_label:"Off"});
