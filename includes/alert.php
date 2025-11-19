<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

if (function_exists('get_flashes')) {
	$flashes = get_flashes();
} else {
	$flashes = isset($_SESSION['flash']) ? $_SESSION['flash'] : [];
	if (isset($_SESSION['flash'])) unset($_SESSION['flash']);
}

foreach ($flashes as $f) {
	$type = isset($f['type']) ? $f['type'] : 'info';
	$msg = isset($f['message']) ? $f['message'] : '';
	$cls = 'alert-info';
	if ($type === 'success') $cls = 'alert-success';
	if ($type === 'error' || $type === 'danger') $cls = 'alert-danger';
	if ($type === 'warning') $cls = 'alert-warning';

	echo '<div class="mt-3"><div class="alert ' . $cls . ' alert-dismissible fade show" role="alert">'
		 . htmlspecialchars($msg)
		 . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div></div>';
}

?>
