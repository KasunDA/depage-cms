<?php

require_once("framework/Depage/Runner.php");
// TODO: convert to autoloader
require_once("framework/WebSocket/lib/WebSocket/Application/Application.php");

class JsTreeApplication extends \Websocket\Application\Application {
    private $clients = array();
    private $delta_updates = array();
    protected $defaults = array(
        "db" => null,
        "auth" => null,
        'env' => "development",
        'timezone' => "UST",
    );

    function __construct() {
        parent::__construct();

        $conf = new \Depage\Config\Config();
        $conf->readConfig(__DIR__ . "/../../../conf/dpconf.php");
        $this->options = $conf->getFromDefaults($this->defaults);

        // get database instance
        $this->pdo = new \Depage\Db\Pdo (
            $this->options->db->dsn, // dsn
            $this->options->db->user, // user
            $this->options->db->password, // password
            array(
                'prefix' => $this->options->db->prefix, // database prefix
            )
        );

        // TODO: set project correctly
        $proj = "proj";
        $this->prefix = "{$this->pdo->prefix}_{$proj}";
        $this->xmldb = new \Depage\XmlDb\XmlDb ($this->prefix, $this->pdo, \Depage\Cache\Cache::factory($this->prefix, array(
            'disposition' => "redis",
            'host' => "127.0.0.1:6379",
        )));

        /* get auth object
        $this->auth = \Depage\Auth\Auth::factory(
            $this->pdo, // db_pdo
            $this->options->auth->realm, // auth realm
            DEPAGE_BASE, // domain
            $this->options->auth->method // method
        ); */
    }

    public function onConnect($client)
    {
        // TODO: authentication ? beware of timeouts

        if (empty($this->clients[$client->param])) {
            $this->clients[$client->param] = array();
            $this->delta_updates[$client->param] = new \Depage\WebSocket\JsTree\DeltaUpdates($this->prefix, $this->pdo, $this->xmldb, $client->param);
        }

        $this->clients[$client->param][] = $client;
    }

    public function onDisconnect($client)
    {
        $key = array_search($client, $this->clients[$client->param]);
        if ($key) {
            unset($this->clients[$client->param][$key]);

            if (empty($this->clients[$client->param])) {
                unset($this->delta_updates[$client->param]);
            }
        }
    }

    public function onTick() {
        foreach ($this->clients as $doc_id => $clients) {
            $data = $this->delta_updates[$doc_id]->encodedDeltaUpdate();

            if (!empty($data)) {
                // send to clients
                foreach ($clients as $client) {
                    $client->send($data);
                }
            }
        }

        // do not sleep too long, this impacts new incoming connections
        usleep(50 * 1000);
    }

    public function onData($raw_data, $client)
    {
        // do nothing, only send data in onTick() because fallback clients do not support websockets
    }
}

?>
