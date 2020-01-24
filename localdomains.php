<?php /*
*    Pi-hole: A black hole for Internet advertisements
*    (c) 2017 Pi-hole, LLC (https://pi-hole.net)
*    Network-wide ad blocking via your own hardware.
*
*    This file is copyright under the latest version of the EUPL.
*    Please see LICENSE file for your rights under this license. */
    require "scripts/pi-hole/php/header.php";
?>
<!-- Title -->
<div class="page-header">
    <h1>Local Domain Names</h1>
</div>

<!-- Domain Input -->
<div class="row">
    <div class="col-xs-12">
	<label>Local DNS entry to add</label>
    </div>
    <div class="col-sm-4">
	<div class="form-group">
	    <div class="input-group">
		<div class="input-group-addon">IP Address</div>
		<input type="text" class="form-control" name="ipaddress"  id="ipaddress" placeholder="(e.g. 192.168.1.1)">
	    </div>
	</div>
        <div class="form-group">
            <div class="input-group">
                <label for="autoadd">Auto-add alias</label>
                <input type="checkbox"  name="autoadd"  id="autoadd">
            </div>
        </div>
    </div>
    <div class="col-sm-6">
	<div class="form-group">
	    <div class="input-group">
		<div class="input-group-addon">Domain Names</div>
            <textarea type="text" class="form-control" name="domains" id="domains" placeholder="Comma-separated list (myserver,myserver.example.com)"></textarea>
	    </div>
	</div>
    </div>
    <div class="col-sm-2">
	    <span class="input-group-btn">
		<button id="btnAdd" class="btn btn-default" type="button">Add</button>
		<button id="btnRefresh" class="btn btn-default" type="button"><i class="fa fa-sync"></i></button>
	    </span>
    </div>
</div>

<!-- Alerts -->
<div id="alInfo" class="alert alert-info alert-dismissible fade in" role="alert" hidden="true">
    <button type="button" class="close" data-hide="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <span id="infoMsg"></span>
</div>
<div id="alSuccess" class="alert alert-success alert-dismissible fade in" role="alert" hidden="true">
    <button type="button" class="close" data-hide="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <span id="successMsg"></span> The list will refresh.
</div>
<div id="alFailure" class="alert alert-danger alert-dismissible fade in" role="alert" hidden="true">
    <button type="button" class="close" data-hide="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    Failure! Something went wrong, see output below:<br/><br/><pre><span id="err"></span></pre>
</div>
<div id="alWarning" class="alert alert-warning alert-dismissible fade in" role="alert" hidden="true">
    <button type="button" class="close" data-hide="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    There was an issue with the data you sent, see output below:<br/><br/><pre><span id="warn"></span></pre>
</div>


<!-- Domain List -->
<ul class="list-group" id="list"></ul>
<script src="scripts/pi-hole/js/localdomains-list.js"></script>

<?php
require "scripts/pi-hole/php/footer.php";
?>
