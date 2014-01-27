<?php

namespace Project\Sitemap\Handlers;

use eZ\Publish\Sitemap\Handler as Core;
use Project\Sitemap\Handler as Custom;

class Example extends Custom
{

    public function __construct($sMode = 'global')
    {
        parent::__construct( $sMode );
    }


    /***********************************************************************************************************************/
    /***********************************************************************************************************************/
    /*************************************** Sitemap generation methods ****************************************************/
    /***********************************************************************************************************************/
    /***********************************************************************************************************************/


    public function generateSitemap()
    {
        $sMode = 'global';
        $urlMainObjects = $this->getCustomEZObjectsUrl();

        $aNode = array();

        $node = \eZContentObjectTreeNode::subTreeByNodeID( array(
            'Depth'             => 2,
            'ClassFilterType'   => 'include',
            'ClassFilterArray'  => array('ceva_heading'),
            'SortBy'            => array('modified', false)
        ), 8211 );
        $this->addNodesToArray($aNode, $node, $sMode);
        \eZContentObject::clearCache();

        // Lancement de la génération
        $aFiles = parent::generateMap( $aNode, $sMode);

        $this->generate( $sMode );
    }

    public function generateSitemapNews()
    {
        $sMode = 'news';
        $urlMainObjects = $this->getCustomEZObjectsUrl();

        $node = \eZContentObjectTreeNode::subTreeByNodeID( array(
            'Depth'             => 2,
            'ClassFilterType'   => 'include',
            'ClassFilterArray'  => array('ceva_heading'),
            'SortBy'            => array('modified', false)
        ), 8211 );
        \eZContentObject::clearCache();

        // Lancement de la génération
        $aFiles = parent::generateMap( $node, $sMode);

        $this->generate( $sMode );
    }

}
