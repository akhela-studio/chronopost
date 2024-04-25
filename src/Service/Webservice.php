<?php
/**
 * Chronopost Webservice methods
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @package    Chronopost
 * @subpackage Chronopost/includes
 * @author     Adexos <contact@adexos.fr>
 */

namespace Chronopost\Service;

use Chronopost\Helper\Options;
use SoapClient;

class Webservice
{
    private $product_to_code = [
        'chrono13'=>'9A',
        'chrono10'=>'9B',
        'chrono18'=>'9C',
        'shop2shop'=>'5E',
        'chronorelaiseurope'=>'49',
        'chronorelais13'=>'86',
        'chronoexpress'=>'9D',
        'chronorelaisdom'=>'4P',
        'chronoclassic'=>'9F'
    ];

    /**
     * @param $args
     * @return array|\WP_Error
     */
    public function getRelayPointsByAddress($args=[])
	{
		if (!$args['shipping_method']??false)
			return [];

        $zipCode = sanitize_text_field($args['zip_code']??'');
        $city = sanitize_text_field($args['city']??'');
        $address = sanitize_text_field($args['address']??'');

        $countryDomCode = $this->getCountryDomCode();

        $countryId = sanitize_text_field($args['country_code']??'');
        $countryId = $countryDomCode[$countryId]??$countryId;

		try {

			$RelayPointWs = 'https://www.chronopost.fr/recherchebt-ws-cxf/PointRelaisServiceWS?wsdl';
            $RelayPointWsMethod = 'recherchePointChronopost';
			$addAddressToWs = 1;

			if ( in_array($args['shipping_method'], ['chronorelaiseurope', 'chronorelaisdom']) ) {

                $RelayPointWsMethod = 'recherchePointChronopostInter';
				$addAddressToWs = 0;
			}

			$maxPickupRelayNumber = intval($args['max_pickup_relay_number']??Options::get('max_pickup_relay_number', 10));
			$maxDistanceSearch = intval($args['max_distance_search']??Options::get('max_distance_search', 1));
			$weight = intval($args['weight']??Options::get('default_weight', 2000));
			$holidayTolerant = boolval($args['holiday_tolerant']??1);
            $productCode = $this->product_to_code[$args['shipping_method']]??$this->product_to_code['chronorelais13'];

			$client = new SoapClient( $RelayPointWs, [
                'trace'              => 0,
                'connection_timeout' => 10,
            ]);

			$dropoffType = $args['dropoff']??'P';

            if ($args['shipping_method'] === 'chronotoshopdirect' || $args['shipping_method'] === 'chronotoshopeurope')
                $dropoffType = 'C';

			$params = [
				'accountNumber'      => Options::get('account_number'),
				'password'           => Options::get('account_password'),
				'zipCode'            => $zipCode,
				'city'               => $city,
				'countryCode'        => $countryId,
				'type'               => $dropoffType,
				'productCode'        => $productCode,
				'service'            => 'T',
				'weight'             => $weight,
				'shippingDate'       => date('d/m/Y'),
				'maxPointChronopost' => $maxPickupRelayNumber,
				'maxDistanceSearch'  => $maxDistanceSearch,
				'holidayTolerant'    => $holidayTolerant
			];

			if ($addAddressToWs)
				$params['address'] = $address;

			$webservbt = $client->$RelayPointWsMethod($params);

			if ($webservbt->return->errorCode == 0 && isset($webservbt->return->listePointRelais)) {

				$listePr = [];

				if (isset($webservbt->return->listePointRelais)) {
					$listePr = $webservbt->return->listePointRelais;
					if (count($webservbt->return->listePointRelais) == 1) {
						$listePr = [$listePr];
					}
				}

                $data = [];

				foreach ($listePr as $pr) {

                    $data[] = $this->formatRelayPoint($pr);
				}

				return $data;
			}
            else{

                return new \WP_Error($webservbt->return->errorCode, $webservbt->return->errorMessage);
            }

		} catch (\Exception $e) {

            return new \WP_Error($e->getCode(), $e->getMessage());
		}
	}

    /**
     * @param $pr
     * @return array
     */
    protected function formatRelayPoint($pr){

        $data = [
            'id'            => $pr->identifiant,
            'active'        => $pr->actif,
            'prm'           => $pr->accesPersonneMobiliteReduite,
            'distance'      => $pr->distanceEnMetre,
            'max_weight'    => $pr->poidsMaxi,
            'type'          => $pr->typeDePoint,
            'address_1'     => $pr->adresse1,
            'address_2'     => $pr->adresse2??'',
            'address_3'     => $pr->adresse3??'',
            'zip_code'      => $pr->codePostal,
            'lat_lng'       => [$pr->coordGeolocalisationLatitude, $pr->coordGeolocalisationLongitude],
            'city'          => $pr->localite,
            'name'          => $pr->nom,
            'opening_hours' => [
                'monday' => 'closed',
                'tuesday' => 'closed',
                'wednesday' => 'closed',
                'thursday' => 'closed',
                'friday' => 'closed',
                'saturday' => 'closed',
                'sunday' => 'closed',
            ],
        ];

        foreach ($pr->listeHoraireOuverture as $opening_hours) {
            switch ($opening_hours->jour) {
                case '1':
                    $data['opening_hours']['monday'] = $opening_hours->horairesAsString;
                    break;
                case '2':
                    $data['opening_hours']['tuesday'] = $opening_hours->horairesAsString;
                    break;
                case '3':
                    $data['opening_hours']['wednesday'] = $opening_hours->horairesAsString;
                    break;
                case '4':
                    $data['opening_hours']['thursday'] = $opening_hours->horairesAsString;
                    break;
                case '5':
                    $data['opening_hours']['friday'] = $opening_hours->horairesAsString;
                    break;
                case '6':
                    $data['opening_hours']['saturday'] = $opening_hours->horairesAsString;
                    break;
                case '7':
                    $data['opening_hours']['sunday'] = $opening_hours->horairesAsString;

                    break;
                default:
                    break;
            }
        }

        return $data;
    }

	protected function getCountryDomCode()
	{
		return array(
			'RE' => 'REU',
			'MQ' => 'MTQ',
			'GP' => 'GLP',
			'MX' => 'MYT',
			'GF' => 'GUF',
		);
	}

    /**
     * @param $id
     * @return array|\WP_Error
     */
    public function getRelayPoint($id)
	{
		try {

			$params = [
				'accountNumber' => Options::get('account_number'),
				'password'      => Options::get('account_password'),
				'identifiant'   => $id
			];

			$client = new SoapClient('https://www.chronopost.fr/recherchebt-ws-cxf/PointRelaisServiceWS?wsdl');
			$webservbt = $client->rechercheDetailPointChronopost($params);

			if ($webservbt->return->errorCode == 0) {

                return $this->formatRelayPoint($webservbt->return->listePointRelais);

			} else {

                return new \WP_Error($webservbt->return->errorCode, $webservbt->return->errorMessage);
			}

		} catch (\Exception $e) {

            return new \WP_Error($e->getCode(), $e->getMessage());
		}
	}
}
