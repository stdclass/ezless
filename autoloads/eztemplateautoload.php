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

$eZTemplateOperatorArray = array();
 
$eZTemplateOperatorArray[] = array( 'script' => 'extension/ezless/autoloads/ezless.php',
									'class' => 'ezLessOperator',
									'operator_names' => array( 'ezless', 'ezless_add', 'ezless_imports' ) );
							 
?>
