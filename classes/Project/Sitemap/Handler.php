<?php

namespace Project\Sitemap;

use eZ\Publish\Sitemap\Handler as Core;

abstract class Handler extends Core
{

    final protected function getGoogleNewsPublicationName()
    {
        return \eZINI::instance( 'ezsitemap.ini' )->variable( 'GoogleNews', 'PublicationName' );
    }

}
