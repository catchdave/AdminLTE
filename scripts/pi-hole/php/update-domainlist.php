<?php
require_once('auth.php');
require_once('func.php');

set_error_handler(function ($severity, $message, $file, $line) {
    throw new \ErrorException($message, $severity, $severity, $file, $line);
});

try {
	list($ipAddress, $hosts, $autoAdd) = validateData($_POST);

	// Get current domains, add new one & write
	$domainList = LocalDomainList::createFromFile(LocalDomainList::DOMAIN_FILE);
	$ld = new LocalDomain($hosts, $ipAddress);

	if ($autoAdd) $ld->autoAddAlias();
	$domainList->add($ld, true);

	$result = $domainList->saveToFile();
	if ($result) {
		$statusMsg = ($domainList->isMutated() ? 'Modified' : 'Added') . ' local domain';
		http_response_code($domainList->isMutated() ? 200 : 201);
		echo json_encode(['status' => $statusMsg, 'results' => $domainList->jsonSerialize()]);
	} else {
		http_response_code(500);
		echo json_encode(['status' => 'Error adding local domain(s) for ' . $ipAddress, 'message' => 'Unknown error saving file (permissions were valid)']);
	}

} catch (InvalidArgumentException $e) {
	http_response_code(400);
	echo json_encode(['status' => 'User data error', 'message' => $e->getMessage()]);
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(['status' => 'Unexpected Error', 'message' => $e->getMessage()]);
}

/**
 * @param array $postAr - Array of posted parameters
 * @return array
 */
function validateData(array $postAr) : array {
	// Validate IP
	$ipAddress = isset($postAr['ip']) ? $postAr['ip'] : null;
	$ipAddress = trim($ipAddress);
	if (!$ipAddress || !filter_var($ipAddress, FILTER_VALIDATE_IP)) {
		throw new \InvalidArgumentException("Invalid IP Address: $ipAddress");
	}

	// Validate Hosts
	$hostsString = isset($postAr['domains']) ? $postAr['domains'] : '';
	if (!$hostsString) {
		throw new \InvalidArgumentException("One or more host names are invalid: $hostsString");
	}
	$hosts = array_filter(array_map('trim', explode(',', trim($hostsString))));
	foreach ($hosts as $h) {
		if (!filter_var($h, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
			throw new \InvalidArgumentException("One or more host names are invalid: $hostsString");
		}
	}

	$autoAdd = isset($postAr['autoadd']) && $postAr['autoadd'] === "true";
	return [$ipAddress, $hosts, $autoAdd];
}

restore_error_handler();