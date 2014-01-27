<?php

namespace eZ\Publish\Sitemap;

abstract class Tools
{

    const INT_MAX_WEIGHT   = 10485760;
    const INT_MAX_URL      = 50000;
    const INT_MAX_NEWS_URL = 1000;

    /**
     * @param $iWeight
     * @param $sMode
     * @param $iCompt
     * @return bool
     */
    public static function testLimit($iWeight, $sMode, $iCompt)
    {
        return $iWeight > self::INT_MAX_WEIGHT
                || ($sMode != handler::MODE_GOOGLE_NEWS && $iCompt > self::INT_MAX_URL)
                || ($sMode == handler::MODE_GOOGLE_NEWS && $iCompt > self::INT_MAX_NEWS_URL);
    }

    /**
     * @param $mode
     * @param $identifier
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function getSitemapName($mode, $identifier)
    {
        switch ( $mode )
        {
            case handler::MODE_GOOGLE_GLOBAL:
                $name = 'sitemap-'.$identifier.'.xml';
                break;
            case handler::MODE_GOOGLE_NEWS:
            case handler::MODE_GOOGLE_VIDEOS:
            case handler::MODE_GOOGLE_IMAGES:
            default :
                $name = 'sitemap-'.$mode.'-'.$identifier.'.xml';
                break;
        }

        return $name;
    }

    /**
     * @param $var
     * @param int $level
     * @return bool
     */
    public static function unsetRecursively(& $var, $level = 0)
    {
        if ( is_null( $var ) || $level > 4 )
        {
            return true;
        }
        if ( is_object( $var ) )
        {
            foreach ( $var as $sKey => $sValue )
            {
                self::unsetRecursively( $var->$sKey, $level++ );
            }
        }
        if ( is_array( $var) )
        {
            foreach ( $var as $sKey => $sValue )
            {
                self::unsetRecursively( $var[$sKey], $level++ );
            }
        }
        $var = null;
    }

    /**
     * @param bool $real_usage
     * @return string
     */
    public static function memoryGetUsage($real_usage = false)
    {
        if ( function_exists( 'memory_get_usage' ) )
        {
            return number_format( round( memory_get_usage( $real_usage ) / 1048576, 8 ), 4 ).' Mo used';
        }

        return '';
    }

}
