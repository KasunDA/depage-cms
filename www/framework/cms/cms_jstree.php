<?php
/**
 * @file    framework/cms/cms_jstree.php
 *
 * depage cms jstree module
 *
 *
 * copyright (c) 2011 Lion Vollnhals [lion.vollnhals@googlemail.com]
 *
 * @author    Lion Vollnhals [lion.vollnhals@googlemail.com]
 */

class cms_jstree extends depage_ui {
    protected $html_options = array();
    protected $defaults = array(
        "db" => null,
        "auth" => null,
        "env" => "development",
    );

    // {{{ constructor
    public function __construct($options = NULL) {
        parent::__construct($options);

        // get database instance
        $this->pdo = new db_pdo (
            $this->options->db->dsn, // dsn
            $this->options->db->user, // user
            $this->options->db->password, // password
            array(
                'prefix' => $this->options->db->prefix, // database prefix
            )
        );

        // TODO init correctly
        $this->prefix = "dp_proj_{$this->pdo->prefix}";
        $this->xmldb = new \depage\xmldb\xmldb ($this->prefix, $this->pdo, \depage\cache\cache::factory($this->prefix));

        // get auth object
        $this->auth = auth::factory(
            $this->pdo, // db_pdo 
            $this->options->auth->realm, // auth realm
            DEPAGE_BASE, // domain
            $this->options->auth->method // method
        );

        // set html-options
        $this->html_options = array(
            'template_path' => __DIR__ . "/tpl/",
            'clean' => "space",
            'env' => $this->options->env,
        );

//        $this->delta_updates = new delta_updates ("dp_proj_{$this->pdo->prefix}", $this->pdo);
    }
    // }}}

    // {{{ index
    public function index($doc_name = "pages") {
        // $this->auth->enforce();
        $doc_id = $this->get_doc_id($doc_name);

        $h = new html("jstree.tpl", array(
            'doc_id' => $doc_id,
            'seq_nr' => $this->get_current_seq_nr($doc_id),
            'nodes' => $this->get_html_nodes($doc_name),
        ), $this->html_options); 

        return $h;
    }
    // }}}

    // {{{ create_node
    /**
     * @param $doc_id document id
     * @param $parent parent
     * @param $child child
     * @param $position position for new child in parent
     */
    public function create_node() {
        $this->auth->enforce();

        $node = $this->node_from_request();
        $this->xmldb->add_node($_REQUEST["doc_id"], $node, null, $_REQUEST["position"]);   
    }
    // }}}

    // {{{ rename_node
    public function rename_node() {
        $this->auth->enforce();

        $this->xmldb->set_attribute($_REQUEST["doc_id"], $_REQUEST["id"], "name", $_REQUEST["name"]);
    }
    // }}}

    // {{{ move_node
    public function move_node() {
        //$this->auth->enforce();

        $this->xmldb->move_node($_REQUEST["doc_id"], $_REQUEST["id"], $_REQUEST["target_id"], $_REQUEST["position"]);
        $this->recordChange($_REQUEST["doc_id"], array($_REQUEST["id"]), array($_REQUEST["target_id"]));

        return new json(array("status" => 1));
    }
    // }}}

    // {{{ remove_node
    public function remove_node() {
        $this->auth->enforce();

        $this->xmldb->unlink_node($_REQUEST["doc_id"], $_REQUEST["id"]);
    }
    // }}}

    // {{{ recordChange
    private function recordChange($doc_id, $ids, $parent_ids) {
        $delta_updates = new \depage\websocket\jstree\jstree_delta_updates($this->prefix, $this->pdo, $this->xmldb, $doc_id);

        foreach ($ids as $id) {
            $parent_ids[] = $this->xmldb->get_parentId_by_elementId($doc_id, $id);
        }

        $unique_parent_ids = array_unique($parent_ids);
        foreach ($unique_parent_ids as $parent_id) {
            $delta_updates->recordChange($parent_id);
        }
    }
    // }}}

    // {{{ get_doc_id
    private function get_doc_id($doc_name) {
        $doc_list = $this->xmldb->get_doc_list($doc_name);
        return $doc_list[$doc_name]->id;
    }
    // }}}

    // {{{ get_html_nodes
    private function get_html_nodes($doc_name) {
        $doc = $this->xmldb->get_doc($doc_name);
        $html = \depage\cms\jstree_xml_to_html::toHTML(array($doc));

        return current($html);
    }
    // }}}

    // {{{ node_from_request
    private function node_from_request() {
        $parent = new DOMElement($_REQUEST["parent"]["type"]);
        foreach ($_REQUEST["parent"] as $attr => $value) {
            $parent->setAttribute($attr, $value);
        }

        $child = new DOMElement($_REQUEST["child"]["type"]);
        foreach ($_REQUEST["parent"] as $attr => $value) {
            $parent->setAttribute($attr, $value);
        }

        $parent->appendChild($child);
        return $parent;
    }
    // }}}

    private function get_current_seq_nr($doc_id) {
       $delta_updates = new \depage\websocket\jstree\jstree_delta_updates($this->prefix, $this->pdo, $this->xmldb, $doc_id);
       return $delta_updates->currentChangeNumber();
    }
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */