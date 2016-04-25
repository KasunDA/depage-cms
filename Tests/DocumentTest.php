<?php

namespace Depage\XmlDb\Tests;

class DocumentTest extends XmlDbTestCase
{
    // {{{ variables
    protected $xmlDb;
    protected $doc;
    // }}}
    // {{{ setUp
    protected function setUp()
    {
        parent::setUp();

        $this->cache = \Depage\Cache\Cache::factory('xmlDb', array('disposition' => 'uncached'));

        $this->xmlDb = new \Depage\XmlDb\XmlDb($this->pdo->prefix . '_proj_test', $this->pdo, $this->cache, array(
            'root',
            'child',
        ));

        $this->doc = new DocumentTestClass($this->xmlDb, 3);
        $this->namespaces = 'xmlns:db="http://cms.depagecms.net/ns/database" xmlns:dpg="http://www.depagecms.net/ns/depage" xmlns:pg="http://www.depagecms.net/ns/page"';
    }
    // }}}

    // {{{ testGetHistory
    public function testGetHistory()
    {
        $this->assertInstanceOf('\\Depage\\XmlDb\\DocumentHistory', ($this->doc->getHistory()));
    }
    // }}}

    // {{{ testGetDoctypeHandler
    public function testGetDoctypeHandler()
    {
        $baseType = 'Depage\XmlDb\XmlDocTypes\Base';

        $this->assertEquals($baseType, $this->doc->getDocInfo()->type);
        $this->assertInstanceOf($baseType, $this->doc->getDoctypeHandler());
    }
    // }}}
    // {{{ testGetDoctypeHandlerNoType
    public function testGetDoctypeHandlerNoType()
    {
        // delete document type
        $this->pdo->exec('UPDATE xmldb_proj_test_xmldocs SET type=\'\' WHERE id=\'3\'');

        $this->assertEquals('', $this->doc->getDocInfo()->type);
        $this->assertInstanceOf('Depage\XmlDb\XmlDocTypes\Base', $this->doc->getDoctypeHandler());
    }
    // }}}

    // {{{ testGetSubdocByNodeId
    public function testGetSubdocByNodeId()
    {
        $expected = '<pg:page ' . $this->namespaces . ' name="P3.1" db:id="6" db:lastchange="2016-02-03 16:09:05" db:lastchangeUid="">bla bla blub <pg:page name="P3.1.2" db:id="7"/></pg:page>';

        $this->assertXmlStringEqualsXmlString($expected, $this->doc->getSubdocByNodeId(6));
    }
    // }}}
    // {{{ testGetSubdocByNodeIdNodeDoesntExist
    /**
     * @expectedException Depage\XmlDb\Exceptions\XmlDbException
     * @expectedExceptionMessage This node is no ELEMENT_NODE or node does not exist
     */
    public function testGetSubdocByNodeIdNodeDoesntExist()
    {
        $this->doc->getSubdocByNodeId(1000);
    }
    // }}}
    // {{{ testGetSubdocByNodeIdCached
    public function testGetSubdocByNodeIdCached()
    {
        $cache = new MockCache();
        $cache->set(
            'xmldb_proj_test_xmldocs_d3/2.xml',
            '<page/>'
        );

        $xmlDb = new \Depage\XmlDb\XmlDb($this->pdo->prefix . '_proj_test', $this->pdo, $cache, array(
            'root',
            'child',
        ));
        $doc = $xmlDb->getDoc(3);

        $expected = '<page/>';

        $this->assertXmlStringEqualsXmlString($expected, $doc->getSubdocByNodeId(2));
    }
    // }}}
    // {{{ testGetSubdocByNodeIdWrongNodeType
    /**
     * @expectedException Depage\XmlDb\Exceptions\XmlDbException
     * @expectedExceptionMessage This node is no ELEMENT_NODE or node does not exist
     */
    public function testGetSubdocByNodeIdWrongNodeType()
    {
        // set up document type
        $this->pdo->exec('UPDATE xmldb_proj_test_xmltree SET type=\'WRONG_NODE\' WHERE id=\'1\'');

        $this->doc->getSubdocByNodeId(1);
    }
    // }}}
    // {{{ testGetSubdocByNodeIdChangedDoc
    public function testGetSubdocByNodeIdChangedDoc()
    {
        // set up mock cache
        $cache = new MockCache();

        $xmlDb = new \Depage\XmlDb\XmlDb($this->pdo->prefix . '_proj_test', $this->pdo, $cache, array(
            'root',
            'child',
        ));
        $doc = $xmlDb->getDoc(1);

        // set up doc type handler, pretend the document changed, trigger save node
        $this->pdo->exec('UPDATE xmldb_proj_test_xmldocs SET type=\'Depage\\\\XmlDb\\\\Tests\\\\DoctypeHandlerTestClass\' WHERE id=\'1\'');
        $doc->getDoctypeHandler()->testDocument = true;
        $doc->getSubdocByNodeId(1);

        // saveNode triggers clearCache, check for cleared cache
        $this->assertTrue($cache->deleted);
    }
    // }}}

    // {{{ testGetNodeNameById
    public function testGetNodeNameById()
    {
        $this->assertEquals('dpg:pages', $this->doc->getNodeNameById(4));
        $this->assertEquals('pg:page', $this->doc->getNodeNameById(5));
    }
    // }}}
    // {{{ testGetNodeNameByIdNonExistent
    public function testGetNodeNameByIdNonExistent()
    {
        $this->assertFalse($this->doc->getNodeNameById(100));
        $this->assertFalse($this->doc->getNodeNameById('noId'));
        $this->assertFalse($this->doc->getNodeNameById(null));
    }
    // }}}

    // {{{ testSaveElementNodes
    public function testSaveElementNodes()
    {
        $xmlStr = '<root xmlns:db="http://cms.depagecms.net/ns/database">' .
            '<child>' .
                '<child/>' .
            '</child>' .
            '<child/>' .
        '</root>';

        $xml = $this->generateDomDocument($xmlStr);
        $this->doc->save($xml);

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($xmlStr, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testSaveElementNodesMany
    public function testSaveElementNodesMany()
    {
        $nodes = '';
        for ($i = 0; $i < 10; $i++) {
            $nodes .= '<child></child><child/><child></child><child></child>text<child/><child/>text<child/><child/><child/>';
        }
        $xmlStr = '<root xmlns:db="http://cms.depagecms.net/ns/database">' . $nodes . '</root>';

        $xml = $this->generateDomDocument($xmlStr);
        $this->doc->save($xml);

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($xmlStr, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testSaveElementNodesWithAttribute
    public function testSaveElementNodesWithAttribute()
    {
        $xmlStr = '<root xmlns:db="http://cms.depagecms.net/ns/database">' .
            '<child attr="test"></child>' .
        '</root>';

        $xml = $this->generateDomDocument($xmlStr);
        $this->doc->save($xml);

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($xmlStr, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testSaveElementNodesWithNamespaces
    public function testSaveElementNodesWithNamespaces()
    {
        $xmlStr = '<root xmlns:db="http://cms.depagecms.net/ns/database">' .
            '<db:child attr="test"></db:child>' .
            '<child db:data="blub" />' .
        '</root>';

        $xml = $this->generateDomDocument($xmlStr);
        $this->doc->save($xml);

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($xmlStr, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testSaveTextNodes
    public function testSaveTextNodes()
    {
        $xmlStr = '<root xmlns:db="http://cms.depagecms.net/ns/database">' .
            '<child>bla</child>blub<b/><c/><child>bla</child>' .
        '</root>';

        $xml = $this->generateDomDocument($xmlStr);
        $this->doc->save($xml);

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($xmlStr, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testSavePiNode
    public function testSavePiNode()
    {
        $xmlStr = '<root xmlns:db="http://cms.depagecms.net/ns/database">' .
            '<?php echo("bla"); ?>' .
        '</root>';

        $xml = $this->generateDomDocument($xmlStr);
        $this->doc->save($xml);

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($xmlStr, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testSaveCommentNode
    public function testSaveCommentNode()
    {
        $xmlStr = '<root xmlns:db="http://cms.depagecms.net/ns/database">' .
            '<!-- comment -->' .
        '</root>';

        $xml = $this->generateDomDocument($xmlStr);
        $this->doc->save($xml);

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($xmlStr, $this->doc->getXml(false));
    }
    // }}}

    // {{{ testUnlinkNode
    public function testUnlinkNode()
    {
        $this->assertEquals(5, $this->doc->unlinkNode(6));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testUnlinkNodeDenied
    public function testUnlinkNodeDenied()
    {
        // set up doc type handler
        $this->pdo->exec('UPDATE xmldb_proj_test_xmldocs SET type=\'Depage\\\\XmlDb\\\\Tests\\\\DoctypeHandlerTestClass\' WHERE id=\'3\'');
        $this->doc->getDoctypeHandler()->isAllowedUnlink = false;

        $this->assertFalse($this->doc->unlinkNode(6));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}

    // {{{ testAddNode
    public function testAddNode()
    {
        $doc = $this->generateDomDocument('<root><node/></root>');

        $this->doc->addNode($doc, 6);

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/><root><node/></root></pg:page>' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testAddNodeDenied
    public function testAddNodeDenied()
    {
        // set up doc type handler
        $this->pdo->exec('UPDATE xmldb_proj_test_xmldocs SET type=\'Depage\\\\XmlDb\\\\Tests\\\\DoctypeHandlerTestClass\' WHERE id=\'3\'');
        $this->doc->getDoctypeHandler()->isAllowedAdd = false;

        $doc = $this->generateDomDocument('<root><node/></root>');

        $this->assertFalse($this->doc->addNode($doc, 6));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testAddNodeByName
    public function testAddNodeByName()
    {
        // set up doc type handler
        $this->pdo->exec('UPDATE xmldb_proj_test_xmldocs SET type=\'Depage\\\\XmlDb\\\\Tests\\\\DoctypeHandlerTestClass\' WHERE id=\'3\'');

        $this->doc->addNodeByName('testNode', 8, 0);

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.2">' .
                    '<testNode attr1="value1" attr2="value2" name="customNameAttribute"/>' .
                '</pg:page>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testAddNodeByNameFail
    public function testAddNodeByNameFail()
    {
        $this->assertFalse($this->doc->addNodeByName('test', 8, 0));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}

    // {{{ testGetPermissionѕ
    public function testGetPermissionѕ()
    {
        $expected = array(
            'validParents' => array(
                '*' => array('*')
            ),
            'availableNodes' => array()
        );

        $this->assertEquals($expected, (array) $this->doc->getPermissions());
    }
    // }}}

    // {{{ testReplaceNode
    public function testReplaceNode()
    {
        $doc = $this->generateDomDocument('<root><node/></root>');

        $this->doc->replaceNode($doc, 5);

        $expected = '<dpg:pages ' . $this->namespaces . ' name="" db:id="4">' .
            '<root db:id="5">' .
                '<node db:id="6"/>' .
            '</root>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml());
    }
    // }}}

    // {{{ testGetPosById
    public function testGetPosById()
    {
        $this->assertEquals(0, $this->doc->getPosById(2));
    }
    // }}}
    // {{{ testGetPosByIdFail
    public function testGetPosByIdFail()
    {
        // there's no node with id 999
        $this->assertNull($this->doc->getPosById(999));
    }
    // }}}

    // {{{ testSaveNode
    public function testSaveNode()
    {
        $doc = $this->generateDomDocument('<root><node/></root>');

        $this->doc->saveNode($doc, 4);

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
            '<root>' .
                '<node/>' .
            '</root>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}

    // {{{ testSaveNodeToDb
    public function testSaveNodeToDb()
    {
        $doc = new \DomDocument();
        $nodeElement = $doc->createElement('test');

        $this->assertEquals(37, $this->doc->saveNodeToDb($nodeElement, 37, 8, 0));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.2">' .
                    '<test/>' .
                '</pg:page>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testSaveNodeToDbEntityRef
    public function testSaveNodeToDbEntityRef()
    {
        $doc = new \DomDocument();
        $nodeElement = $doc->createEntityReference('test');

        $this->assertEquals(37, $this->doc->saveNodeToDb($nodeElement, 37, 8, 0));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testSaveNodeToDbIdNull
    public function testSaveNodeToDbIdNull()
    {
        $doc = new \DomDocument();
        $nodeElement = $doc->createElement('test');

        $this->assertEquals(37, $this->doc->saveNodeToDb($nodeElement, null, 8, 0));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.2">' .
                    '<test/>' .
                '</pg:page>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testSaveNodeToDbIdNullText
    public function testSaveNodeToDbIdNullText()
    {
        $doc = new \DomDocument();
        $nodeElement = $doc->createTextNode('test');

        $this->assertEquals(37, $this->doc->saveNodeToDb($nodeElement, null, 8, 0));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.2">test</pg:page>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}

    // {{{ testUpdateLastchange
    public function testUpdateLastchange()
    {
        $xmlDb = new \Depage\XmlDb\XmlDb($this->pdo->prefix . '_proj_test', $this->pdo, $this->cache, array('userId' => 42));
        $doc = new DocumentTestClass($xmlDb, 3);

        $before = '<dpg:pages ' . $this->namespaces . ' name="" db:lastchange="2016-02-03 16:09:05" db:lastchangeUid="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlString($before, $doc->getXml(false));

        $this->setForeignKeyChecks(false);
        $timestamp = $doc->updateLastChange();
        $this->setForeignKeyChecks(true);

        $date = date('Y-m-d H:i:s', $timestamp);
        $after = '<dpg:pages ' . $this->namespaces . ' name="" db:lastchange="' . $date . '" db:lastchangeUid="42">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlString($after, $doc->getXml(false));
    }
    // }}}

    // {{{ testMoveNode
    public function testMoveNode()
    {
        $this->assertEquals(4, $this->doc->moveNode(6, 4, 0));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastChange($expected, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testMoveNodeIn
    public function testMoveNodeIn()
    {
        $this->assertEquals(4, $this->doc->moveNodeIn(6, 4));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
            '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastChange($expected, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testMoveNodeBefore
    public function testMoveNodeBefore()
    {
        $this->assertEquals(4, $this->doc->moveNodeBefore(6, 5));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testMoveNodeAfter
    public function testMoveNodeAfter()
    {
        $this->assertEquals(5, $this->doc->moveNodeAfter(7, 6));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub </pg:page>' .
                '<pg:page name="P3.1.2"/>' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testMoveNodeAfterSameLevel
    public function testMoveNodeAfterSameLevel()
    {
        $this->assertEquals(5, $this->doc->moveNodeAfter(6, 8));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.2"/>' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}

    // {{{ testCopyNode
    public function testCopyNode()
    {
        $this->assertEquals(37, $this->doc->copyNode(7, 8, 0));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.2">' .
                    '<pg:page name="P3.1.2"/>' .
                '</pg:page>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testCopyNodeDenied
    public function testCopyNodeDenied()
    {
        // set up doc type handler
        $this->pdo->exec('UPDATE xmldb_proj_test_xmldocs SET type=\'Depage\\\\XmlDb\\\\Tests\\\\DoctypeHandlerTestClass\' WHERE id=\'3\'');
        $this->doc->getDoctypeHandler()->isAllowedMove = false;

        $this->assertFalse($this->doc->copyNode(7, 8, 0));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testCopyNodeIn
    public function testCopyNodeIn()
    {
        $this->assertEquals(37, $this->doc->copyNodeIn(7, 8));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.2">' .
                    '<pg:page name="P3.1.2"/>' .
                '</pg:page>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testCopyNodeBefore
    public function testCopyNodeBefore()
    {
        $this->assertEquals(37, $this->doc->copyNodeBefore(7, 8));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.1.2"/>' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testCopyNodeAfter
    public function testCopyNodeAfter()
    {
        $this->assertEquals(37, $this->doc->copyNodeAfter(7, 8));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.2"/>' .
                '<pg:page name="P3.1.2"/>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}

    // {{{ testDuplicateNode
    public function testDuplicateNode()
    {
        $this->assertEquals(37, $this->doc->duplicateNode(6));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.1"/>' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testDuplicateNodeDenied
    public function testDuplicateNodeDenied()
    {
        // set up doc type handler
        $this->pdo->exec('UPDATE xmldb_proj_test_xmldocs SET type=\'Depage\\\\XmlDb\\\\Tests\\\\DoctypeHandlerTestClass\' WHERE id=\'3\'');
        $this->doc->getDoctypeHandler()->isAllowedMove = false;

        $this->assertFalse($this->doc->duplicateNode(5));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page name="P3.1">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}

    // {{{ testBuildNode
    public function testBuildNode()
    {
        $node = $this->doc->buildNode('newNode', array('att' => 'val', 'att2' => 'val2'));

        $expected = '<newNode xmlns:dpg="http://www.depagecms.net/ns/depage" xmlns:pg="http://www.depagecms.net/ns/page" att="val" att2="val2"/>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $node->ownerDocument->saveXML($node));
    }
    // }}}

    // {{{ testSetAttribute
    public function testSetAttribute()
    {
        $this->doc->setAttribute(5, 'textattr', 'new value');
        $this->doc->setAttribute(6, 'name', 'newName');

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3" textattr="new value">' .
                '<pg:page name="newName">bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testRemoveAttribute
    public function testRemoveAttribute()
    {
        $this->assertTrue($this->doc->removeAttribute(6, 'name'));

        $expected = '<dpg:pages ' . $this->namespaces . ' name="">' .
            '<pg:page name="Home3">' .
                '<pg:page>bla bla blub <pg:page name="P3.1.2"/></pg:page>' .
                '<pg:page name="P3.2"/>' .
            '</pg:page>' .
        '</dpg:pages>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml(false));
    }
    // }}}
    // {{{ testRemoveAttributeNonExistent
    public function testRemoveAttributeNonExistent()
    {
        $expected = $this->doc->getXml();

        $this->assertFalse($this->doc->removeAttribute(6, 'idontexist'));

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $this->doc->getXml());
    }
    // }}}
    // {{{ testRemoveIdAttr
    public function testRemoveIdAttr()
    {
        $xmlDoc = new \Depage\Xml\Document();
        $xmlDoc->loadXml('<root db:id="2" xmlns:db="http://cms.depagecms.net/ns/database"><node/></root>');
        $this->doc->removeIdAttr($xmlDoc);

        $expected = '<root xmlns:db="http://cms.depagecms.net/ns/database">' .
                        '<node/>' .
                    '</root>';

        $this->assertXmlStringEqualsXmlStringIgnoreLastchange($expected, $xmlDoc->saveXml());
    }
    // }}}
    // {{{ testGetAttribute
    public function testGetAttribute()
    {
        $this->assertEquals('Home3', $this->doc->getAttribute(5, 'name'));

        $this->assertFalse($this->doc->getAttribute(5, 'undefindattr'));
    }
    // }}}
    // {{{ testGetAttributes
    public function testGetAttributes()
    {
        $attrs = $this->doc->getAttributes(5);

        $expected = array(
            'name' => 'Home3',
        );

        $this->assertEquals($expected, $attrs);
    }
    // }}}

    // {{{ testGetParentIdById
    public function testGetParentIdById()
    {
        $this->assertNull($this->doc->getParentIdById(4));
        $this->assertEquals(4, $this->doc->getParentIdById(5));
    }
    // }}}
    // {{{ testGetParentIdByIdNonExistent
    public function testGetParentIdByIdNonExistent()
    {
        $this->assertFalse($this->doc->getParentIdById(1000));
        $this->assertFalse($this->doc->getParentIdById('noId'));
        $this->assertFalse($this->doc->getParentIdById(null));
    }
    // }}}

    // {{{ testGetNodeId
    public function testGetNodeId()
    {
        $doc = $this->generateDomDocument('<root db:id="2" xmlns:db="http://cms.depagecms.net/ns/database"><node/></root>');

        $id = $this->doc->getNodeId($doc->documentElement);

        $this->assertEquals(2, $id);
    }
    // }}}
    // {{{ testGetNodeDataId
    public function testGetNodeDataId()
    {
        $doc = $this->generateDomDocument('<root db:dataid="2" xmlns:db="http://cms.depagecms.net/ns/database"><node/></root>');

        $id = $this->doc->getNodeDataId($doc->documentElement);

        $this->assertEquals(2, $id);
    }
    // }}}

    // {{{ testGetNodeArrayForSaving
    public function testGetNodeArrayForSaving()
    {
        $nodeArray = array();
        $node = $this->generateDomDocument('<root db:id="2" xmlns:db="http://cms.depagecms.net/ns/database"></root>');

        $this->doc->getNodeArrayForSaving($nodeArray, $node);

        $this->assertEquals(1, count($nodeArray));
        $this->assertEquals(2, $nodeArray[0]['id']);
    }
    // }}}
    // {{{ testGetNodeArrayForSavingStripWhitespace
    public function testGetNodeArrayForSavingStripWhitespace()
    {
        $nodeArray = array();
        $node = $this->generateDomDocument('<root db:id="2" xmlns:db="http://cms.depagecms.net/ns/database"></root>');

        $this->doc->getNodeArrayForSaving($nodeArray, $node, null, 0, false);

        $this->assertEquals(1, count($nodeArray));
        $this->assertEquals(2, $nodeArray[0]['id']);
    }
    // }}}
}

/* vim:set ft=php fenc=UTF-8 sw=4 sts=4 fdm=marker et : */
