<?php

/**
 * JSKOS-API Wrapper to ORCID.
 */

include 'LuceneTrait.php';

use JSKOS\Service;
use JSKOS\Concept;
use JSKOS\Page;
use JSKOS\URISpaceService;

class ORCIDService extends Service {
    use LuceneTrait;

    protected $supportedParameters = ['notation','search'];

    private $uriSpaceService;

    private $client_id;
    private $client_secret;

    public function __construct($client_id, $client_secret) {
        $this->uriSpaceService = new URISpaceService([
            'Concept' => [
                'uriSpace'        => 'http://orcid.org/',
                'notationPattern' => '/^(\d\d\d\d-){3}\d\d\d[0-9X]$/'
            ]
        ]);
        $this->client_id     = $client_id;
        $this->client_secret = $client_secret;
        parent::__construct();
    }

    // Get an OAuth access token
    protected function getOAuthToken() {
        # TODO: use session to store the token
        if ($this->client_id and $this->client_secret) {
            $body = Unirest\Request\Body::form(
                [ 
                  'client_id' => $this->client_id, 
                  'client_secret' => $this->client_secret,
                  'scope' => '/read-public',
                  'grant_type' => 'client_credentials',
                ]
            );
            $response = Unirest\Request::post(
                'https://orcid.org/oauth/token', 
                [ 'Accept' => 'application/json' ],
                $body
            );
            if ($response->code == 200) {
                return $response->body->{'access_token'};
            }
        }
    }

    // main method
    public function query($request) {

        // search for ORCID profiles
        if (isset($request['search'])) {
            $result = $this->searchProfiles($request['search']);
            if ($result) {
                $concepts = [];
                foreach ($result->{'orcid-search-result'} as $bio) {
                    $concepts[] = $this->mapProfile($bio->{'orcid-profile'});
                }
                return new Page($concepts,0,1,$result->{'num-found'});
            }
        }

        // get ORCID profile by ORCID ID or ORCID URI
        $jskos = $this->uriSpaceService->query($request);
        if ($jskos && $jskos->notation[0]) {
            $profile = $this->getProfile($jskos->notation[0]);
            $jskos = $this->mapProfile($profile);
            return $jskos;
        }

    }

    // get an indentified profile by ORCID ID
    protected function getProfile( $id )
    { 
        $token = $this->getOAuthToken();
        if (!$token) return;

        $response = Unirest\Request::get(
            "https://pub.orcid.org/v1.2/$id/orcid-bio/",
            [
                'Authorization' => "Bearer $token",
                'Content-Type' => 'application/orcid+json'
            ]
        );

        if ($response->code == 200) {
            return $response->body->{'orcid-profile'};
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
            [ 'q' => LuceneTrait::luceneQuery('text',$query) ]
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


