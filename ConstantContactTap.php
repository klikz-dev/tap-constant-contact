<?php

use GuzzleHttp\Client;
use SingerPhp\SingerTap;
use SingerPhp\Singer;

class ConstantContactTap extends SingerTap
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * The base URL of the Constant Contact Auth token generation
     * @var string
     */
    const BASE_AUTH_URL = 'https://authz.constantcontact.com/oauth2/default/v1/token';

    /**
     * CTCT PHP SDK Client
     * @var object
     */
    private $client = null;

    /**
     * Client ID (API Key)
     * @var string
     */
    private $client_id = '';

    /**
     * Client Secret
     * @var string
     */
    private $client_secret = '';

    /**
     * Access Token
     * @var string
     */
    private $access_token = '';

    /**
     * Refresh Token
     * @var string
     */
    private $refresh_token = '';

    /**
     * Tests if the connector is working then writes the results to STDOUT
     */
    public function test()
    {
        $this->client_id     = $this->singer->config->input('client_id');
        $this->client_secret = $this->singer->config->input('client_secret');
        $this->access_token  = $this->singer->config->input('access_token');
        $this->refresh_token = $this->singer->config->input('refresh_token');

        try {
            $this->initializeClient();

            $this->singer->writeMeta(['test_result' => true]);
        }
        catch(Exception $e) {
            $this->singer->writeMeta(['test_result' => false]);
        }
    }

    /**
     * Gets all schemas/tables and writes the results to STDOUT
     */
    public function discover()
    {
        $this->singer->logger->debug('Starting discover for tap Constant Contact');

        $this->client_id     = $this->singer->config->setting('client_id');
        $this->client_secret = $this->singer->config->setting('client_secret');
        $this->access_token  = $this->singer->config->setting('access_token');
        $this->refresh_token = $this->singer->config->setting('refresh_token');

        $this->initializeClient();

        foreach ($this->singer->config->catalog->streams as $stream) {
            $table = strtolower($stream->stream);

            $cursor = $this->useClient($table);

            $columns = [];
            $key_properties = [];

            $response       = $cursor->get();
            $response_key   = $this->table_map[$table]['response_key'];
            $records        = $response[$response_key];

            if ( ! empty($records) ) {
                foreach($records[0] as $key => $value) {
                    $columns[$key] = [
                        'type' => $this->getColumnType($value)
                    ];
                }

                $key_properties = $this->table_map[$table]['key_properties'];
            }

            $this->singer->writeSchema(
                stream: $table,
                schema: $columns,
                key_properties: $key_properties
            );
        }
    }

    /**
     * Gets the record data and writes to STDOUT
     */
    public function tap()
    {
        $this->singer->logger->debug('Starting sync for tap Constant Contact');

        $this->client_id     = $this->singer->config->setting('client_id');
        $this->client_secret = $this->singer->config->setting('client_secret');
        $this->access_token  = $this->singer->config->setting('access_token');
        $this->refresh_token = $this->singer->config->setting('refresh_token');

        $this->initializeClient();

        foreach ($this->singer->config->catalog->streams as $stream) {
            $table = strtolower($stream->stream);

            $cursor = $this->useClient($table);

            $columns = [];
            $key_properties = [];

            $response       = $cursor->get();
            $response_key   = $this->table_map[$table]['response_key'];
            $records        = $response[$response_key];

            // Full Replace //
            $this->singer->logger->debug("Starting discover for {$table}");
            if ( ! empty($records) ) {
                foreach($records[0] as $key => $value) {
                    $columns[$key] = [
                        'type' => $this->getColumnType($value)
                    ];
                }

                $key_properties = $this->table_map[$table]['key_properties'];
            }

            $this->singer->writeSchema(
                stream: $table,
                schema: $columns,
                key_properties: $key_properties
            );
            $this->singer->logger->debug("Finished discover for {$table}");
            /////////////////

            $this->singer->logger->debug("Starting sync for {$table}");

            $total_records = 0;
            do {
                foreach ($records as $record) {
                    $this->singer->writeRecord(
                        stream: $table,
                        record: $record
                    );
                    $total_records++;
                }

                $response = $cursor->next();
                $records  = isset($response[$response_key]) ? $response[$response_key] : [];
            } while (! empty($records));

            $this->singer->writeMetric(
                'counter',
                'record_count',
                $total_records,
                [
                    'table' => $table
                ]
            );

            $this->singer->logger->debug("Finished sync for {$table}");
        }
    }

    /**
     * Writes a metadata response with the tables to STDOUT
     */
    public function getTables()
    {
        $tables = array_values(array_keys($this->table_map));
        $this->singer->writeMeta(compact('tables'));
    }

    /**
     * Initialize CTCT PHP SDK Client
     */
    public function initializeClient()
    {
        $this->client = new \PHPFUI\ConstantContact\Client(
            $this->client_id, 
            $this->client_secret, 
            rtrim(getenv("ORCHESTRATION_URL")) . '/OAuth/callback'
        );

        $this->client->accessToken  = $this->access_token;
        $this->client->refreshToken = $this->refresh_token;
    }

    /**
     * Use ConstantContact Class
     * 
     * @param  string   $table   The name of the table (CTCT Class)
     * @return object
     */
    public function useClient($table)
    {
        // Refresh the token on a regular basis to avoid reauthorization. Recommended by CTCT.
        $this->client->refreshToken();

        switch ($table) {
            case 'activities':
                return new \PHPFUI\ConstantContact\V3\Activities($this->client);

            case 'contact_custom_fields':
                return new \PHPFUI\ConstantContact\V3\ContactCustomFields($this->client);

            case 'contact_lists':
                return new \PHPFUI\ConstantContact\V3\ContactLists($this->client);

            case 'contacts':
                return new \PHPFUI\ConstantContact\V3\Contacts($this->client);

            case 'contact_tags':
                return new \PHPFUI\ConstantContact\V3\ContactTags($this->client);

            case 'campaigns':
                return new \PHPFUI\ConstantContact\V3\Emails($this->client);

            case 'segments':
                return new \PHPFUI\ConstantContact\V3\Segments($this->client);

            default:
                throw new Exception("An invalid table name {$table} was provided.");
        }
    }

    /**
     * Get a refresh token
     */
    public function getRefreshToken()
    {
        $this->client_id     = $this->singer->config->input('client_id');
        $this->client_secret = $this->singer->config->input('client_secret');
        $code                = $this->singer->config->input('access_token');

        $client = new GuzzleHttp\Client();
        $response = $client->request(
            'POST',
            self::BASE_AUTH_URL . '?grant_type=authorization_code&code=' . $code . '&redirect_uri=' . rtrim(getenv("ORCHESTRATION_URL")) . '/OAuth/callback',
            [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'http_errors' => false
            ]
        );

        $status_code = $response->getStatusCode();

        switch ($status_code) {
            case 200:
                $token = json_decode((string) $response->getBody(), true);
                $this->singer->writeMeta(['refresh_token' => ['refresh_token' => $token['refresh_token'], 'access_token' => $token['access_token']]]);
                break;
            case 400:
                throw new Exception("The authorization code is invalid or has expired.");
            default:
                throw new Exception("An error occurred trying to get an access token. Expected 200 but received '{$status_code}'");
        }
    }

    /**
     * Attempt to determine the data type of a column based on its value
     *
     * @param  mixed  $value The value of the column
     * @return string The postgres friendly data type to be used in the column definition
     */
    public function getColumnType($value)
    {
        $types = [
            'array'         => Singer::TYPE_ARRAY,
            'boolean'       => Singer::TYPE_BOOLEAN,
            'integer'       => Singer::TYPE_INTEGER,
            'float'         => Singer::TYPE_FLOAT,
            'null'          => Singer::TYPE_STRING,
            'object'        => Singer::TYPE_OBJECT,
            'string'        => Singer::TYPE_STRING,
            'timestamp'     => Singer::TYPE_TIMESTAMP,
            'timestamp-tz'  => Singer::TYPE_TIMESTAMPTZ,
        ];

        $type = strtolower(gettype($value));

        return array_key_exists($type, $types) ? $types[$type] : Singer::TYPE_STRING;
    }

    /**
     * Table structures
     *
     * @var array
     */
    private $table_map = [
        'activities' => [
            'response_key' => 'activities',
            'key_properties' => [
                'activity_id'
            ],
        ],
        'contact_custom_fields' => [
            'response_key' => 'custom_fields',
            'key_properties' => [
                'custom_field_id'
            ],
        ],
        'contact_lists' => [
            'response_key' => 'lists',
            'key_properties' => [
                'list_id'
            ],
        ],
        'contacts' => [
            'response_key' => 'contacts',
            'key_properties' => [
                'contact_id'
            ],
        ],
        'contact_tags' => [
            'response_key' => 'tags',
            'key_properties' => [
                'tag_id'
            ],
        ],
        'campaigns' => [
            'response_key' => 'campaigns',
            'key_properties' => [
                'campaign_id'
            ],
        ],
        'segments' => [
            'response_key' => 'segments',
            'key_properties' => [
                'segment_id'
            ],
        ],
    ];
}
