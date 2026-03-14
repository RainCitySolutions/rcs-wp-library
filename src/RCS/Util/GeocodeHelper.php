<?php
declare(strict_types = 1);
namespace RCS\Util;

use Geocoder\Location;
use Geocoder\Provider\Provider;
use Geocoder\Query\GeocodeQuery;
use Psr\Log\LoggerInterface;

/**
 * Helper class for using the Google Geocode API.
 */
class GeocodeHelper
{
    /**
     *
     * @param Provider $geocodeProvider An implementation of Provider.
     * @param LoggerInterface $logger Optional. If provided, will be used for
     *      logging any errors that occur.
     */
    public function __construct(
        private Provider $geocodeProvider,
        private ?LoggerInterface $logger = null
        )
    {
    }

    /**
     * Determine if a postal code is valid for a set of countries.
     *
     * @param string $postalCode The postal code to test.
     * @param string[] $countries An array of ISO-3166-1, two letter country
     *      codes. E.g. US or CA.
     *
     * @return bool True if the postal code is valid for one of the provided
     *      country codes. False if the postal code is not valid for any of
     *      the countries, or an error occured trying to determine the postal
     *      code validity.
     */
    public function isValidPostalCode(string $postalCode, array $countries): bool
    {
        $result = false;

        $geoLocation = $this->fetchLocation($postalCode);

        if ($geoLocation) {
            $country = $geoLocation->getCountry();

            if ($country &&
                in_array($country->getCode(), $countries))
            {
                $result = true;
            }
        }

        return $result;
    }

    /**
     * Determine if the zip or postal code provided is valid in the United
     * States or Cananda.
     *
     * @param string $zipcode The zip or postal code to validate.
     *
     * @return bool True if the postal code is valid in either the United
     *      States or Canada. False if the postal code is not valid either
     *      country , or an error occured trying to determine the postal
     *      code validity.
     */
    public function isValidUsOrCaZipcode(string $zipcode): bool
    {
        return $this->isValidPostalCode($zipcode, ['US', 'CA']);
    }


    /**
     *
     * @param string $location
     *
     * @return GeocodeLocation|NULL
     */
    public function lookupGeoLocation(string $location): ?GeocodeLocation
    {
        $result = null;

        $geoLocation = $this->fetchLocation($location);

        if ($geoLocation) {
            $result = $this->constructGeocodeLocation($geoLocation);
        }

        return $result;
    }

    /**
     *
     * @param string $location
     *
     * @return Location|NULL
     */
    private function fetchLocation(string $location): ?Location
    {
        $result = null;

        try {
            $queryResult = $this->geocodeProvider->geocodeQuery(GeocodeQuery::create($location));

            if (!$queryResult->isEmpty()) {
                $result = $queryResult->first();
            }
        } catch (\Exception $e) {
            $this->logger->critical('Error fetching Geocode information for ' . $location . ': ' . $e->getMessage());
        }

        return $result;
    }


    private function constructGeocodeLocation(Location $location): GeocodeLocation
    {
        $adminLevel = $location->getAdminLevels()->get(1);

        $result = new GeocodeLocation();
        $result
            ->setAddressLine1(($location->getStreetNumber() ?? '') . ' ' . ($location->getStreetName() ?? ''))
            ->setCity($location->getLocality() ?? '')
            ->setState($adminLevel->getName())
            ->setStateAbbreviation($adminLevel->getCode() ?? '')
            ->setZipcode($location->getPostalCode() ?? '')
            ->setCountryCode($location->getCountry()?->getCode() ?? '')
            ->setCountry($location->getCountry()?->getName() ?? '')
            ->setLatitude($location->getCoordinates()?->getLatitude() ?? 0.0)
            ->setLongitude($location->getCoordinates()?->getLongitude() ?? 0.0)
            ;

            return $result;
    }
}
