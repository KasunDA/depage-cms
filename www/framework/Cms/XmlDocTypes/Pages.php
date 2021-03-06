<?php

namespace Depage\Cms\XmlDocTypes;

// TODO configure

class Pages extends Base {
    use Traits\UniqueNames;

    const XML_TEMPLATE_DIR = __DIR__ . '/PagesXml/';

    /**
     * @brief routeHtmlThroughPhp
     **/
    protected $routeHtmlThroughPhp = false;

    // {{{ constructor
    public function __construct($xmlDb, $document) {
        parent::__construct($xmlDb, $document);

        $this->routeHtmlThroughPhp = $this->project->getProjectConfig()->routeHtmlThroughPhp;

        // list of elements that may created by a user
        $this->availableNodes = [
            'pg:page' => (object) [
                'name' => _("Page"),
                'new' => _("(Untitled Page)"),
                'icon' => "",
                'attributes' => [],
                'doc_type' => 'Depage\Cms\XmlDocTypes\Page',
                'xml_template' => 'page.xml'
            ],
            'pg:folder' => (object) [
                'name' => _("Folder"),
                'new' => _("(Untitled Folder)"),
                'icon' => "",
                'attributes' => [],
                'doc_type' => 'Depage\Cms\XmlDocTypes\Folder',
                'xml_template' => 'folder.xml',
            ],
            'pg:redirect' => (object) [
                'name' => _("Redirect"),
                'new' => _("Redirect"),
                'icon' => "",
                'attributes' => [],
                'doc_type' => 'Depage\Cms\XmlDocTypes\Page',
                'xml_template' => 'redirect.xml',
            ],
            'sec:separator' => (object) [
                'name' => _("Separator"),
                'new' => "",
                'icon' => "",
                'attributes' => [],
            ],
        ];

        // list of valid parents given by nodename
        $this->validParents = [
            'pg:page' => [
                'dpg:pages',
                'proj:pages_struct',
                'pg:page',
                'pg:folder',
                'pg:redirect',
            ],
            'pg:folder' => [
                'dpg:pages',
                'proj:pages_struct',
                'pg:page',
                'pg:folder',
                'pg:redirect',
            ],
            'pg:redirect' => [
                'dpg:pages',
                'proj:pages_struct',
                'pg:page',
                'pg:folder',
                'pg:redirect',
            ],
            'sec:separator' => [
                '*',
            ],
        ];
    }
    // }}}

    // {{{ onAddNode
    /**
     * On Add Node
     *
     * @param \DomNode $node
     * @param $target_id
     * @param $target_pos
     * @param $extras
     * @return null
     */
    public function onAddNode(\DomNode $node, $target_id, $target_pos, $extras) {
        if (isset($this->availableNodes[$node->nodeName])) {
            $properties = $this->availableNodes[$node->nodeName];

            if (!empty($properties->new)) {
                $node->setAttribute("name", $properties->new);
            }
            if (isset($properties->doc_type) && isset($properties->xml_template)) {
                $doc = $this->xmlDb->createDoc($properties->doc_type);
                $xml = $this->loadXmlTemplate($properties->xml_template);

                $docId = $doc->save($xml);
                $info = $doc->getDocInfo();
                $node->setAttribute('db:docref', $info->name);

                if (isset($extras['dataNodes'])) {
                    // add doc data to page data doc
                    foreach ($extras['dataNodes'] as $dataNode) {
                        $doc->addNode($dataNode, $info->rootid);
                    }
                }

                return $docId;
            }
        }

        return false;
    }
    // }}}
    // {{{ onCopyNode
    /**
     * On Copy Node
     *
     * @param \DomElement $node
     * @param $target_id
     * @param $target_pos
     * @return null
     */
    public function onCopyNode($node_id, $copy_id) {
        // get all copied nodes
        $copiedXml = $this->document->getSubdocByNodeId($copy_id, true);
        $xpath = new \DOMXPath($copiedXml);
        $xpath->registerNamespace("db", "http://cms.depagecms.net/ns/database");

        $xp_result = $xpath->query("./descendant-or-self::node()[@db:docref]", $copiedXml);

        foreach ($xp_result as $node) {
            // get node ids and docrefids
            $nodeId = $node->getAttributeNS("http://cms.depagecms.net/ns/database", "id");
            $docrefId = $node->getAttributeNS("http://cms.depagecms.net/ns/database", "docref");

            // duplicate document as new
            $copiedDoc = $this->xmlDb->duplicateDoc($docrefId);
            $info = $copiedDoc->getDocInfo();

            // reset release state for copied document
            $copiedDoc->setAttribute($info->rootid, "db:released", "false");

            $this->document->setAttribute($nodeId, "db:docref", $info->name);
        }

        return true;
    }
    // }}}
    // {{{ onDeleteNode()
    /**
     * On Delete Node
     *
     * Deletes an xmlDb document by the given id.
     *
     * @param $doc_id
     * @return boolean
     */
    public function onDeleteNode($node_id, $parent_id)
    {
        // @todo check wether to delete attached documents directly or later
        //$this->xmlDb->removeDoc($doc_id);
        return true;
    }
    // }}}

    // {{{ testDocument
    public function testDocument($node) {
        $changed = $this->testUniqueNames($node, "//proj:pages_struct | //pg:*");

        $xmlnav = new \Depage\Cms\XmlNav();
        $xmlnav->routeHtmlThroughPhp = $this->routeHtmlThroughPhp;
        $xmlnav->addUrlAttributes($node);

        $this->addReleaseStatusAttributes($node);

        return $changed;
    }
    // }}}
    // {{{ testDocumentForHistory
    public function testDocumentForHistory($xml) {
        parent::testDocumentForHistory($xml);

        $this->addReleaseStatusAttributes($xml, true);

        $xpath = new \DOMXPath($xml);

        // remove unreleased pages
        $unreleasedPages = $xpath->query("//pg:page[@db:released = 'false']");
        foreach ($unreleasedPages as $page) {
            $page->parentNode->removeChild($page);
        }

        // remove empty folders
        do {
            $emptyFolders = $xpath->query("//pg:folder[not(.//pg:page)]");
            foreach ($emptyFolders as $folder) {
                $folder->parentNode->removeChild($folder);
            }
        } while ($emptyFolders->length > 0);

        $xmlnav = new \Depage\Cms\XmlNav();
        $xmlnav->routeHtmlThroughPhp = $this->routeHtmlThroughPhp;
        $xmlnav->addUrlAttributes($xml);
    }
    // }}}
    // {{{ addReleaseStatusAttributes()
    /**
     * @brief addReleaseStatusAttributes
     *
     * @param mixed $
     * @return void
     **/
    public function addReleaseStatusAttributes($node, $getAnyVersion = false)
    {
        list($xml, $node) = \Depage\Xml\Document::getDocAndNode($node);

        $xpath = new \DOMXPath($xml);
        $pages = $xpath->query("//pg:page");

        foreach ($pages as $page) {
            $doc = $this->xmlDb->getDoc($page->getAttribute("db:docref"));
            if ($doc) {
                $info = $doc->getDocInfo();
                $versions = array_values($doc->getHistory()->getVersions(true, 1));

                if (count($versions) > 0 && ($getAnyVersion || $info->lastchange->getTimestamp() < $versions[0]->lastsaved->getTimestamp())) {
                    $page->setAttributeNS("http://cms.depagecms.net/ns/database", "db:released", "true");
                } else {
                    $page->setAttributeNS("http://cms.depagecms.net/ns/database", "db:released", "false");
                }
            }
        }
    }
    // }}}

    // {{{ loadXmlTemplate()
    /**
     * Load XML Template
     *
     * @param $template
     * @return \DOMDocument
     */
    private function loadXmlTemplate($template) {
        $doc = new \DOMDocument();
        $doc->load(self::XML_TEMPLATE_DIR . $template);
        return $doc;
    }
    // }}}
}

/* vim:set ft=php sw=4 sts=4 fdm=marker et : */
