<?php
declare(strict_types = 1)
	;

namespace OCA\GeoBlocker\GeoBlocker;

use OCA\GeoBlocker\Config\GeoBlockerConfig;
use OCP\ILogger;
use OCP\IL10N;
use OCA\GeoBlocker\LocalizationServices\ILocalizationService;

class GeoBlocker {
	private $user;
	private $logger;
	private $config;
	private $l;
	private $location_service;
	public const kCountryNotFoundCode = 'AA';

	public function __construct(String $user, ILogger $logger,
			GeoBlockerConfig $config, IL10N $l,
			ILocalizationService $location_service) {
		$this->user = $user;
		$this->logger = $logger;
		$this->config = $config;
		$this->l = $l;
		$this->location_service = $location_service;
	}

	private function createInvalidIPLogString(string $log_user,
			string $log_address): string {
		return $this->l->t(
				'The user "%s" attempt to login with an invalid IP address "%s".',
				array($log_user,$log_address));
	}

	private function logEvent(string $log_string): void {
		$this->logger->warning($log_string, array('app' => 'geoblocker'));
	}

	private function logError(string $log_string): void {
		$this->logger->error($log_string, array('app' => 'geoblocker'));
	}

	public function isIpAddressBlocked(String $ip_address): bool {
		$block_ip_address = false;
		if ($this->isIPAddressValid($ip_address)) {
			if (! $this->isIPAddressLocal($ip_address)) {

				$location = $this->location_service->getCountryCodeFromIP(
						$ip_address);

				$log_user = $this->config->getLogWithUserName() ? $this->user : $this->l->t(
						'NOT_SHOWN_IN_LOG');
				$log_location = $this->config->getLogWithCountryCode() ? $location : $this->l->t(
						'NOT_SHOWN_IN_LOG');
				$log_address = $this->config->getLogWithIpAddress() ? $ip_address : $this->l->t(
						'NOT_SHOWN_IN_LOG');

				if ($location !== 'INVALID_IP' && $location !== 'UNAVAILABLE') {
					if ($this->config->isCountryCodeInListOfChoosenCountries(
							$location) xor $this->config->getUseWhiteListing()) {
						$log_string = $this->l->t(
								'The user "%s" attempt to login with IP address "%s" from blocked country "%s".',
								array($log_user,$log_address,$log_location));
						$any_reaction = false;
						if ($this->config->getDelayIpAddress()) {
							usleep(30 * 1000000);
							$log_string .= ' ' . $this->l->t(
									'Login is delayed.');
							$any_reaction = true;
						}
						if ($this->config->getBlockIpAddress()) {
							$block_ip_address = true;
							$log_string .= ' ' . $this->l->t(
									'Login is blocked.');
							$any_reaction = true;
						}
						if (! $any_reaction) {
							$log_string .= ' ' .
									$this->l->t('No reaction is activated.');
						}
						$this->logEvent($log_string);
					}
				} elseif ($location === 'UNAVAILABLE') {
					$log_string = $this->l->t(
							'The login of user "%s" with IP address "%s" could not be checked due to problems with location service.',
							array($log_user,$log_address));
					$this->logEvent($log_string);
				} elseif ($location === 'INVALID_IP') {
					$log_string = $this->createInvalidIPLogString($log_user,
							$log_address);
					$this->logEvent($log_string);
				} else {
					$this->logger->error(
							"This shouldn't have happen. This line should never be reached.",
							array('app' => 'geoblocker'));
				}
			}
		} else {
			$log_user = $this->config->getLogWithUserName() ? $this->user : 'NOT_SHOWN_IN_LOG';
			$log_address = $this->config->getLogWithIpAddress() ? $ip_address : 'NOT_SHOWN_IN_LOG';

			$log_string = $this->createInvalidIPLogString($log_user,
					$log_address);
			$this->logEvent($log_string);
		}
		return $block_ip_address;
	}

	public static function isIPAddressValid(String $ip_address): bool {
		if (filter_var($ip_address, FILTER_VALIDATE_IP)) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * Returns if the IP address is an local IP address.
	 * This implicitly also confirms, that it is a valid IP address, if it is local.
	 * If it is not local it could also be a not valid IP address.
	 *
	 * @param string $IPAdress
	 *        	The IP Adress to check.
	 * @return bool
	 */
	public static function isIPAddressLocal(String $ip_address): bool {
		if (GeoBlocker::isIPAddressValid($ip_address)) {
			if (filter_var($ip_address, FILTER_VALIDATE_IP,
					FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
				return FALSE;
			} else {
				return TRUE;
			}
		} else {
			return FALSE;
		}
	}
}
