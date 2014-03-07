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

class ezLessOperator
{
    
    const
        STYLESHEETS_FOLDER = '/public/stylesheets';
    
    private
        $operators,
        $phpLessProcessor,
        $configuration;
    
    static
        $files = array(),
        $imports = array();

    function __construct()
    {
        $this->operators        = array( 'ezless', 'ezless_add', 'ezless_imports' );
        $this->phpLessProcessor = new lessc();
        $this->configuration    = eZINI::instance('ezless.ini');
    }

    function &operatorList()
    {
        return $this->operators;
    }

    function namedParameterPerOperator()
    {
        return true;
    }

    function namedParameterList()
    {
        return array(
            'ezless' => array(),
            'ezless_add' => array(),
            'ezless_imports' => array()
        );
    }

    function modify( $tpl, $operatorName, $operatorParameters, $rootNamespace, $currentNamespace, &$operatorValue, $namedParameters )
    {
        switch($operatorName)
        {
            case 'ezless':
                $operatorValue = $this->loadFiles($operatorValue);
                break;
            case 'ezless_add':
                $operatorValue = $this->addFiles($operatorValue);
                break;
            case 'ezless_imports':
                $operatorValue = $this->registerImports($operatorValue);
                break;
        }
    }

    public function loadFiles( $files )
    {
        $pageLayoutFiles = array();
        $afiles = (array)$files;
        
        if( count( $afiles ) > 0 )
        {
            foreach( $afiles as $file )
            {
                $pageLayoutFiles[] = $file;
            }
        }
        
        $files = $this->prependArray(self::$files, $pageLayoutFiles);
        
        return $this->generateTag($files);
    }

    public function registerImports( $files )
    {
        if( is_array( $files ) )
        {
            foreach( $files as $file )
            {
                self::$imports[] = $file;
            }
        }
        else
        {
            self::$imports[] = $files;
        }
    }
    
    public function addFiles($files)
    {
        if( is_array( $files ) )
        {
            foreach( $files as $file )
            {
                self::$files[] = $file;
            }
        }
        else
        {
            self::$files[] = $files;
        }
    }
    
    private function prependArray( $array, $prepend )
    {
        $return = $prepend;
        
        foreach( $array as $value)
        {
            $return[] = $value;
        }
        
        return $return;
    }

    private function generateTag( $files )
    {
        eZDebug::writeDebug($files, 'ezLessOperator::generateTag');
        
        $compileMethod = trim( $this->configuration->variable( 'ezlessconfig', 'CompileMethod'  ) );
        
        if( $compileMethod === 'javascript' )
        {
            return $this->compileWithJavascript($files);
        }
        elseif( $compileMethod === 'lessphp' )
        {
            return $this->compileWithLessPHP($files);
        }
        else
        {
            eZDebug::writeError( "Unknown compile method : '{$compileMethod}'", __CLASS__ . "::" . __FUNCTION__ );
        }
        
        return '';
    }
    
    private function compileWithJavascript($files)
    {
        $html = '';
        
        foreach ( $files as $file )
        {
            $lessPath = $this->getLessFilePath($file);
            if( $lessPath !== false )
            {
                $html .= sprintf(
                    '<link rel="stylesheet/less" href="%s" type="text/css" />%s',
                    $this->getFileUrl($lessPath),
                    PHP_EOL
                );
            }
        }
        
        $lessJSFilename = $this->configuration->variable('ezlessconfig','LessJSFile');
        $lessJSProcessorFilePath = $this->getFilePathFromDesign('javascript', $lessJSFilename);
        
        if ($lessJSProcessorFilePath === false)
        {
            eZDebug::writeDebug( "Using LessJS mode but unable to find less.js (LessJSFile={$lessJSFilename}).\nTried files : " . implode( "\n", $triedFiles ) , __CLASS__ . "::" . __FUNCTION__ );
        }
        else
        {
            $html .= sprintf(
                '<script src="%s" type="text/javascript" ></script>%s',
                $this->getFileUrl($lessJSProcessorFilePath),
                PHP_EOL
            );
        }
        
        return $html;
    }
    
    private function getFileUrl($filePath)
    {
        eZURI::transformURI( $filePath, true, 'full' );
        return $filePath;
    }
    
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
    
    private function compileWithLessPHP($files)
    {
        $useOneFile = $this->configuration->variable( 'ezlessconfig','useOneFile' );
        $this->initializeImportLessDirectories();
        
        if( $useOneFile === "true" )
        {
            return $this->compileWithLessPHPIntoOneFile($files);
        }
        else
        {
            return $this->compileWithLessPHPForEachFile($files);
        }
    }
    
    private function initializeImportLessDirectories()
    {
        $bases = eZTemplateDesignResource::allDesignBases();
        
        foreach( $bases as $base )
        {
            $this->phpLessProcessor->importDir[] = $base . DIRECTORY_SEPARATOR . 'stylesheets';
        }
    }
    
    private function compileWithLessPHPIntoOneFile($files)
    {
        $lessContent    = '';
        $lessPaths = array();
        
        foreach( $files as $file)
        {
            $lessPath = $this->getLessFilePath($file);
            if ($lessPath !== false)
            {
                $lessPaths[]= $lessPath;
            }
        }
        
        $cachedFileName = $this->getCachedCssFilePath($lessPaths);
        
        if (is_file($cachedFileName) === false)
        {
            foreach ($lessPaths as $lessPath)
            {
                $content = $this->readLessContent($lessPath);
                $lessContent .= $content.PHP_EOL;
            }
            
            try
            {
                $this->storeCssFile($cachedFileName, $this->parseLess($lessContent));
            }
            catch( Exception $e )
            {
                eZDebug::writeError( $e->getMessage(), 'ezLessOperator for ' . implode(', ', $files));
            }
        }
        
        return $this->buildStylesheetTag($cachedFileName);
    }
    
    private function compileWithLessPHPForEachFile($files)
    {
        $html       = '';
        
        foreach( $files as $file)
        {
            $lessPath = $this->getLessFilePath($file);
            
            if ($lessPath !== false)
            {
                $cachedFileName = $this->getCachedCssFilePath(array($lessPath));
                
                if (is_file($cachedFileName) === false)
                {
                    $content = $this->readLessContent($lessPath);
                    $importContent = $this->readImportedContent();
                    
                    try
                    {
                        $this->storeCssFile($cachedFileName, $this->parseLess($importContent.$content));
                    }
                    catch( Exception $e )
                    {
                        eZDebug::writeError( $e->getMessage(), 'ezLessOperator for ' . $file);
                    }
                }
                
                $html .= $this->buildStylesheetTag($cachedFileName);
            }
        }
        
        return $html;
    }
    
    private function getCachedCssFilePath(array $lessPathFiles)
    {
        $fileNamesAndTimes = array();
        
        foreach ($lessPathFiles as $lessPathFile)
        {
            if (!is_file($lessPathFile))
            {
                eZDebug::writeError('Less File "'.$lessPathFile.'" does not exist !');
            }
            else
            {
                $lastModifiedLessFileTime = filemtime($lessPathFile);
                $fileNamesAndTimes[] = $lessPathFile.'-'.$lastModifiedLessFileTime;
                
            }
        }
        if (empty($fileNamesAndTimes))
        {
            return false;
        }
        
        $sys  = eZSys::instance();
        $path = $sys->cacheDirectory() . self::STYLESHEETS_FOLDER;
        
        return sprintf(
            '%s/%s.css',
            $path,
            md5(implode('',$fileNamesAndTimes))
        );
    }
    
    private function generateCSSFileAndHTMLTag($fileName, $lessContent, array $files)
    {
        try
        {
            $this->storeCssFile($fileName, $this->parseLess($lessContent));
            return $this->buildStylesheetTag($fileName);
        }
        catch( Exception $e )
        {
            eZDebug::writeError( $e->getMessage(), 'ezLessOperator for ' . implode(', ', $files));
        }
        
        return false;
    }
    
    private function readImportedContent()
    {
        $importContent = '';
        
        if( count( self::$imports ) > 0 )
        {
            foreach( self::$imports as $import )
            {
                $lessPath = $this->getLessFilePath($import);
                
                if ($lessPath !== false)
                {
                    $importCss = file_get_contents($lessPath);
                    $importContent .= $importCss;
                }
            }
        }
        
        return $importContent;
    }
    
    private function getLessFilePath($file)
    {
        return $this->getFilePathFromDesign('stylesheets', $file);
    }
    
    private function getFilePathFromDesign($sourceFolder, $fileName)
    {
        $triedMatchedPathFiles = array();
        $bases = eZTemplateDesignResource::allDesignBases();
        
        $matchedFile = eZTemplateDesignResource::fileMatch(
            $bases,
            '',
            sprintf('%s/%s', trim($sourceFolder, '/'), $fileName),
            $triedMatchedPathFiles
        );
        
        if ($matchedFile !== false)
        {
            return $matchedFile['path'];
        }
        
        return false;
    }
    
    private function readLessContent($path)
    {
        if (is_file($path) === false)
        {
            eZDebug::writeError('Less File "'.$path.'" does not exist !');
            return '';
        }
        
        $content = file_get_contents($path);
        if($content !== false)
        {
            $content = ezjscPacker::fixImgPaths($content, $path);
            return $content;
        }
        
        return '';
    }
    
    private function storeCssFile($file, $content)
    {
        $clusterFile = eZClusterFileHandler::instance($file);
        $clusterFile->storeContents( $content, 'ezless', 'text/css' );
    }
    
    private function buildStylesheetTag($cssFile)
    {
        return '<link rel="stylesheet" type="text/css" href="' . $this->getFileUrl($cssFile) . '" />' . PHP_EOL;
    }
    
    private function parseLess($lessContent)
    {
        $parsedContent = $this->phpLessProcessor->parse($lessContent);
        $packerLevel   = $this->getPackerLevel();
        
        if( $packerLevel > 1 )
        {
            $parsedContent = $this->optimizeCSS($parsedContent, $packerLevel);
        }
        
        return $parsedContent;
    }
    
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