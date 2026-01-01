<?php

/**
 * GoWA Installer - Downloads the correct binary for the system architecture
 */
class GoWAInstaller
{
	const GOWA_VERSION = '7.10.2';
	const GOWA_RELEASES_URL = 'https://github.com/aldinokemal/go-whatsapp-web-multidevice/releases/download/v%s/whatsapp_%s_%s_%s.zip';

	/**
	 * Get system architecture
	 * @return string arm64 or amd64
	 */
	public static function getArch()
	{
		$arch = php_uname('m');
		if (in_array($arch, array('aarch64', 'arm64'))) {
			return 'arm64';
		}
		return 'amd64';
	}

	/**
	 * Get operating system
	 * @return string linux, darwin, windows
	 */
	public static function getOS()
	{
		$os = strtolower(PHP_OS);
		if (strpos($os, 'darwin') !== false) return 'darwin';
		if (strpos($os, 'win') !== false) return 'windows';
		return 'linux';
	}

	/**
	 * Check if goWA is installed
	 * @return bool
	 */
	public static function isInstalled()
	{
		$binPath = dol_buildpath('/whatsapp/bin/gowa', 0);
		return file_exists($binPath) && is_executable($binPath);
	}

	/**
	 * Get download URL for current system
	 * @return string
	 */
	public static function getDownloadUrl()
	{
		$os = self::getOS();
		$arch = self::getArch();
		return sprintf(self::GOWA_RELEASES_URL, self::GOWA_VERSION, self::GOWA_VERSION, $os, $arch);
	}

	/**
	 * Install goWA binary
	 * @return array Result with 'success' and 'message'
	 */
	public static function install()
	{
		$modulePath = dol_buildpath('/whatsapp/', 0);
		$binDir = $modulePath . 'bin/';
		$storagesDir = $modulePath . 'storages/';
		$tmpZip = $modulePath . 'gowa_tmp.zip';

		// Create directories
		if (!is_dir($binDir)) {
			if (!mkdir($binDir, 0755, true)) {
				return array('success' => false, 'message' => 'Failed to create bin directory: ' . $binDir);
			}
		}
		if (!is_dir($storagesDir)) {
			if (!mkdir($storagesDir, 0777, true)) {
				return array('success' => false, 'message' => 'Failed to create storages directory');
			}
		}

		// Download using cURL (more reliable)
		$url = self::getDownloadUrl();
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 120);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$zipContent = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);
		
		if ($httpCode != 200 || empty($zipContent)) {
			return array('success' => false, 'message' => 'Failed to download from: ' . $url . ' (HTTP ' . $httpCode . ') ' . $error);
		}

		if (file_put_contents($tmpZip, $zipContent) === false) {
			return array('success' => false, 'message' => 'Failed to save zip file');
		}

		// Extract using system unzip command (more reliable than ZipArchive)
		$output = array();
		$returnCode = 0;
		exec('unzip -o ' . escapeshellarg($tmpZip) . ' -d ' . escapeshellarg($binDir) . ' 2>&1', $output, $returnCode);
		
		// Clean up zip file
		@unlink($tmpZip);
		
		if ($returnCode != 0) {
			return array('success' => false, 'message' => 'Failed to extract zip file: ' . implode("\n", $output));
		}

		// Find and rename the binary
		$arch = self::getArch();
		$os = self::getOS();
		$possibleNames = array(
			$os . '-' . $arch,
			'whatsapp',
			'whatsapp_' . self::GOWA_VERSION . '_' . $os . '_' . $arch
		);

		$binPath = $binDir . 'gowa';
		foreach ($possibleNames as $name) {
			$srcPath = $binDir . $name;
			if (file_exists($srcPath)) {
				rename($srcPath, $binPath);
				chmod($binPath, 0755);
				return array('success' => true, 'message' => 'goWA installed successfully');
			}
		}

		// List what was extracted for debugging
		$files = scandir($binDir);
		return array('success' => false, 'message' => 'Binary not found after extraction. Files in ' . $binDir . ': ' . implode(', ', $files));
	}


	/**
	 * Uninstall goWA binary
	 * @return bool
	 */
	public static function uninstall()
	{
		$binPath = dol_buildpath('/whatsapp/bin/gowa', 0);
		if (file_exists($binPath)) {
			return unlink($binPath);
		}
		return true;
	}
}
