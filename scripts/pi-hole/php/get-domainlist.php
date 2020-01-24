<?php
/* Pi-hole: A black hole for Internet advertisements
*  (c) 2017 Pi-hole, LLC (https://pi-hole.net)
*  Network-wide ad blocking via your own hardware.
*
*  This file is copyright under the latest version of the EUPL.
*  Please see LICENSE file for your rights under this license. */

require_once "func.php";

$domainList = LocalDomainList::createFromFile(LocalDomainList::DOMAIN_FILE);
echo json_encode($domainList);