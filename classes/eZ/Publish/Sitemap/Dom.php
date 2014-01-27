<?php

namespace eZ\Publish\Sitemap;

final class Dom
{

    private $domDocument = null;

    /**
     * @param string $version
     * @param string $encoding
     */
    public function __construct($version = '1.0', $encoding = 'utf-8')
    {
        $this->domDocument = new \DOMDocument( $version, $encoding );
    }

    /**
     * @param string $version
     * @param string $encoding
     * @return Dom
     */
    public static function instance($version = '1.0', $encoding = 'utf-8')
    {
        return new self( $version, $encoding );
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->domDocument->$name;
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array( array( $this->domDocument, $name ), $arguments );
    }

    /**
     * @param $urlset
     * @param $mode
     * @return Dom
     */
    public function init(&$urlset, $mode)
    {
        $urlset = $this->createElement( $mode == 'sitemapindex' ? 'sitemapindex' : 'urlset' );

        $aAttributes = array();
        $aAttributes['xmlns'] = 'http://www.sitemaps.org/schemas/sitemap/0.9';

        switch ( $mode )
        {
            case handler::MODE_GOOGLE_NEWS:
                $aAttributes['xmlns:news'] = 'http://www.google.com/schemas/sitemap-news/0.9';
                $aAttributes['xmlns:image'] = 'http://www.google.com/schemas/sitemap-images/1.1';
                break;
            case handler::MODE_GOOGLE_VIDEOS:
                $aAttributes['xmlns:video'] = 'http://www.google.com/schemas/sitemap-video/1.0';
                break;
            case handler::MODE_GOOGLE_IMAGES:
                $aAttributes['xmlns:image'] = 'http://www.google.com/schemas/sitemap-images/1.1';
                break;
        }

        foreach ( $aAttributes as $sKey => $sValue )
        {
            $urlset->setAttribute( $sKey, $sValue );
        }

        $this->appendChild( $urlset );

        return $this;
    }

    /**
     * @param $url
     * @param $nodeName
     * @param string $nodeValue
     * @return mixed
     */
    public function addElement(&$url, $nodeName, $nodeValue = '')
    {
        $node = $this->createElement( $nodeName );

        if ( $nodeValue != '' )
        {
            $node = $this->createElement( $nodeName );
            if ( preg_match( "/[&<>\"']/", $nodeValue ) )
            {
                $oCdataNode = $this->createCDATASection( $nodeValue );
                $node->appendChild( $oCdataNode );
            }
            else
            {
                $node->nodeValue = $nodeValue;
            }
        }

        $url->appendChild( $node );

        return $node;
    }

}
