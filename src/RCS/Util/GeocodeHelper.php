<?php
declare(strict_types = 1);
namespace RCS\Util;

use Geocoder\Location;
use Geocoder\Provider\Provider;
use Geocoder\Provider\GoogleMaps\GoogleMaps;
use Geocoder\Query\GeocodeQuery;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;

/**
 * Helper class for using the Google Geocode API.
 */
class GeocodeHelper
{
    private ?Provider $geocodeProvider = null;

    /**
     *
     * @param string $apiKey The Google Maps API key to use in accessing the
     *      Geocode API.
     * @param LoggerInterface $logger Optional. If provided, will be used for
     *      logging any errors that occur.
     */
    public function __construct(
        private string $apiKey,
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
     * Fetch Geocode Provider.
     *
     * This function allows the creating of the HTTP client once during a
     * request for use multiple times.
     *
     * @return Provider|null
     */
    private function getGeocodeProvider(): ?Provider
    {
        if (!$this->geocodeProvider) {
            $config = [
                'timeout' => 2.0,
                'verify' => false
            ];

            try {
                $client = new Client($config);
                $this->geocodeProvider = new GoogleMaps($client, null, $this->apiKey);
            } catch (\Exception $e) {
                if ($this->logger) {
                    $this->logger->critical('Unable to create Geocode Provider: ' . $e->getMessage());
                }
            }
        }

        return $this->geocodeProvider;
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
            $geocoder = $this->getGeocodeProvider();

            if ($geocoder) {
                $queryResult = $geocoder->geocodeQuery(GeocodeQuery::create($location));

                if (!$queryResult->isEmpty()) {
                    $result = $queryResult->first();
                }
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
            ->setLatitude($location->getCoordinates()?->getLatitude() ?? '')
            ->setLongitude($location->getCoordinates()?->getLongitude() ?? '')
            ;

            return $result;
    }
}
