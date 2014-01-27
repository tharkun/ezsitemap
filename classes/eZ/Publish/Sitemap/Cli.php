<?php

namespace eZ\Publish\Sitemap;

abstract class Cli
{

    const PERCENT_DISPLAY_MIN_SCALE = 0.0001;
    const PERCENT_DISPLAY_MAX_SCALE = 0.5;

    public static function displayMemoryUsage()
    {
        self::display( 'Instantanous used memory', 1, 0, 30, Tools::memoryGetUsage() );
    }

    public static function display($content, $iNbBrAfter = 1, $iNbBrBefore = 0, $iStrpad = 0, $sContent2 = '', $sType = '', $iLinePadding = 0)
    {
        $isInteractive = ( defined( 'STDOUT' ) === true && function_exists( 'posix_isatty' ) === true && posix_isatty( STDOUT ) === true);
        if ( $isInteractive )
        {
            if ( $iNbBrAfter > 1 )
            {
                $iNbBrAfter = 1;
            }
            if ( $iNbBrBefore > 0 )
            {
                $iNbBrBefore = 0;
            }
        }

        echo self::createLogContent( $content, $iNbBrAfter, $isInteractive, $iNbBrBefore, $iStrpad, $sContent2, $sType, $iLinePadding );
    }

    private static function createLogContent($sContent = '', $iNbBrAfter = 1, $bEchoDate = true, $iNbBrBefore = 0, $iStrpad = 0, $sContent2 = '', $sType = '', $iLinePadding = 0)
    {
        $sOutput = str_pad( '', $iLinePadding, " ", STR_PAD_LEFT );
        if ( $bEchoDate )
        {
            $sOutput .= date( "[ M d Y H:i:s ] : " );
        }
        if ( $sType != '' )
        {
            $sOutput .= strtoupper( $sType )." : ";
        }
        $sOutput .= str_pad( $sContent, $iStrpad, " ", STR_PAD_RIGHT );
        $sOutput .= $sContent2;

        for ( $i = 0; $i < $iNbBrBefore; $i++ )
        {
            $sOutput = "\n$sOutput";
        }
        for ( $i = 0; $i < $iNbBrAfter; $i++ )
        {
            $sOutput = "$sOutput\n";
        }
        return $sOutput;
    }

    /**
     * @return float
     */
    public static function getPercentScale()
    {
        return 0.05;
    }

    /**
     * @param $iTot
     * @param $totNode
     */
    public static function displayPercent($iTot, $totNode)
    {
        $iScale = self::getPercentScale();

        if ($iScale < self::PERCENT_DISPLAY_MIN_SCALE) $iScale = self::PERCENT_DISPLAY_MIN_SCALE;
        if ($iScale > self::PERCENT_DISPLAY_MAX_SCALE) $iScale = self::PERCENT_DISPLAY_MAX_SCALE;

        for ( $i = $iScale; $i <= 1 + self::PERCENT_DISPLAY_MIN_SCALE / 10; $i += $iScale )
        {
            if ( $iTot == floor( $totNode * $i ) )
            {
                self::display( ( 100 * $i )."% - $iTot/$totNode", 1, 0, 30, Tools::memoryGetUsage() );
            }
        }
    }

}
