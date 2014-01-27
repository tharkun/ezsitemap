<?php

namespace eZ\Publish\Sitemap;

abstract class Handler
{

    const MODE_GOOGLE_INDEX    = 'sitemapindex';
    const MODE_GOOGLE_GLOBAL   = 'global';
    const MODE_GOOGLE_NEWS     = 'news';
    const MODE_GOOGLE_VIDEOS   = 'videos';
    const MODE_GOOGLE_IMAGES   = 'images';


    /***********************************************************************************************************************/
    /***********************************************************************************************************************/
    /*************************************** Sitemap "Global" methods ******************************************************/
    /***********************************************************************************************************************/
    /***********************************************************************************************************************/


    abstract protected function getCustomEZObjectsUrl();


    /***********************************************************************************************************************/
    /***********************************************************************************************************************/
    /*************************************** Sitemap "News" methods ********************************************************/
    /***********************************************************************************************************************/
    /***********************************************************************************************************************/


    abstract protected function getGoogleNewsPublicationName();


    /***********************************************************************************************************************/
    /***********************************************************************************************************************/
    /*************************************** Sitemap generation methods ****************************************************/
    /***********************************************************************************************************************/
    /***********************************************************************************************************************/


    abstract public function generateSitemap();
    abstract public function generateSitemapNews();

    protected $dom = null;
    protected $fileNumber = 1;
    protected $files = array();

    private static $bHasDeletedFolderContent = false;

    protected static $sUrlMainObjects = '';


    /***********************************************************************************************************************/
    /***********************************************************************************************************************/
    /********************************************* Constructor *************************************************************/
    /***********************************************************************************************************************/
    /***********************************************************************************************************************/


    protected function __construct($sMode = self::MODE_GOOGLE_GLOBAL)
    {
        self::$bHasDeletedFolderContent = false;

        self::$sUrlMainObjects = $this->getCustomEZObjectsUrl();
    }

    /**
     * @param string $sMode
     * @return mixed
     */
    final public static function instance($sMode = self::MODE_GOOGLE_GLOBAL)
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
            $class = 'eZ\\Publish\\Sitemap\\Handlers\\Nil';
        }

        return new $class( $sMode );
    }

    /**
     * @return null
     */
    public function getDom()
    {
        return $this->dom;
    }

    /**
     * @param Dom $dom
     * @return handler
     */
    public function setDom(Dom $dom)
    {
        $this->dom = $dom;

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
     * @param $mode
     * @return handler
     */
    final public function addFiles($mode)
    {
        $this->files[$this->fileNumber] = array(
            'sName'         => Tools::getSitemapName($mode, $this->fileNumber),
            'sXML'          => $this->dom->saveXML(),
            'bGenerated'    => false,
        );
        $this->fileNumber++;

        return $this;
    }


    /***********************************************************************************************************************/
    /***********************************************************************************************************************/
    /***********************************************************************************************************************/


    final protected function generateMap(array & $aNode, $sMode = self::MODE_GOOGLE_GLOBAL ) {

        Cli::display("Starting generation : sMode = '$sMode'", 1, 2);
        Cli::displayMemoryUsage();

        $urlset = null;
        if ($this->dom == null)
        {
            $this->setDom(Dom::instance()->init($urlset, $sMode));
        }
        else
        {
            $urlset = $this->dom->firstChild;
            $this->fileNumber--;
        }

        $totNode = count($aNode);
        $iTot   = 0;
        $iCompt = 0;
        $iWeight = strlen($this->dom->saveXML());

        Cli::display("Looping through $totNode nodes");
        foreach ($aNode as $iKey => $mMixed)
        {
            $oNode = is_string($mMixed) ? json_decode($mMixed, true) : $mMixed;

            $iTot++;
            Cli::displayPercent($iTot, $totNode);

            $url = $this->dom->addElement($urlset, 'url');

            if (is_array($oNode)){
                $this->addElementsToNodeFromArray($url, $oNode, $sMode);
            } elseif ($oNode instanceof \eZContentObjectTreeNode){
                $this->addElementsToNodeFromEZObject($url, $oNode, $sMode);
            } else{
                continue;
            }
            $iCompt++;

            // Calculating the xml weight
            $oDocTemp = new \DOMDocument( '1.0', 'utf-8' );
            $oDocTemp->appendChild( $oDocTemp->importNode($url, true) );
            $sTempXML = str_replace('<?xml version="1.0" encoding="utf-8"?>', '', $oDocTemp->saveXML());
            $iWeight += strlen( substr($sTempXML, 1, strlen($sTempXML)-2) );

            if ( Tools::testLimit($iWeight, $sMode, $iCompt) )
            {
                $urlset->removeChild($url);
                $this->addFiles($sMode);
                Cli::display("Sitemap weight or url limit reached => Generating new file");

                $this->setDom(Dom::instance()->init($urlset, $sMode));

                $urlset->appendChild( $this->dom->importNode($url, true));
                $iCompt = 1;
                $iWeight = strlen($this->dom->saveXML());
            }

            Tools::unsetRecursively($aNode[$iKey]);
        }

        Tools::unsetRecursively($aNode);

        Cli::display("Last node reached", 2);
        $this->addFiles($sMode);
        Cli::displayMemoryUsage();

        return array();
    }

    private function getMethodNameToTransformNodeWithMode($sBasicName, $sMode)
    {
        return $sBasicName . '_' . ( method_exists($this, $sBasicName . '_' . $sMode) ? $sMode : 'global' );
    }

    protected function addElementsToNodeFromArray(&$url, $array, $sMode) {
        switch ($sMode) {
            case self::MODE_GOOGLE_NEWS:
                break;
            case self::MODE_GOOGLE_VIDEOS:
                break;
            case self::MODE_GOOGLE_IMAGES:
                $this->addElementsToNodeFromArray_images($url, $array);
                break;
            case '':
            default:
                $this->addElementsToNodeFromArray_global($url, $array);
                break;
        }
    }

    protected function addElementsToNodeFromEZObject(&$url, $oNode, $sMode) {
        // récupération de l'url absolue et création de la balise loc
        $this->dom->addElement($url, 'loc', self::$sUrlMainObjects.$oNode->attribute('url_alias'));

        switch ($sMode) {
            case self::MODE_GOOGLE_NEWS:
                $this->addElementsToNodeFromEZObject_news($url, $oNode);
                break;
            case self::MODE_GOOGLE_VIDEOS:
                //récupération de la date de modif et création de la balise lastmod
                $oObject = $oNode->attribute('object');
                $datemod = date('c',$oObject->attribute('modified'));
                $this->dom->addElement($url, 'lastmod', $datemod);

                //création des balises changefreq et priority
                $sChangeFreq = $this->getChangeFreqFromEZObject($oNode, $sMode);
                $this->dom->addElement($url, 'changefreq', $sChangeFreq);

                $priorityVal = $this->getPriorityFromEZObject($oNode, $sMode);
                $this->dom->addElement($url, 'priority', $priorityVal);

                $this->addElementsToNodeFromEZObject_videos($url, $oNode);
                break;
            case self::MODE_GOOGLE_IMAGES:
                // Too complicated to be done with the node coz there are to many configuration
                break;
            case '':
            default:
                $this->addElementsToNodeFromEZObject_global($url, $oNode, $sMode);
                break;
        }
    }


    protected function getPriorityFromEZObject($oNode, $sMode) {
        $sMethodName = $this->getMethodNameToTransformNodeWithMode('getPriorityFromEZObject', $sMode);
        return $this->$sMethodName($oNode);
    }

    protected function getPriorityFromEZObject_global($oNode) {
        $tab = explode("/", $oNode->attribute('url_alias'));
        $iPriority = 0.9 - 0.1 * (count($tab)-1) ;
        if ($iPriority<0.1) $iPriority = 0.1;
        return "$iPriority";
    }

    protected function getPriorityFromEZObject_news($oNode) {
        $tab = explode("/", $oNode->attribute('url_alias'));
        $iDateJour = strtotime(date('Y-m-d'));
        $iDateSemaine = strtotime("-1 week");
        $oObject = $oNode->attribute('object');
        if ($oObject->attribute('modified') >= $iDateJour) {
            $iPriority = 1.0;
        }
        else if ($oObject->attribute('modified') >= $iDateSemaine) {
            $iPriority = 0.7;
        }
        else {
            $iPriority = 0.5;
        }
        return $iPriority;
    }


    protected function getChangeFreqFromEZObject($oNode, $sMode) {
        $sMethodName = $this->getMethodNameToTransformNodeWithMode('getChangeFreqFromEZObject', $sMode);
        return $this->$sMethodName($oNode);
    }

    protected function getChangeFreqFromEZObject_global($oNode) {
        return 'monthly';
    }


    /***********************************************************************************************************************/
    /***********************************************************************************************************************/
    /******************************************** Sitemaps "Global" methods ************************************************/
    /***********************************************************************************************************************/
    /***********************************************************************************************************************/


    final protected function addElementsToNodeFromEZObject_global(& $url, $oNode, $sMode) {
        //récupération de la date de modif et création de la balise lastmod
        $this->dom->addElement($url, 'lastmod', date('c', $oNode->attribute('object')->attribute('modified')));
        //création des balises changefreq et priority
        $this->dom->addElement($url, 'changefreq', $this->getChangeFreqFromEZObject($oNode, $sMode));
        $this->dom->addElement($url, 'priority', $this->getPriorityFromEZObject($oNode, $sMode));
    }

    final protected function addNodesToArray(array & $aNodes, $mEZContentObjectTreeNode, $sMode) {
        $this->pushNodesToArray_global($aNodes, $mEZContentObjectTreeNode, $sMode);
    }

    final protected function pushNodesToArray_global(array & $aNodes, $mEZContentObjectTreeNode, $sMode) {
        if (!is_array($mEZContentObjectTreeNode) && $mEZContentObjectTreeNode instanceof \eZContentObjectTreeNode) {
            $mEZContentObjectTreeNode = array($mEZContentObjectTreeNode);
        }
        if (is_array($mEZContentObjectTreeNode) && count($mEZContentObjectTreeNode)) {
            foreach ($mEZContentObjectTreeNode as $eZContentObjectTreeNode) {
                $aNodes[] = array(
                    'loc'            => self::$sUrlMainObjects.$eZContentObjectTreeNode->attribute('url_alias'),
                    'lastmod'        => date('c', $eZContentObjectTreeNode->attribute('object')->attribute('modified') ),
                    'changefreq'     => $this->getChangeFreqFromEZObject($eZContentObjectTreeNode, $sMode),
                    'priority'       => $this->getPriorityFromEZObject($eZContentObjectTreeNode, $sMode),
                );
            }
        }
    }

    final protected function addElementsToNodeFromArray_global(&$url, $array)
    {
        $this->dom->addElement($url, 'loc',        $array['loc']);
        $this->dom->addElement($url, 'lastmod',    $array['lastmod']);
        $this->dom->addElement($url, 'changefreq', $array['changefreq']);
        $this->dom->addElement($url, 'priority',   $array['priority']);
    }

    protected function getCustomSitemapIndexFiles() {
        return array();
    }


    /***********************************************************************************************************************/
    /***********************************************************************************************************************/
    /******************************************** Sitemaps "News" methods **************************************************/
    /***********************************************************************************************************************/
    /***********************************************************************************************************************/


    protected function addElementsToNodeFromEZObject_news(&$url, $oNode) {
        $news = $this->dom->createElement('news:news');
        $url->appendChild($news);

        $oObject = $oNode->attribute('object');

        $publication = $this->dom->createElement('news:publication');

        $name = $this->dom->createElement('news:name');
        $oCdataNode = $this->dom->createCDATASection( $this->getGoogleNewsPublicationName() );
        $name->appendChild($oCdataNode);
        $publication->appendChild($name);

        $publication->appendChild($this->dom->createElement('news:language', substr($oObject->CurrentLanguage, 0, 2)));
        $news->appendChild($publication);

        $pubDate = date('c', $oObject->attribute('published'));
        $publication_date = $this->dom->createElement('news:publication_date', $pubDate);
        $news->appendChild($publication_date);

        $title = $this->dom->createElement('news:title');
        $oCdataNode = $this->dom->createCDATASection($oObject->attribute('name'));
        $title->appendChild($oCdataNode);
        $news->appendChild($title);

        $this->getKeywordsFromEZObject_news($news, $oNode);
    }

    protected function getKeywordsFromEZObject_news(&$news, $oNode) {
        if($oNode->attribute('class_identifier') != 'dossier'){
            $oObject = $oNode->attribute('object');

            $dataMap = $oObject->attribute('data_map');
            $sKeywords = '';
            if (array_key_exists('keyword', $dataMap)) {
                $oKeyword = $dataMap['keyword']->content();
                $aWords = $oKeyword->attribute('keywords');
                if(count($aWords)>0){
                    $keywords = $this->dom->createElement('news:keywords', implode(', ',$aWords));
                    $news->appendChild($keywords);
                }
            } else {
                foreach (array_keys($dataMap) as $attribute) {
                     switch ($dataMap[$attribute]->DataTypeString) {
                         case 'ezmetatag':
                            $oContent = $dataMap[$attribute]->content();

                            $aWords = $oContent['keywords'];
                            if(strlen($aWords)>0){
                                $keywords = $this->dom->createElement('news:keywords', $aWords);
                                $news->appendChild($keywords);
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


    final protected function addElementsToNodeFromArray_images(&$url, $array) {
        $this->dom->addElement($url, 'loc',        $array['loc']);
        $this->dom->addElement($url, 'lastmod',    $array['lastmod']);
        $this->dom->addElement($url, 'changefreq', $array['changefreq']);
        $this->dom->addElement($url, 'priority',   $array['priority']);
        $this->dom->addElement($url, 'language',   $array['language']);
        $oImage = $this->dom->addElement($url, 'image:image');
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
        foreach ($aXmlChildrenNodeName as $sXmlChildrenNodeName) {
            if (array_key_exists($sXmlChildrenNodeName, $array['image:image'])
                && !is_null($array['image:image'][$sXmlChildrenNodeName])
            ) {
                if (is_array($array['image:image'][$sXmlChildrenNodeName]) && count($array['image:image'][$sXmlChildrenNodeName])) {
                    foreach ($array['image:image'][$sXmlChildrenNodeName] as $sKeyword) {
                        $this->dom->addElement($oImage, $sXmlChildrenNodeName, $sKeyword);
                    }
                }
                else $this->dom->addElement($oImage, $sXmlChildrenNodeName, $array['image:image'][$sXmlChildrenNodeName]);
            }
        }
    }

    final protected function addElementsToNodeFromEZObject_images() {

    }


    /***********************************************************************************************************************/
    /***********************************************************************************************************************/
    /*********************************************** Push methods **********************************************************/
    /***********************************************************************************************************************/
    /***********************************************************************************************************************/


    final private function deleteFiles($sMode = '') {
        Cli::display("Deleting files", 1, 3);

        // Checking input parameters
        if( $sMode == ''){
            Cli::display("Checking input parameters", 1, 0, 50, '$sMode == \'\'');
            return false;
        }
        Cli::display("Checking input parameters", 1, 0, 50, 'ok');

        //
        $ini = \eZINI::instance('ezsitemap.ini');
        $aParams = $ini->variable('Sitemap-'.$sMode, 'Params');

        $localDir  = ( isset($aParams["PathLocal"]) ) ? $aParams["PathLocal"] : false;
        $sUrl      = ( isset($aParams["Url"]) ) ? $aParams["Url"] : false;

        // Checking local parameters
        if(!$localDir || !$sUrl )
        {
            Cli::display("Checking local parameters", 1, 0, 50, '!$localDir || !$sUrl');
            return false;
        }
        Cli::display("Checking local parameters", 1, 0, 50, 'ok');

        // Checking local folders
        if (file_exists($localDir)) {
            $aDir = scandir($localDir);

            // on efface les fichiers dans le répertoire local (pour ne pas avoir de fichier en trop)
            foreach ($aDir as $file){
                $sPathFile = $localDir.'/'.$file;
                if (!is_dir($sPathFile)) {
                    unlink($sPathFile);
                }
            }
            Cli::display("Checking local folders", 1, 0, 50, 'existing => content deleted');
        }
        else
        {
            \eZDir::mkdir($localDir, false, true);
            Cli::display("Checking local folders", 1, 0, 50, 'non existing => created');
        }
        self::$bHasDeletedFolderContent = true;

        return $this;
    }

    final public function generateFiles($sMode = '') {
        Cli::display("Generating files", 1, 3);

        $aFiles = $this->files;

        // Checking input parameters
        if( $sMode == '' || !is_array( $this->files ) ){
            Cli::display("Checking input parameters", 1, 0, 50, '$sMode == \'\' || !is_array( $aFiles )');
            return false;
        }
        Cli::display("Checking input parameters", 1, 0, 50, 'ok');

        // Checking file count
        $iFileCount = count($this->files);
        if($iFileCount < 1){
            Cli::display("Checking file count", 1, 0, 50, 'none');
            return false;
        }
        Cli::display("Checking file count", 1, 0, 50, 'ok : count = '.$iFileCount);

        //
        $ini = \eZINI::instance('ezsitemap.ini');
        $aParams = $ini->variable('Sitemap-'.$sMode, 'Params');

        $localDir  = ( isset($aParams["PathLocal"]) ) ? $aParams["PathLocal"] : false;
        $sUrl      = ( isset($aParams["Url"]) ) ? $aParams["Url"] : false;

        // Checking local parameters
        if(!$localDir || !$sUrl )
        {
            Cli::display("Checking local parameters", 1, 0, 50, '!$localDir || !$sUrl');
            return false;
        }
        Cli::display("Checking local parameters", 1, 0, 50, 'ok');

        // Checking local folders
        if (!self::$bHasDeletedFolderContent) $this->deleteFiles($sMode);

        // Creating files
        foreach ($this->files as $iKey => $map) {

            if ($this->files[$iKey]['bGenerated']) continue;

            if (! \eZFileHandler::doExists($localDir))
                \eZDir::mkdir($localDir, false, true);
            $localFile = $localDir.'/'.$map["sName"];
            $handle    = fopen($localFile, 'wr+');
            if (!$handle) {
                Cli::display("Opening file '{$map["sName"]}'", 1, 0, 50, 'ko : unable to execute fopen');
                continue;
            }

            $write = fwrite($handle, $map["sXML"]);
            if(!$write){
                fclose($handle);
                Cli::display("Writing file '{$map["sName"]}'", 1, 0, 50, 'ko : unable to execute fwrite');
                continue;
            }
            fclose($handle);
            Cli::display("Writing file '{$map["sName"]}'", 1, 0, 50, 'ok');

            $this->files[$iKey]['bGenerated'] = true;
            Tools::unsetRecursively($this->files[$iKey]["sXML"]);

        }
    }

    private function generateIndexFiles($sMode = '') {
        //
        $ini = \eZINI::instance('ezsitemap.ini');
        $aParams = $ini->variable('Sitemap-'.$sMode, 'Params');
        $sUrl      = ( isset($aParams["Url"]) ) ? $aParams["Url"] : false;

        $sitemapindex = null;
        $this->setDom(Dom::instance()->init($sitemapindex, 'sitemapindex'));

        // Creating files
        foreach ($this->files as $map) {

            $sitemap = $this->dom->createElement('sitemap');
            $sitemapindex->appendChild($sitemap);
            $loc = $this->dom->createElement('loc', 'http://'.$sUrl.'/'.$map["sName"]);
            $sitemap->appendChild($loc);

            $dateModif = date('c');
            $lastmod = $this->dom->createElement('lastmod', $dateModif);
            $sitemap->appendChild($lastmod);
        }

        // In global sitemap, we can use some other sitemaps from external data. We only add them in the sitemapindex. Sitemap file should be generated in an other way.
        if ($sMode == self::MODE_GOOGLE_GLOBAL) {
            $aCustomSitemapIndexFiles = $this->getCustomSitemapIndexFiles();
            Cli::display("Fetching custom sitemap index file : count = ".count($aCustomSitemapIndexFiles));
            if (is_array($aCustomSitemapIndexFiles) && count($aCustomSitemapIndexFiles)) {
                foreach ($aCustomSitemapIndexFiles as $sCustomSitemapIndexFile) {
                    $sitemap = $this->dom->addElement($sitemapindex, 'sitemap', '');
                    $this->dom->addElement($sitemap, 'loc', preg_match('@^http://@', $sCustomSitemapIndexFile) ? $sCustomSitemapIndexFile : 'http://'.$sUrl.'/'.Tools::getSitemapName('custom', $sCustomSitemapIndexFile));
                    $this->dom->addElement($sitemap, 'lastmod', date('c'));
                }
            }
        }

        $aIndexParams = $ini->variable('Sitemap-index', 'Params');
        $sName = 'sitemap-'.$sMode.'-index.xml';
        if (! \eZFileHandler::doExists($aIndexParams["PathLocal"]))
            \eZDir::mkdir($aIndexParams["PathLocal"], false, true);
        $localIndex = $aIndexParams["PathLocal"].'/'.$sName;
        $handle = fopen($localIndex, 'wr+');

        if (!$handle){
            Cli::display("Opening file '$sName'", 1, 0, 50, 'ko : unable to execute fopen');
            return false;
        }

        $write = fwrite($handle, $this->dom->saveXML());
        if(!$write){
            fclose($handle);
            Cli::display("Writing file '$sName'", 1, 0, 50, 'ko : unable to execute fwrite');
            return false;
        }
        fclose($handle);
        Cli::display("Writing file '$sName'", 1, 0, 50, 'ok');
    }

    final public function generate($sMode)
    {
        $this->generateFiles($sMode);
        $this->generateIndexFiles($sMode);
    }

}
