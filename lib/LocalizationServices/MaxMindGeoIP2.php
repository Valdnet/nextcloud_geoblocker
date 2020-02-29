<?php
declare(strict_types = 1)
	;

namespace OCA\GeoBlocker\LocalizationServices;

include __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' .
		DIRECTORY_SEPARATOR . '3rdparty' . DIRECTORY_SEPARATOR . 'maxmind_geoip2' .
		DIRECTORY_SEPARATOR . '' . 'geoip2.phar';

use OCP\IL10N;
use GeoIp2\Database\Reader;
use MaxMind\Db\Reader\InvalidDatabaseException;
use InvalidArgumentException;
use GeoIp2\Exception\AddressNotFoundException;

class MaxMindGeoIP2 implements ILocalizationService {
	private $l;
	private $db_file_path = '/usr/share/GeoIP/GeoLite2-Country.mmdb';
	public function __construct(IL10N $l) {
		$this->l = $l;
	}
	public function getStatus(): bool {
		if (class_exists ( 'GeoIp2\Database\Reader' )) {
			try {
				$reader = new Reader ( $this->db_file_path ); 
			} catch (InvalidDatabaseException | InvalidArgumentException $e) {
				return FALSE;
			}
			
			try {
				$reader->country('1.1.1.1');
			} catch (AddressNotFoundException $e) {
				return FALSE;
			}
			return TRUE;
		}
		return FALSE;
	}
	public function getStatusString(): string {
		if ($this->getStatus()) {			
			return $this->l->t ('OK.  (Please make sure the databases are up to date. This is currently not checked here.)' );
		}
		return $this->l->t ( 
				'ERROR: "MaxMind GeoIP2" does not seem to be installed or database is not available at /usr/share/GeoIP/GeoLite2-Country.mmdb2.' );
	}
	public function getCountryCodeFromIP($ip_address): string {
// 		if ($this->getStatus ()) {
			try {
				$reader = new Reader ( $this->db_file_path );
				try {
					$record = $reader->country($ip_address);
				} catch (AddressNotFoundException $e) {
					return 'AA';
				}
				return $record->country->isoCode;
			} catch (InvalidDatabaseException | InvalidArgumentException $e) {
				return 'UNAVAILABLE';
			}
// 		}
// 		return 'UNAVAILABLE';
	}
}