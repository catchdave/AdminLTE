<?php
require_once('auth.php');
require_once('func.php');

set_error_handler(function ($severity, $message, $file, $line) {
    throw new \ErrorException($message, $severity, $severity, $file, $line);
});

try {
	$ip = isset($_POST['ip']) ? $_POST['ip'] : null;
	$ipAddress = validateIpAddress($ip);
	$saved = false;

	// Get current domains, add new one & write
	$domainList = LocalDomainList::createFromFile(LocalDomainList::DOMAIN_FILE);
	$existed = $domainList->remove($ipAddress);

	if ($existed) {
		$saved = $domainList->saveToFile();
	}
	if ($existed && $saved) {
		$statusMsg = ($domainList->isMutated() ? 'Modified' : 'Added') . ' local domain';
		http_response_code($domainList->isMutated() ? 200 : 201);
		echo json_encode(['status' => $statusMsg, 'results' => $domainList->jsonSerialize()]);
	} else {
		if (!$existed) {
			http_response_code(400);
			echo json_encode(['status' => $ip . ' does not exist',
				'message' => 'The IP address submitted is not in the local domains file, so there\'s nothing to do']);
		}
		else {
			http_response_code(500);
			echo json_encode(['status' => 'Error adding local domain(s) for ' . $ip,
				'message' => 'Unknown error saving file (permissions were valid)']);
		}
	}

} catch (InvalidArgumentException $e) {
	http_response_code(400);
	echo json_encode(['status' => $e->getMessage(), 'message' => $e->getTrace()]);
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(['status' => 'Unexpected Error', 'message' => $e->getMessage()]);
}

/**
 * @param string $ipAddress
 * @return string
 */
function validateIpAddress(string $ipAddress) : string {
	// Validate IP
	$ipAddress = trim($ipAddress);
	if (!$ipAddress || !filter_var($ipAddress, FILTER_VALIDATE_IP)) {
		throw new \InvalidArgumentException("Invalid IP Address: $ipAddress");
	}
	return $ipAddress;
}

restore_error_handler();
