<?php

namespace Depage\Fs;

class FsSsh extends Fs
{
    // {{{ variables
    protected $session = null;
    protected $connection = null;
    protected $privateKeyFile = null;
    protected $publicKeyFile = null;
    protected $privateKey = null;
    protected $publicKey = null;
    protected $fingerprint = null;
    protected $tmp = null;
    // }}}
    // {{{ constructor
    public function __construct($params = array())
    {
        parent::__construct($params);
        $this->privateKeyFile = (isset($params['privateKeyFile'])) ? $params['privateKeyFile'] : false;
        $this->publicKeyFile = (isset($params['publicKeyFile'])) ? $params['publicKeyFile'] : false;
        $this->privateKey = (isset($params['privateKey'])) ? $params['privateKey'] : false;
        $this->publicKey = (isset($params['publicKey'])) ? $params['publicKey'] : false;
        $this->tmp = (isset($params['tmp'])) ? $params['tmp'] : false;
        $this->fingerprint = (isset($params['fingerprint'])) ? $params['fingerprint'] : false;
    }
    // }}}
    // {{{ destructor
    public function __destruct()
    {
        $this->disconnect();
    }
    // }}}

    // {{{ lateConnect
    protected function lateConnect()
    {
        parent::lateConnect();
        $this->getSession();
    }
    // }}}
    // {{{ getFingerprint
    public function getFingerprint()
    {
        $this->getConnection($fingerprint);
        return $fingerprint;
    }
    // }}}
    // {{{ getConnection
    protected function getConnection(&$fingerprint = null)
    {
        if (!$this->connection) {
            if (isset($this->url['port'])) {
                $this->connection = ssh2_connect($this->url['host'], $this->url['port']);
            } else {
                $this->connection = ssh2_connect($this->url['host']);
            }
        }
        $fingerprint = ssh2_fingerprint($this->connection);

        return $this->connection;
    }
    // }}}
    // {{{ getSession
    protected function getSession()
    {
        if (!$this->session) {
            $connection = $this->getConnection($fingerprint);

            if (strcasecmp($this->fingerprint, $fingerprint) !== 0) {
                throw new Exceptions\FsException('SSH RSA Fingerprints don\'t match.');
            }

            if (
                $this->privateKeyFile
                || $this->publicKeyFile
                || $this->privateKey
                || $this->publicKey
                || $this->tmp
            ) {
                $authenticated = $this->authenticateByKey($connection);
            } else {
                $authenticated = $this->authenticateByPassword($connection);
            }

            if ($authenticated) {
                $this->session = ssh2_sftp($connection);
            } else {
                throw new Exceptions\FsException('Could not authenticate session.');
            }
        }

        return $this->session;
    }
    // }}}
    // {{{ authenticateByPassword
    protected function authenticateByPassword($connection)
    {
        return ssh2_auth_password(
            $connection,
            $this->url['user'],
            $this->url['pass']
        );
    }
    // }}}
    // {{{ authenticateByKey
    protected function authenticateByKey($connection)
    {
        if ($this->privateKeyFile) {
            $private = new PrivateSshKey($this->privateKeyFile);
        } elseif ($this->privateKey) {
            $private = new PrivateSshKey($this->privateKey, $this->tmp);
        }

        if ($this->publicKeyFile) {
            $public = new PublicSshKey($this->publicKeyFile);
        } elseif ($this->publicKey) {
            $public = new PublicSshKey($this->publicKey, $this->tmp);
        } else {
            $public = $private->extractPublicKey($this->tmp);
        }

        $authenticated = ssh2_auth_pubkey_file(
            $connection,
            $this->url['user'],
            $public,
            $private,
            $this->url['pass']
        );

        $private->clean();
        $public->clean();

        return $authenticated;
    }
    // }}}
    // {{{ disconnect
    protected function disconnect()
    {
        $this->connection = null;
        $this->session = null;
    }
    // }}}
    // {{{ buildUrl
    protected function buildUrl($parsed)
    {
        $path = $parsed['scheme'] . '://';
        $path .= $this->getSession();
        $path .= isset($parsed['path']) ? $parsed['path'] : '/';

        return $path;
    }
    // }}}
}

/* vim:set ft=php sw=4 sts=4 fdm=marker : */
