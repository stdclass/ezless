<?php
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ less
// SOFTWARE RELEASE: 1.x
// COPYRIGHT NOTICE: Copyright (C) 2010-2011 Phillip Dornauer, Juan Pablo Stumpf
// SOFTWARE LICENSE: Creative Commons By-Sa 3.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the Creative Commons By-Sa 3.0
//   License.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   Creative Commons By-Sa 3.0 License for more details.
//
//
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//

/**
 * eZ Less Template Operator
 */
class ezLessOperator{


    /**
	 * $Operators
	 * @access private
	 * @type array
	 */
	private $Operators;


	/**
	 * $files
	 * @access static
	 * @type array
	 */
	static $files = array();


	/**
	 * eZ Template Operator Constructor
	 * @return null
	 */
	function __construct(){
		$this->Operators = array('ezless', 'ezless_add');
	}


	/**
	 * operatorList
	 * @access public
	 * @return array
	 */
	function &operatorList(){
		return $this->Operators;
	}

	/**
	 * namedParameterPerOperator
	 * @return true
	 */
	function namedParameterPerOperator(){
		return true;
	}


	/**
	 * namedParameterList
	 * @return array
	 */
	function namedParameterList(){
		return array('ezless' => array(),
					 'ezless_add' => array()
				 );
	}


	/**
	 * modify
	 * @param & $tpl
	 * @param & $operatorName
	 * @param & $operatorParameters
	 * @param & $rootNamespace
	 * @param & $currentNamespace
	 * @param & $operatorValue
	 * @param & $namedParameters
	 * @return null
	 */
	function modify( &$tpl, &$operatorName, &$operatorParameters, &$rootNamespace,
									&$currentNamespace, &$operatorValue, &$namedParameters ){

		switch ( $operatorName ){
			case 'ezless':
				$operatorValue = $this->loadFiles( $operatorValue );
				break;
			case 'ezless_add':
				$operatorValue = $this->addFiles( $operatorValue );
				break;
		}

	}


	/**
	 * loadFiles
	 * @param array $files
	 * @return string $html generated html tags
	 */
	public function loadFiles( $files ){
	    $pageLayoutFiles = array();
		if( is_array( $files ) )
			foreach( $files as $file )
				$pageLayoutFiles[] = $file;


		$files = $this->prependArray( self::$files, $pageLayoutFiles );

		return $this->generateTag( $files );
	}


	/**
	 * addFiles
	 * @param array|string $files
	 * @return null
	 */
	public function addFiles($files){
		if( is_array( $files ) )
			foreach( $files as $file )
				self::$files[] = $file;
		else
			self::$files[] = $files;

	}


	/**
	 * prependArray
	 * @description prepends the $prepend array in front of $array
	 * @param array $array
	 * @param array $prepend
	 * @return array $return
	 */
	private function prependArray( $array, $prepend ){
		$return = $prepend;

		foreach( $array as $value)
			$return[] = $value;

		return $return;
	}


	/**
	 * generateTag
	 * @param array $files
	 * @return string $html
	 */
	private function generateTag( $files ){
        eZDebug::writeDebug($files, 'ezLessOperator::generateTag');

	    $html = $cssContent = '';

		$ini 		= eZINI::instance( 'ezless.ini' );
		$useOneFile = $ini->variable( 'ezlessconfig','useOneFile' );
		$bases   	= eZTemplateDesignResource::allDesignBases();
		$triedFiles = array();

		$sys = eZSys::instance();

		$path = $sys->cacheDirectory() . '/public/stylesheets';

		require_once dirname( __FILE__ ) . '/../lib/lessc.inc.php';


        $packerLevel = $this->getPackerLevel();
		$less = new lessc();
		foreach( $files as $file){
			$match = eZTemplateDesignResource::fileMatch( $bases, '', 'stylesheets/'.$file, $triedFiles );

            $content = file_get_contents( $match['path'] );
			$content = ezjscPacker::fixImgPaths( $content, $match['path'] );


			if( $useOneFile == "true" ){
				$cssContent .= $content;
			}else{
				try
				{
				    $parsedContent = $less->parse( $content );
    				if( $packerLevel > 1 )
    			    {
                        $parsedContent = $this->optimizeCSS( $parsedContent, $packerLevel );
    			    }
                    $file = md5(uniqid(mt_rand(), true)) . ".css";
                    $file = $path . '/' . $file;
                    $clusterFile = eZClusterFileHandler::instance( $file );
                    $clusterFile->storeContents( $parsedContent, 'ezless', 'text/css' );
        			eZURI::transformURI( $file, true );
    				$html .= '<link rel="stylesheet" type="text/css" href="' . $file . '" />' . PHP_EOL;
    			}
				catch( Exception $e )
				{
                    eZDebug::writeError( $e->getMessage(), 'ezLessOperator for ' . $match['path'] );
				}
			}
		}


		if( $useOneFile == "true" ){
			$file = md5(uniqid(mt_rand(), true)) . ".css";
			$less = new lessc();
	        try
			{
			    $parsedContent = $less->parse( $cssContent );

                if( $packerLevel > 1 )
			    {
                    $parsedContent = $this->optimizeCSS( $parsedContent, $packerLevel );
			    }

    			$file = $path . '/' . $file;
    			$clusterFile = eZClusterFileHandler::instance( $file );
    			$clusterFile->storeContents( $parsedContent, 'ezless', 'text/css' );
    			eZURI::transformURI( $file, true );
    			$html = '<link rel="stylesheet" type="text/css" href="' . $file . '" />' . PHP_EOL;
    		}
			catch( Exception $e )
			{
                eZDebug::writeError( $e->getMessage(), 'ezLessOperator parsing error' );
			}
		}

		return $html;
	}


	/**
	 * Returns packer Level as defined in ezjscore.ini
	 * borrowed from ezjscore
	 * @return int
	 */
	private function getPackerLevel()
	{
	    $ezjscINI = eZINI::instance( 'ezjscore.ini' );
	    // Only pack files if Packer is enabled and if not set DevelopmentMode is disabled
        if ( $ezjscINI->hasVariable( 'eZJSCore', 'Packer' ) )
        {
            $packerIniValue = $ezjscINI->variable( 'eZJSCore', 'Packer' );
            if ( $packerIniValue === 'disabled' )
                return 0;
            else if ( is_numeric( $packerIniValue ) )
                return (int) $packerIniValue;
        }
        else
        {
            if ( eZINI::instance()->variable( 'TemplateSettings', 'DevelopmentMode' ) === 'enabled' )
            {
                return 0;
            }
            else return 3;
        }
	}

	/**
	 * Optimizes CSS content using ezjscore
	 * Using either INI optimzers or optimizeCSS if ezjscore is an older version
	 * @param string $content
	 * @param int $packerLevel
	 * @return string
	 */
	private function optimizeCSS( $content, $packerLevel )
	{
	    $ezjscINI = eZINI::instance( 'ezjscore.ini' );
	    if( $ezjscINI->hasVariable( 'eZJSCore', 'CssOptimizer' ) )
	    {
            foreach( $ezjscINI->variable( 'eZJSCore', 'CssOptimizer' ) as $optimizer )
            {
                $content = call_user_func( array( $optimizer, 'optimize' ), $content, $packerLevel );
            }
	    }
	    elseif ( method_exists( 'ezjscPacker', 'optimizeCSS') )
	    {
	        $content = ezjscPacker::optimizeCSS( $content, $packerLevel );
	    }

	    return $content;
	}
}

?>