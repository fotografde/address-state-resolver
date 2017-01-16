<?php

namespace GetPhoto\L10n;

class StateResolver {

	protected $cache = [];

	/**
	 * Get state code from ZIP code
	 * @param string $zip
	 * @param string $city
	 * @param string $country
	 * @return null|string
	 */
	public function getState($zip, $city, $country) {
		$cache_key = serialize(func_get_args());

		if (array_key_exists($cache_key, $this->cache)) {
			return $this->cache[$cache_key];
		}
		$state = $this->_getState($zip, $city, $country);
		if (!$state) {
			return null;
		}

		$this->cache[$cache_key] = $state;
		return $state;
	}

	/**
	 * Get state code from ZIP code
	 * Calls Google Maps API
	 * @param string $zip
	 * @param string $city
	 * @param string $country
	 * @return null|string
	 */
	protected function _getState($zip, $city, $country) {

		sleep(1);

		$url = 'http://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode(implode(',', [
				$zip, $city, $this->getCountryName($country)
			]));

		// Try out up to 3 times in case of request timeout for example
		$response = null;
		for ($i = 0; $i < 3; $i++) {
			try {
				$client = new \Guzzle\Http\Client([
					'timeout'  => 2.0,
				]);
				/**
				 * @var \Guzzle\Http\Message\Request $request
				 * @var \Guzzle\Http\Message\Response $response
				 */
				$request = $client->get($url);
				$response = $request->send();
			} catch(\Exception $e) {
				continue;
			}
			if ($response->getStatusCode() == 200) {
				break;
			}
		}

		if (!$response || $response->getStatusCode() != 200) {
			return null;
		}

		$data = json_decode($response->getBody()->__toString(), true);

		if (empty($data['results'][0]['address_components'])) {
			return null;
		}

		$administrative_area_level_1 = null;
		$administrative_area_level_2 = null;
		foreach ($data['results'][0]['address_components'] as $component) {
			if (!empty($component['types']) && in_array('administrative_area_level_2', $component['types'])) {
				$administrative_area_level_2 = $component['short_name'];
			}
			if (!empty($component['types']) && in_array('administrative_area_level_1', $component['types'])) {
				$administrative_area_level_1 = $component['short_name'];
			}
		}

		/* Try to validate state code from Google */

		if ($administrative_area_level_2) {
			$state_code = sprintf('%s-%s', $country, $administrative_area_level_2);
			if ($state_code) {
				if ($this->validateState($country, $state_code)) {
					return $state_code;
				}
			}
		}

		if ($administrative_area_level_1) {
			$state_code = sprintf('%s-%s', $country, $administrative_area_level_1);
			if ($state_code) {
				if ($this->validateState($country, $state_code)) {
					return $state_code;
				}
			}
		}

		/* Fallback, try to fetch state by name */

		if ($administrative_area_level_2) {
			$state_code = $this->getStateByName($country, $administrative_area_level_2);
			if ($state_code) {
				if ($this->validateState($country, $state_code)) {
					return $state_code;
				}
			}
		}

		if ($administrative_area_level_1) {
			$state_code = $this->getStateByName($country, $administrative_area_level_1);
			if ($state_code) {
				if ($this->validateState($country, $state_code)) {
					return $state_code;
				}
			}
		}

		return null;
	}

	/**
	 * Countries with required state
	 * @return [string]
	 */
	public function getCountriesWithRequiredState() {

		$return = [];

		$countryRepository = new \CommerceGuys\Intl\Country\CountryRepository;
		$AddressFormatRepository = new \CommerceGuys\Addressing\Repository\AddressFormatRepository();
		foreach ($countryRepository->getList() as $country_code => $country_name) {
			$AddressFormat = $AddressFormatRepository->get($country_code);

			// If state is required
			if (in_array('administrativeArea', $AddressFormat->getRequiredFields())) {
				$return[] = $country_code;
			}
		}

		return $return;
	}

	/**
	 * @param string $countryCode
	 * @return string
	 */
	protected function getCountryName($countryCode) {
		$countryRepository = new \CommerceGuys\Intl\Country\CountryRepository;
		$country = $countryRepository->get($countryCode, 'en');
		return (string) @$country->getName();
	}

	/**
	 * Get State code by name
	 * @param string $country
	 * @param string $stateName
	 * @return null|string
	 */
	protected function getStateByName($country, $stateName) {
		$subdivisionRepository = new \CommerceGuys\Addressing\Repository\SubdivisionRepository();
		$states = $subdivisionRepository->getAll($country);
		/**
		 * @var \CommerceGuys\Addressing\Model\Subdivision $state
		 */
		foreach ($states as $state) {
			if (strtolower($stateName) === strtolower($state->getName())) {
				return $state->getId();
			}
		}
		return null;
	}

	/**
	 * Validates state value
	 * @param string $country
	 * @param string $state
	 * @return bool
	 */
	protected function validateState($country, $state) {

		// Return true if state is not required
		$AddressFormatRepository = new \CommerceGuys\Addressing\Repository\AddressFormatRepository();
		$AddressFormat = $AddressFormatRepository->get($country);
		if (!in_array('administrativeArea', $AddressFormat->getRequiredFields())) {
			return true;
		}

		$Validator = \Symfony\Component\Validator\Validation::createValidator();
		$Address = new \CommerceGuys\Addressing\Model\Address();
		$Address = $Address
			->withAdministrativeArea($state)
			->withCountryCode($country);
		$Constraint = new \CommerceGuys\Addressing\Validator\Constraints\AddressFormat();
		$violations = $Validator->validate($Address, $Constraint);

		if (!$violations->count()) {
			return true;
		}

		foreach ($violations as $violation) {
			if ('[administrativeArea]' === $violation->getPropertyPath()) {
				return false; // State value thorwn a violation
			}
		}

		return true;
	}

}