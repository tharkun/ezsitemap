<?php

namespace Project\Sitemap\Handlers;

use eZ\Publish\Sitemap\Handler as Core;
use Project\Sitemap\Handler as Custom;

class Example extends Custom
{

    public function generateSitemap()
    {
        $aNode = array();

        $node = \eZContentObjectTreeNode::subTreeByNodeID( array(
            'Depth'             => 2,
            'ClassFilterType'   => 'include',
            'ClassFilterArray'  => array( 'ceva_heading' ),
            'SortBy'            => array( 'modified', false )
        ), 8211 );
        $this->addNodesToArray( $aNode, $node );
        \eZContentObject::clearCache();

        // Lancement de la génération
        parent::generateMap( $aNode );

        $this->generate();
    }

    public function generateSitemapNews()
    {
        $node = \eZContentObjectTreeNode::subTreeByNodeID( array(
            'Depth'             => 2,
            'ClassFilterType'   => 'include',
            'ClassFilterArray'  => array( 'ceva_heading' ),
            'SortBy'            => array( 'modified', false )
        ), 8211 );
        \eZContentObject::clearCache();

        // Lancement de la génération
        parent::generateMap( $node );

        $this->generate();
    }

}
