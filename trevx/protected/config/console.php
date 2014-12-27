<?php

// This is the configuration for yiic console application.
// Any writable CConsoleApplication properties can be configured here.
return array (
		'basePath' => dirname ( __FILE__ ) . DIRECTORY_SEPARATOR . '..',
		'name' => 'Trevx Application',
		'import' => array(
				'application.lib.*',
				'application.extensions.solr.*',
		
		),
		
		// preloading 'log' component
		'preload' => array (
				'log' 
		),
		
		// application components
		'components' => array (
				'collection1' => array (
						'class' => 'CSolrComponent',
						'host' => 'localhost',
						'port' => 8983,
						'indexPath' => '/solr/collection1' 
				),
				
				'db' => array (
						'connectionString' => 'mysql:host=localhost;dbname=trevx',
						'emulatePrepare' => true,
						'username' => 'root',
						'password' => '123123',
						'charset' => 'utf8' 
				),
				
				'log' => array (
						'class' => 'CLogRouter',
						'routes' => array (
								array (
										'class' => 'CFileLogRoute',
										'levels' => 'error, warning' 
								) 
						) 
				) 
		) 
);