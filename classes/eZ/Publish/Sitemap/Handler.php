<?php

namespace eZ\Publish\Sitemap;

abstract class Handler
{

    const MODE_GOOGLE_INDEX    = 'sitemapindex';
    const MODE_GOOGLE_GLOBAL   = 'global';
    const MODE_GOOGLE_NEWS     = 'news';
    const MODE_GOOGLE_VIDEOS   = 'videos';
    const MODE_GOOGLE_IMAGES   = 'images';

    private $mode = self::MODE_GOOGLE_GLOBAL;

    protected $domDocument = null;
    protected $fileNumber = 1;
    protected $files = array();

    private static $bHasDeletedFolderContent = false;


    protected function __construct($mode = self::MODE_GOOGLE_GLOBAL)
    {
        $this->setMode( $mode );

        self::$bHasDeletedFolderContent = false;
    }

    /**
     * @param string $mode
     * @return mixed
     */
    final public static function instance($mode = self::MODE_GOOGLE_GLOBAL)
    {
        $class = null;

        $ini = \eZINI::instance( 'ezsitemap.ini' );
        if ( $ini->hasVariable( 'SitemapSettings', 'Handler' ) )
        {
            $class = $ini->variable( 'SitemapSettings', 'Handler' );
            if ( !class_exists( $class ) )
            {
                $class = null;
            }
        }

        if ( is_null( $class ) )
        {
            $class = __CLASS__;
        }

        return new $class( $mode );
    }

    /**
     * @return null
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @param string $mode
     * @return $this
     */
    public function setMode($mode)
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * @return null
     */
    public function getDOMDocument()
    {
        return $this->domDocument;
    }

    /**
     * @param DOMDocument $domDocument
     * @return $this
     */
    public function setDOMDocument(DOMDocument $domDocument)
    {
        $this->domDocument = $domDocument;

        return $this;
    }

    /**
     * @return int
     */
    public function getFileNumber()
    {
        return $this->fileNumber;
    }

    /**
     * @return array
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @return $this
     */
    final public function addFiles()
    {
        $this->files[$this->fileNumber] = array(
            'name'         => Tools::getSitemapName( $this->mode, $this->fileNumber ),
            'xml'          => $this->domDocument->saveXML(),
            'generated'    => false,
        );
        $this->fileNumber++;

        return $this;
    }


    /***********************************************************************************************************************/
    /***********************************************************************************************************************/
    /***********************************************************************************************************************/


    /**
     * @return $this
     */
    public function generateSitemap()
    {
        return $this;
    }

    /**
     * @return $this
     */
    public function generateSitemapNews()
    {
        return $this;
    }


    /***********************************************************************************************************************/
    /***********************************************************************************************************************/
    /***********************************************************************************************************************/


    final protected function generateMap(array &$aNode)
    {
        Cli::display( sprintf( "Starting generation : sMode = '%s'", $this->mode ), 1, 2 );
        Cli::displayMemoryUsage();

        $urlset = null;
        if ( $this->domDocument == null )
        {
            $this->setDOMDocument( DOMDocument::instance()->init( $urlset, $this->mode ) );
        }
        else
        {
            $urlset = $this->domDocument->firstChild;
            $this->fileNumber--;
        }

        $totNode = count( $aNode );
        $iTot   = 0;
        $iCompt = 0;
        $iWeight = strlen( $this->domDocument->saveXML() );

        Cli::display( "Looping through $totNode nodes" );
        foreach ( $aNode as $iKey => $mMixed )
        {
            $oNode = is_string( $mMixed ) ? json_decode( $mMixed, true ) : $mMixed;

            $iTot++;
            Cli::displayPercent( $iTot, $totNode );

            $url = $this->domDocument->addElement( $urlset, 'url' );

            if ( is_array( $oNode ) )
            {
                $this->addElementsToNodeFromArray( $url, $oNode );
            }
            else if ( $oNode instanceof \eZContentObjectTreeNode )
            {
                $this->addElementsToNodeFromEZObject( $url, $oNode );
            }
            else
            {
                continue;
            }
            $iCompt++;

            // Calculating the xml weight
            $oDocTemp = new \DOMDocument( '1.0', 'utf-8' );
            $oDocTemp->appendChild( $oDocTemp->importNode( $url, true ) );
            $sTempXML = str_replace( '<?xml version="1.0" encoding="utf-8"?>', '', $oDocTemp->saveXML() );
            $iWeight += strlen( substr( $sTempXML, 1, strlen( $sTempXML ) - 2 ) );

            if ( Tools::testLimit( $iWeight, $this->mode, $iCompt ) )
            {
                $urlset->removeChild( $url );
                $this->addFiles();
                Cli::display( "Sitemap weight or url limit reached => Generating new file" );

                $this->setDOMDocument( DOMDocument::instance()->init( $urlset, $this->mode ) );

                $urlset->appendChild( $this->domDocument->importNode( $url, true ) );
                $iCompt = 1;
                $iWeight = strlen( $this->domDocument->saveXML() );
            }

            Tools::unsetRecursively( $aNode[$iKey] );
        }

        Tools::unsetRecursively( $aNode );

        Cli::display( "Last node reached", 2 );
        $this->addFiles();
        Cli::displayMemoryUsage();

        return $this;
    }

    private function getMethodNameToTransformNodeWithMode($sBasicName)
    {
        return $sBasicName . '_' . ( method_exists( $this, $sBasicName . '_' . $this->mode ) ? $this->mode : 'global' );
    }

    protected function addElementsToNodeFromArray(&$url, $array)
    {
        switch ( $this->mode )
        {
            case self::MODE_GOOGLE_NEWS:
                break;
            case self::MODE_GOOGLE_VIDEOS:
                break;
            case self::MODE_GOOGLE_IMAGES:
                $this->addElementsToNodeFromArray_images( $url, $array );
                break;
            case '':
            default:
                $this->addElementsToNodeFromArray_global( $url, $array );
                break;
        }
    }

    protected function addElementsToNodeFromEZObject(&$url, $oNode)
    {
        // récupération de l'url absolue et création de la balise loc
        $this->domDocument->addElement( $url, 'loc', $this->getUrl( $oNode ) );

        switch ( $this->mode )
        {
            case self::MODE_GOOGLE_NEWS:
                $this->addElementsToNodeFromEZObject_news( $url, $oNode );
                break;
            case self::MODE_GOOGLE_VIDEOS:
                //récupération de la date de modif et création de la balise lastmod
                $oObject = $oNode->attribute( 'object' );
                $datemod = date( 'c', $oObject->attribute( 'modified' ) );
                $this->domDocument->addElement( $url, 'lastmod', $datemod );

                //création des balises changefreq et priority
                $sChangeFreq = $this->getChangeFreqFromEZObject( $oNode );
                $this->domDocument->addElement( $url, 'changefreq', $sChangeFreq );

                $priorityVal = $this->getPriorityFromEZObject( $oNode );
                $this->domDocument->addElement( $url, 'priority', $priorityVal );

                $this->addElementsToNodeFromEZObject_videos( $url, $oNode );
                break;
            case self::MODE_GOOGLE_IMAGES:
                // Too complicated to be done with the node coz there are to many configuration
                break;
            case '':
            default:
                $this->addElementsToNodeFromEZObject_global( $url, $oNode );
                break;
        }
    }

    /**
     * @param \eZContentObjectTreeNode $eZContentObjectTreeNode
     * @return string
     */
    protected function getUrl(\eZContentObjectTreeNode $eZContentObjectTreeNode)
    {
        $siteUrl = \eZINI::instance()->variable( 'SiteSettings', 'SiteURL' );
        if ( !preg_match( '@^http://@', $siteUrl ) )
        {
            $siteUrl = "http://" . $siteUrl;
        }
        if ( substr( $siteUrl, -1 ) == '/' )
        {
            $siteUrl = substr( $siteUrl, 0, -1 );
        }

        return $siteUrl . '/' . $eZContentObjectTreeNode->attribute( 'url_alias' );
    }

    protected function getPriorityFromEZObject($oNode)
    {
        $sMethodName = $this->getMethodNameToTransformNodeWithMode( 'getPriorityFromEZObject' );
        return $this->$sMethodName( $oNode );
    }

    protected function getPriorityFromEZObject_global($oNode)
    {
        $tab = explode( "/", $oNode->attribute( 'url_alias' ) );
        $iPriority = 0.9 - 0.1 * ( count( $tab ) - 1 );

        if ( $iPriority < 0.1 )
        {
            $iPriority = 0.1;
        }
        return "$iPriority";
    }

    protected function getPriorityFromEZObject_news($oNode)
    {
        $tab = explode( "/", $oNode->attribute( 'url_alias' ) );
        $iDateJour = strtotime( date( 'Y-m-d' ) );
        $iDateSemaine = strtotime( "-1 week" );
        $oObject = $oNode->attribute( 'object' );
        if ( $oObject->attribute( 'modified' ) >= $iDateJour )
        {
            $iPriority = 1.0;
        }
        else if ( $oObject->attribute( 'modified' ) >= $iDateSemaine )
        {
            $iPriority = 0.7;
        }
        else
        {
            $iPriority = 0.5;
        }

        return $iPriority;
    }


    protected function getChangeFreqFromEZObject($oNode)
    {
        $sMethodName = $this->getMethodNameToTransformNodeWithMode( 'getChangeFreqFromEZObject' );
        return $this->$sMethodName($oNode);
    }

    protected function getChangeFreqFromEZObject_global($oNode)
    {
        return 'monthly';
    }


    /***********************************************************************************************************************/
    /***********************************************************************************************************************/
    /******************************************** Sitemaps "Global" methods ************************************************/
    /***********************************************************************************************************************/
    /***********************************************************************************************************************/


    final protected function addElementsToNodeFromEZObject_global(& $url, $oNode)
    {
        //récupération de la date de modif et création de la balise lastmod
        $this->domDocument->addElement( $url, 'lastmod', date( 'c', $oNode->attribute( 'object' )->attribute( 'modified' ) ) );
        //création des balises changefreq et priority
        $this->domDocument->addElement( $url, 'changefreq', $this->getChangeFreqFromEZObject( $oNode ) );
        $this->domDocument->addElement( $url, 'priority', $this->getPriorityFromEZObject( $oNode ) );
    }

    final protected function addNodesToArray(array & $aNodes, $mEZContentObjectTreeNode)
    {
        $this->pushNodesToArray_global( $aNodes, $mEZContentObjectTreeNode );
    }

    final protected function pushNodesToArray_global(array & $aNodes, $mEZContentObjectTreeNode)
    {
        if ( !is_array( $mEZContentObjectTreeNode ) && $mEZContentObjectTreeNode instanceof \eZContentObjectTreeNode )
        {
            $mEZContentObjectTreeNode = array( $mEZContentObjectTreeNode );
        }
        if ( is_array( $mEZContentObjectTreeNode ) && count( $mEZContentObjectTreeNode ) )
        {
            foreach ( $mEZContentObjectTreeNode as $eZContentObjectTreeNode )
            {
                $aNodes[] = array(
                    'loc'            => $this->getUrl( $eZContentObjectTreeNode ),
                    'lastmod'        => date( 'c', $eZContentObjectTreeNode->attribute( 'object' )->attribute( 'modified' ) ),
                    'changefreq'     => $this->getChangeFreqFromEZObject( $eZContentObjectTreeNode ),
                    'priority'       => $this->getPriorityFromEZObject( $eZContentObjectTreeNode ),
                );
            }
        }
    }

    final protected function addElementsToNodeFromArray_global(&$url, array $array)
    {
        $this->domDocument->addElement( $url, 'loc', $array['loc'] );
        $this->domDocument->addElement( $url, 'lastmod', $array['lastmod'] );
        $this->domDocument->addElement( $url, 'changefreq', $array['changefreq'] );
        $this->domDocument->addElement( $url, 'priority', $array['priority'] );
    }

    protected function getCustomSitemapIndexFiles()
    {
        return array();
    }


    /***********************************************************************************************************************/
    /***********************************************************************************************************************/
    /******************************************** Sitemaps "News" methods **************************************************/
    /***********************************************************************************************************************/
    /***********************************************************************************************************************/


    /**
     * @return string
     */
    protected function getGoogleNewsPublicationName()
    {
        return '';
    }

    protected function addElementsToNodeFromEZObject_news(&$url, $oNode)
    {
        $news = $this->domDocument->createElement( 'news:news' );
        $url->appendChild( $news );

        $oObject = $oNode->attribute( 'object' );

        $publication = $this->domDocument->createElement( 'news:publication' );

        $name = $this->domDocument->createElement( 'news:name' );
        $oCdataNode = $this->domDocument->createCDATASection( $this->getGoogleNewsPublicationName() );
        $name->appendChild( $oCdataNode );
        $publication->appendChild( $name );

        $publication->appendChild( $this->domDocument->createElement( 'news:language', substr( $oObject->CurrentLanguage, 0, 2 ) ) );
        $news->appendChild( $publication );

        $pubDate = date( 'c', $oObject->attribute( 'published' ) );
        $publication_date = $this->domDocument->createElement( 'news:publication_date', $pubDate );
        $news->appendChild( $publication_date );

        $title = $this->domDocument->createElement( 'news:title' );
        $oCdataNode = $this->domDocument->createCDATASection( $oObject->attribute( 'name' ) );
        $title->appendChild( $oCdataNode );
        $news->appendChild( $title );

        $this->getKeywordsFromEZObject_news( $news, $oNode );
    }

    protected function getKeywordsFromEZObject_news(&$news, $oNode)
    {
        if ( $oNode->attribute( 'class_identifier' ) != 'dossier' )
        {
            $oObject = $oNode->attribute( 'object' );

            $dataMap = $oObject->attribute( 'data_map' );
            $sKeywords = '';
            if ( array_key_exists( 'keyword', $dataMap ) )
            {
                $oKeyword = $dataMap['keyword']->content();
                $aWords = $oKeyword->attribute( 'keywords' );
                if ( count( $aWords ) > 0 )
                {
                    $keywords = $this->domDocument->createElement( 'news:keywords', implode( ', ', $aWords ) );
                    $news->appendChild( $keywords );
                }
            }
            else
            {
                foreach ( array_keys( $dataMap ) as $attribute )
                {
                     switch ( $dataMap[$attribute]->DataTypeString )
                     {
                         case 'ezmetatag':
                            $oContent = $dataMap[$attribute]->content();

                            $aWords = $oContent['keywords'];
                            if ( strlen( $aWords ) > 0 )
                            {
                                $keywords = $this->domDocument->createElement( 'news:keywords', $aWords );
                                $news->appendChild( $keywords );
                            }
                             break;
                         default:
                             break;
                     }
                }
            }
        }
    }


    /***********************************************************************************************************************/
    /***********************************************************************************************************************/
    /******************************************** Sitemaps "Videos" methods ************************************************/
    /***********************************************************************************************************************/
    /***********************************************************************************************************************/


    protected function addElementsToNodeFromEZObject_videos()
    {
    }


    /***********************************************************************************************************************/
    /***********************************************************************************************************************/
    /******************************************** Sitemap "Images" methods *************************************************/
    /***********************************************************************************************************************/
    /***********************************************************************************************************************/


    final protected function addElementsToNodeFromArray_images(&$url, array $array)
    {
        $this->domDocument->addElement( $url, 'loc', $array['loc'] );
        $this->domDocument->addElement( $url, 'lastmod', $array['lastmod'] );
        $this->domDocument->addElement( $url, 'changefreq', $array['changefreq'] );
        $this->domDocument->addElement( $url, 'priority', $array['priority'] );
        $this->domDocument->addElement( $url, 'language', $array['language'] );
        $oImage = $this->domDocument->addElement( $url, 'image:image' );
        $aXmlChildrenNodeName = array(
            'image:landing_page_loc',
            'image:title',
            'image:caption',
            'image:keyword',
            'image:category',
            'image:family_friendly',
            'image:publication_date',
            'image:geo_location',
            'image:license',
            'image:size',
            'image:quality',
        );
        foreach ( $aXmlChildrenNodeName as $sXmlChildrenNodeName )
        {
            if ( array_key_exists( $sXmlChildrenNodeName, $array['image:image'] ) && !is_null( $array['image:image'][$sXmlChildrenNodeName] ) )
            {
                if ( is_array( $array['image:image'][$sXmlChildrenNodeName] ) && count( $array['image:image'][$sXmlChildrenNodeName] ) )
                {
                    foreach ( $array['image:image'][$sXmlChildrenNodeName] as $sKeyword )
                    {
                        $this->domDocument->addElement( $oImage, $sXmlChildrenNodeName, $sKeyword );
                    }
                }
                else
                {
                    $this->domDocument->addElement( $oImage, $sXmlChildrenNodeName, $array['image:image'][$sXmlChildrenNodeName] );
                }
            }
        }
    }

    final protected function addElementsToNodeFromEZObject_images()
    {

    }


    /***********************************************************************************************************************/
    /***********************************************************************************************************************/
    /*********************************************** Push methods **********************************************************/
    /***********************************************************************************************************************/
    /***********************************************************************************************************************/


    final private function deleteFiles()
    {
        Cli::display( "Deleting files", 1, 3 );

        // Checking input parameters
        if ( $this->mode == '' )
        {
            Cli::display( "Checking input parameters", 1, 0, 50, '$this->mode == \'\'' );
            return false;
        }
        Cli::display( "Checking input parameters", 1, 0, 50, 'ok' );

        //
        $ini = \eZINI::instance( 'ezsitemap.ini' );
        $aParams = $ini->variable( 'SitemapSettings-'.$this->mode, 'Params' );

        $localDir  = ( isset( $aParams["PathLocal"]) ) ? $aParams["PathLocal"] : false;
        $sUrl      = ( isset( $aParams["Url"]) ) ? $aParams["Url"] : false;

        // Checking local parameters
        if ( !$localDir || !$sUrl )
        {
            Cli::display( "Checking local parameters", 1, 0, 50, '!$localDir || !$sUrl' );
            return false;
        }
        Cli::display( "Checking local parameters", 1, 0, 50, 'ok' );

        // Checking local folders
        if ( file_exists( $localDir ) )
        {
            $aDir = scandir( $localDir );

            // on efface les fichiers dans le répertoire local (pour ne pas avoir de fichier en trop)
            foreach ( $aDir as $file )
            {
                $sPathFile = $localDir.'/'.$file;
                if ( !is_dir( $sPathFile ) )
                {
                    unlink( $sPathFile );
                }
            }
            Cli::display( "Checking local folders", 1, 0, 50, 'existing => content deleted' );
        }
        else
        {
            \eZDir::mkdir( $localDir, false, true );
            Cli::display( "Checking local folders", 1, 0, 50, 'non existing => created' );
        }
        self::$bHasDeletedFolderContent = true;

        return $this;
    }

    final public function generateFiles()
    {
        Cli::display( "Generating files", 1, 3 );

        $aFiles = $this->files;

        // Checking input parameters
        if ( $this->mode == '' || !is_array( $this->files ) )
        {
            Cli::display( "Checking input parameters", 1, 0, 50, '$this->mode == \'\' || !is_array( $aFiles )' );
            return false;
        }
        Cli::display( "Checking input parameters", 1, 0, 50, 'ok' );

        // Checking file count
        $iFileCount = count( $this->files );
        if ( $iFileCount < 1 )
        {
            Cli::display( "Checking file count", 1, 0, 50, 'none' );
            return false;
        }
        Cli::display( "Checking file count", 1, 0, 50, 'ok : count = '.$iFileCount );

        //
        $ini = \eZINI::instance( 'ezsitemap.ini' );
        $aParams = $ini->variable( 'SitemapSettings-'.$this->mode, 'Params' );

        $localDir  = ( isset( $aParams["PathLocal"]) ) ? $aParams["PathLocal"] : false;
        $sUrl      = ( isset( $aParams["Url"]) ) ? $aParams["Url"] : false;

        // Checking local parameters
        if ( !$localDir || !$sUrl )
        {
            Cli::display( "Checking local parameters", 1, 0, 50, '!$localDir || !$sUrl' );
            return false;
        }
        Cli::display( "Checking local parameters", 1, 0, 50, 'ok' );

        // Checking local folders
        if ( !self::$bHasDeletedFolderContent )
        {
            $this->deleteFiles( $this->mode );
        }

        // Creating files
        foreach ( $this->files as $iKey => $map )
        {
            if ( $this->files[$iKey]['generated'] )
            {
                continue;
            }

            if ( !\eZFileHandler::doExists( $localDir ) )
            {
                \eZDir::mkdir( $localDir, false, true );
            }
            $localFile = $localDir.'/'.$map["name"];
            $handle    = fopen( $localFile, 'wr+' );
            if ( !$handle )
            {
                Cli::display( "Opening file '{$map["name"]}'", 1, 0, 50, 'ko : unable to execute fopen' );
                continue;
            }

            $write = fwrite( $handle, $map["xml"] );
            if ( !$write )
            {
                fclose( $handle );
                Cli::display( "Writing file '{$map["name"]}'", 1, 0, 50, 'ko : unable to execute fwrite' );
                continue;
            }
            fclose( $handle );
            Cli::display( "Writing file '{$map["name"]}'", 1, 0, 50, 'ok' );

            $this->files[$iKey]['generated'] = true;
            Tools::unsetRecursively( $this->files[$iKey]["xml"] );
        }
    }

    private function generateIndexFiles()
    {
        //
        $ini = \eZINI::instance( 'ezsitemap.ini' );
        $aParams = $ini->variable( 'SitemapSettings-'.$this->mode, 'Params' );
        $sUrl      = ( isset( $aParams["Url"]) ) ? $aParams["Url"] : false;

        $sitemapindex = null;
        $this->setDOMDocument( DOMDocument::instance()->init( $sitemapindex, 'sitemapindex' ) );

        // Creating files
        foreach ( $this->files as $map )
        {
            $sitemap = $this->domDocument->createElement( 'sitemap' );
            $sitemapindex->appendChild( $sitemap );
            $loc = $this->domDocument->createElement( 'loc', 'http://'.$sUrl.'/'.$map["name"] );
            $sitemap->appendChild( $loc );

            $dateModif = date( 'c' );
            $lastmod = $this->domDocument->createElement( 'lastmod', $dateModif );
            $sitemap->appendChild( $lastmod );
        }

        // In global sitemap, we can use some other sitemaps from external data. We only add them in the sitemapindex. Sitemap file should be generated in an other way.
        if ( $this->mode == self::MODE_GOOGLE_GLOBAL )
        {
            $aCustomSitemapIndexFiles = $this->getCustomSitemapIndexFiles();
            Cli::display( "Fetching custom sitemap index file : count = ".count( $aCustomSitemapIndexFiles ) );
            if ( is_array( $aCustomSitemapIndexFiles ) && count( $aCustomSitemapIndexFiles ) )
            {
                foreach ( $aCustomSitemapIndexFiles as $sCustomSitemapIndexFile )
                {
                    $sitemap = $this->domDocument->addElement( $sitemapindex, 'sitemap', '' );
                    $this->domDocument->addElement( $sitemap, 'loc', preg_match( '@^http://@', $sCustomSitemapIndexFile ) ? $sCustomSitemapIndexFile : 'http://'.$sUrl.'/'.Tools::getSitemapName( 'custom', $sCustomSitemapIndexFile ) );
                    $this->domDocument->addElement( $sitemap, 'lastmod', date( 'c' ) );
                }
            }
        }

        $aIndexParams = $ini->variable( 'SitemapSettings-index', 'Params' );
        $sName = 'sitemap-'.$this->mode.'-index.xml';
        if ( !\eZFileHandler::doExists( $aIndexParams["PathLocal"] ) )
        {
            \eZDir::mkdir( $aIndexParams["PathLocal"], false, true );
        }
        $localIndex = $aIndexParams["PathLocal"].'/'.$sName;
        $handle = fopen( $localIndex, 'wr+' );

        if ( !$handle )
        {
            Cli::display( "Opening file '$sName'", 1, 0, 50, 'ko : unable to execute fopen' );
            return false;
        }

        $write = fwrite( $handle, $this->domDocument->saveXML() );
        if ( !$write )
        {
            fclose( $handle );
            Cli::display( "Writing file '$sName'", 1, 0, 50, 'ko : unable to execute fwrite' );
            return false;
        }
        fclose( $handle );
        Cli::display( "Writing file '$sName'", 1, 0, 50, 'ok' );
    }

    final public function generate()
    {
        $this->generateFiles();
        $this->generateIndexFiles();
    }

}
