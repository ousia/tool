#!/usr/bin/php
<?php

$basePath = realpath( __DIR__ . '/..' );
global $wsexportConfig;

$wsexportConfig = [
	'basePath' => $basePath, 'stat' => true, 'tempPath' => sys_get_temp_dir()
];

include_once $basePath . '/book/init.php';

class WSExportInvalidArgumentException extends Exception {
}

function parseCommandLine() {
	global $wsexportConfig;

	$long_opts = [
		'lang:', 'title:', 'format:', 'path:', 'debug', 'tmpdir:',
		'nocredits'
	];

	$lang = null;
	$title = null;
	$format = 'epub';
	$path = '.';
	$options = [];
	$options['images'] = true;

	$opts = getopt( 'l:t:f:p:d', $long_opts );
	foreach ( $opts as $opt => $value ) {
		switch ( $opt ) {
			case 'lang':
			case 'l':
				$lang = $value;
				break;
			case 'title':
			case 't':
				$title = $value;
				break;
			case 'format':
			case 'f':
				$format = $value;
				break;
			case 'path':
			case 'p':
				$path = $value . '/';
				break;
			case 'tmpdir':
				$tempPath = realpath( $value );
				if ( !$tempPath ) {
					throw new Exception( "Error: $value does not exist." );
				}
				$wsexportConfig['tempPath'] = $tempPath;
				break;
			case 'debug':
			case 'd':
				error_reporting( E_STRICT | E_ALL );
				break;
			case 'nocredits':
				$options['credits'] = false;
				break;
		}
	}

	if ( !$lang or !$title ) {
		throw new WSExportInvalidArgumentException();
	}

	return [
		'title' => $title,
		'lang' => $lang,
		'format' => $format,
		'path' => $path,
		'options' => $options
	];
}

function getGenerator( $format ) {
	if ( $format == 'epub-2' ) {
		return new Epub2Generator();
	} elseif ( $format == 'epub-3' || $format == 'epub' ) {
		return new Epub3Generator();
	} elseif ( in_array( $format, ConvertGenerator::getSupportedTypes() ) ) {
		return new ConvertGenerator( $format );
	} else {
		throw new WSExportInvalidArgumentException( "The file format '$format' is unknown." );
	}
}
function createBook( $title, $lang, $format, $path, $options ) {
	date_default_timezone_set( 'UTC' );
	$generator = getGenerator( $format );
	$api = new Api( $lang );
	$provider = new BookProvider( $api, $options );
	$data = $provider->get( $title );
	$file = $generator->create( $data );
	$output = $path . '/' .  $title . '.' . $generator->getExtension();
	if ( !is_dir( dirname( $output ) ) ) {
		mkdir( dirname( $output ), 0755, true );
	}
	if ( !rename( $file, $output ) ) {
		throw new Exception( 'Unable to create output file: ' . $output );
	}
	return $output;
}

if ( isset( $argc ) ) {
	try {
		$arguments = parseCommandLine();
		$output = createBook( $arguments['title'], $arguments['lang'], $arguments['format'],
			$arguments['path'], $arguments['options'] );

		echo "The ebook has been created: $output\n";
	} catch ( WSExportInvalidArgumentException $exception ) {
		if ( !empty( $exception->getMessage() ) ) {
			fwrite( STDERR, $exception->getMessage() . "\n\n" );
		}
		fwrite( STDERR, file_get_contents( $basePath . '/cli/help/book.txt' ) );
		exit( 1 );
	} catch ( Exception $exception ) {
		fwrite( STDERR, "Error: $exception\n" );
		exit ( 1 );
	}
}
