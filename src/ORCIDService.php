<?php declare(strict_types=1);

/**
 * JSKOS-API Wrapper to ORCID.
 */

use JSKOS\Service;
use JSKOS\Concept;
use JSKOS\Result;
use JSKOS\URISpaceService;

use Http\Client\HttpClient;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;

/**
 * Escape special characters used in Lucene Query Parser Syntax.
 */
function luceneQuery($field, $query) {
    $query = preg_replace(
        '/([*+&|!(){}\[\]^"~*?:\\-])/',
        '\\\\$1',
        $query
    );
    return "$field:\"$query\"";
}

class ORCIDService extends Service {

    protected $supportedParameters = ['notation','search'];

    private $uriSpaceService;

    private $client_id;
    private $client_secret;

	protected $httpClient;
    protected $requestFactory;

    public function __construct($client_id, $client_secret) {
        $this->uriSpaceService = new URISpaceService([
            'Concept' => [
                'uriSpace'        => 'http://orcid.org/',
                'notationPattern' => '/^(\d\d\d\d-){3}\d\d\d[0-9X]$/'
            ]
        ]);
        $this->client_id     = $client_id;
        $this->client_secret = $client_secret;
		$this->requestFactory = MessageFactoryDiscovery::find();
		$this->httpClient = HttpClientDiscovery::find();
    }

    // Get an OAuth access token
    protected function getOAuthToken() {
        # TODO: use session to store the token
        if ($this->client_id and $this->client_secret) {
            $response = $this->httpQuery(
                'POST', 'https://orcid.org/oauth/token',
                [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                http_build_query([
                  'client_id' => $this->client_id,
                  'client_secret' => $this->client_secret,
                  'scope' => '/read-public',
                  'grant_type' => 'client_credentials',
                ])
            );

            if ($response) {
                return $response->{'access_token'};
            }
        }
    }

    protected function httpQuery($method, $url, $header, $body=null) {
        $request = $this->requestFactory->createRequest($method, $url, $header, $body);
        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() == 200) {
            return json_decode((string)$response->getBody());
        }
    }

    // main method
    public function query(array $query, string $path=''): Result {

        // search for ORCID profiles
        if (isset($query['search'])) {
            $response = $this->searchProfiles($query['search']);
            if ($response) {
                $concepts = [];
                foreach ($response->{'orcid-search-response'} as $bio) {
                    $concepts[] = $this->mapProfile($bio->{'orcid-profile'});
                }

                // TODO: set totalCount to $response->{'num-found'}
                return new Result($concepts);
            }
        }

        // get ORCID profile by ORCID ID or ORCID URI
        $result = $this->uriSpaceService->query($query);

        if (count($result) && $result[0]->notation[0]) {
            $profile = $this->getProfile($result[0]->notation[0]);
            $jskos = $this->mapProfile($profile);
            $result = new Result($jskos ? [ $jskos ] : []);
        }

        return $result;
    }

    // get an indentified profile by ORCID ID
    protected function getProfile( $id )
    {
        $token = $this->getOAuthToken();
        if (!$token) return;

        $response = $this->httpQuery(
            'GET',
            "https://pub.orcid.org/v1.2/$id/orcid-bio/",
            [
                'Authorization' => "Bearer $token",
                'Accept' => 'application/json',
            ]
        );

        if ($response) {
            return $response->{'orcid-profile'};
        }
    }

    // search for an ORCID profile
    protected function searchProfiles( $query )
    {
        $token = $this->getOAuthToken();
        if (!$token) return;

        $response = Unirest\Request::get(
            "https://pub.orcid.org/v1.2/search/orcid-bio/",
            [
                'Authorization' => "Bearer $token",
                'Content-Type' => 'application/orcid+json'
            ],
            # TODO: search in names only and use boosting
            [ 'q' => luceneQuery('text',$query) ]
        );

        if ($response->code == 200) {
            return $response->body->{'orcid-search-results'};
        }
    }

    /**
     * Maps an ORCID profile from JSON/XML format to JSKOS.
     *
     * See <https://members.orcid.org/api/xml-orcid-bio> for reference.
     */
    public function mapProfile($profile) {
        if (!$profile) return;

        $jskos = new Concept([
            'uri' => $profile->{'orcid-identifier'}->uri,
            'notation' => [ $profile->{'orcid-identifier'}->path ],
        ]);

        $bio = $profile->{'orcid-bio'};

        // names
        $details = $bio->{'personal-details'};

        $otherNames = [];
        $name = $details->{'given-names'}->value; # required

        if (isset($details->{'family-name'})) {
            $name = "$name " . $details->{'family-name'}->value;
        }

        if (isset($details->{'credit-name'})) {
            $creditName = $details->{'credit-name'}->value;
            if ($creditName != $name) {
                $otherNames[] = $name;
                $name = $creditName;
                # TODO: scopus id, researser ID etc.
            }
        }

        $jskos->prefLabel = ['en' => $name ];

        if (isset($details->{'other-names'})) {
            foreach ( $details->{'other-names'}->{'other-name'} as $otherName ) {
                if ($otherName->value != $name) {
                    $otherNames[] = $otherName->value;
                }
            }
        }

        $jskos->altLabel = ['en' => $otherNames];

        // biography
        if ($bio->biography && strtolower($bio->biography->visibility) == 'public') {
            $jskos->description['en'] = $bio->biography->value;
        }

        // external website links
        if ($bio->{'researcher-urls'}) {
            foreach ($bio->{'researcher-urls'}->{'researcher-url'} as $url) {
                $name = $url->{'url-name'}->value;
                $url  = $url->url->value;

                if (preg_match('/^https?:\/\/\w+\.wikipedia.org\/wiki\/.+/i', $url)) {
                    $jskos->subjectOf[] = new Concept([
                        "url" => $url
                    ]);
                    # TOOD: map to Wikidata URI
                } else { # TODO: detect more type of known URLs, for instance Twitter
                    $jskos->url = $url;
                }
            }
        }

        // contact details
        # TODO (email and address)

        // keywords
        if ($bio->keywords and $bio->keywords->keyword) {
            foreach ($bio->keywords->keyword as $keywords) {
                // often wrongly split by comma
                foreach (preg_split('/,\s*/', $keywords->value) as $keyword) {
                    $jskos->subject[] = new Concept([
                        "prefLabel" => [ "en" => $keyword ]
                    ]);
                }
            }
        }

        // external identifiers
        # TODO: scopus id, researser ID etc.

        return $jskos;
    }
}
