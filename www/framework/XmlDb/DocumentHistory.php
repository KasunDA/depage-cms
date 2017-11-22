<?php
/**
 * @file    modules/xmlDb/History.php
 *
 * cms xmlDb module
 *
 * copyright (c) 2002-2014 Frank Hellenkamp [jonas@depage.net]
 *
 * @author   Ben Wallis
 */

namespace Depage\XmlDb;

class DocumentHistory
{
    // {{{ variables
    private $pdo;
    private $db_ns;
    private $table_history;

    private $document;
    private $dateFormat = 'Y-m-d H:i:s';
    // }}}
    // {{{ constructor
    public function __construct(\Depage\Db\Pdo $pdo, $table_prefix, Document $document) {
        $this->document = $document;

        $this->pdo = $pdo;

        $this->db_ns = new XmlNs('db', 'http://cms.depagecms.net/ns/database');

        $this->table_history = $table_prefix . '_history';
    }
    // }}}

    // {{{ getVersions
    /**
     * getVersions
     *
     * Gets versions of the docment in the history.
     * returns array of time, user_id, published, and hash per version in array indexed on the last change time.
     *
     * @param bool $published
     * @param int $maxResults
     *
     * @return mixed
     */
    public function getVersions($published = null, $maxResults = null) {
        $query = "SELECT h.hash, h.last_saved_at, h.user_id, h.published
            FROM {$this->table_history} AS h
            WHERE h.doc_id = :doc_id";

        $params = [
            'doc_id' => $this->document->getDocId(),
        ];

        if ($published !== null) {
            $query .= ' AND h.published = :published';
            $params['published'] = $published == true;
        }

        $query .= ' ORDER BY h.last_saved_at DESC';

        if ($maxResults > 0) {
            $query .= ' LIMIT :maxResults';
            $params['maxResults'] = $maxResults;
        }

        $query .= ';';

        $sth = $this->pdo->prepare($query);

        $versions = [];

        if ($sth->execute($params)) {
            $results = $sth->fetchAll();

            foreach($results as &$result) {
                $versions[strtotime($result['last_saved_at'])] = (object) [
                    'lastsaved' => new \DateTime($result['last_saved_at']),
                    'userId' => $result['user_id'],
                    'published' => $result['published'],
                    'hash' => $result['hash'],
                ];
            }
        }

        return $versions;
    }
    // }}}
    // {{{ getLatestVersion
    /**
     * getLatestVersion
     *
     * gets the last document version
     */
    public function getLatestVersion() {
        $versions = $this->getVersions(true, 1);

        return reset($versions);
    }
    // }}}
    // {{{ getXml
    /**
     * getXml
     *
     * @param int $timestamp
     * @param bool $add_id_attribute
     *
     * @return bool|\DOMDocument|object
     */
    public function getXml($timestamp, $add_id_attribute = true) {
        $doc = false;
        $docId = $this->document->getDocId();

        $query = $this->pdo->prepare(
            "SELECT
                h.xml,
                h.last_saved_at as lastchange,
                h.user_id as uid
            FROM {$this->table_history} AS h
            WHERE h.doc_id = :doc_id
                AND h.last_saved_at = :timestamp"
        );

        $params = [
            'doc_id' => $docId,
            'timestamp' => date($this->dateFormat, $timestamp),
        ];

        if ($query->execute($params) && $result = $query->fetchObject()) {
            $doc = new \Depage\Xml\Document();
            $doc->loadXML($result->xml);
            if (!$add_id_attribute) {
                Document::removeNodeAttr($doc, $this->db_ns, 'id');
            }
            $doc->documentElement->setAttribute('db:docid', $docId);
            $doc->documentElement->setAttribute('db:lastchange', $result->lastchange);
        }

        return $doc;
    }
    // }}}
    // {{{ getLastPublishedXml
    /**
     * getLastPublishedXml
     *
     * load xml from last published version from history
     */
    public function getLastPublishedXml() {
        $versions = $this->getVersions(true, 1);
        reset($versions);
        $latest = key($versions);

        return $this->getXml($latest);
    }
    // }}}

    // {{{ save
    /**
     * gets the current document xml and saves a version to the history
     * add SHA hash column for data integrity
     *
     * @param int $user_id
     * @param bool $published
     *
     * @return bool | timestamp
     */
    public function save($user_id, $published = false)
    {
        $result = false;
        $timestamp = time();

        $doc = $this->document->getXml();
        $dth = $this->document->getDoctypeHandler();
        Document::removeNodeAttr($doc, $this->db_ns, 'lastchange');
        Document::removeNodeAttr($doc, $this->db_ns, 'docid');

        $dth->testDocumentForHistory($doc);

        $xml = $doc->saveXml();
        $hash = sha1($xml);

        $latestVersion = $this->getLatestVersion();

        if (!$latestVersion || $latestVersion->hash != $hash) {
            $query = $this->pdo->prepare(
                "INSERT INTO {$this->table_history} (doc_id, hash, xml, last_saved_at, user_id, published)
                VALUES(:doc_id, :hash, :xml, :timestamp, :user_id, :published);"
            );

            $params = [
                'doc_id' => $this->document->getDocId(),
                'hash' => $hash,
                'xml' => $xml,
                'timestamp' => date($this->dateFormat, $timestamp),
                'user_id' => $user_id,
                'published' => $published,
            ];

            $query->execute($params);
            $dth->onHistorySave();

            $result = $timestamp;

        } else {
            $result = strtotime($latestVersion->lastsaved->getTimestamp());
        }

        return $result;
    }
    // }}}
    // {{{ restore
    /**
     * Restores the document to a previous state
     */
    public function restore($timestamp) {
        $success = false;
        $xml_doc = $this->getXml($timestamp);

        if ($this->document->save($xml_doc)) {
            $success = $xml_doc;
        }

        return $success;
    }
    // }}}
    // delete {{{
    /**
     * Delete
     *
     * @param $timestamp
     */
    public function delete($timestamp) {
        $query = $this->pdo->prepare(
            "DELETE FROM {$this->table_history}
             WHERE doc_id = :doc_id AND last_saved_at = :timestamp;"
        );

        $params = [
            'doc_id' => $this->document->getDocId(),
            'timestamp' => date($this->dateFormat, $timestamp),
        ];

        if ($query->execute($params)) {
            return $query->rowCount() > 0;
        }

        return false;
    }
    // }}}
}

/* vim:set ft=php sw=4 sts=4 fdm=marker et : */
