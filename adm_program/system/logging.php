<?php
/**
 ***********************************************************************************************
 * Init Admidio Logger
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'logging.php')
{
    exit('This page may not be called directly!');
}

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Processor\IntrospectionProcessor;

// check log folder in "adm_my_files" and create if necessary
$myFilesLogs = new MyFiles('LOGS');
if (!$myFilesLogs->checkSettings())
{
    error_log('Log folder could not be created! [error_text: ' . $myFilesLogs->errorText . ', error_path: ' . $myFilesLogs->errorPath . ']');
}

$gLogger = new Logger('Admidio');

$logLevel = Logger::WARNING;
if ($gDebug)
{
    $logLevel = Logger::DEBUG;
}

// Append line/file/class/function where the log message came from
$inspectionProcessor = new IntrospectionProcessor();
$gLogger->pushProcessor($inspectionProcessor);

// Params: format, dateFormat, allowInlineLineBreaks, ignoreEmptyContextAndExtra
$formatter = new LineFormatter(null, 'Y-m-d H:i:s.u', false, true);

$rotatingFileHandler = new RotatingFileHandler(ADMIDIO_PATH . FOLDER_DATA . '/logs/admidio.log', 0, $logLevel, true, 0666);
$errorLogHandler = new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::ERROR);

$rotatingFileHandler->setFormatter($formatter);
$errorLogHandler->setFormatter($formatter);

$gLogger->pushHandler($rotatingFileHandler);
$gLogger->pushHandler($errorLogHandler);

$gLogger->info('#####################################################################################################');
$gLogger->info('URL: ' . CURRENT_URL);

// Log Constants
$constants = array(
    'VERSIONS' => array(
        'ADMIDIO' => ADMIDIO_VERSION_TEXT,
        'PHP'     => PHP_VERSION
    ),
    'ADMIDIO_HOMEPAGE' => ADMIDIO_HOMEPAGE,
    'HTTPS'            => HTTPS,
    'PORT'             => PORT,
    'HOST'             => HOST,
    'DOMAIN'           => DOMAIN,
    'ADMIDIO_URL_PATH' => ADMIDIO_URL_PATH,
    'URLS' => array(
        'ADMIDIO_URL' => ADMIDIO_URL,
        'FILE_URL'    => FILE_URL,
        'CURRENT_URL' => CURRENT_URL
    ),
    'PATHS' => array(
        'WWW_PATH'     => WWW_PATH, // Will get "SERVER_PATH" in v4.0
        'ADMIDIO_PATH' => ADMIDIO_PATH,
        'CURRENT_PATH' => CURRENT_PATH
    ),
    'FOLDERS' => array(
        'FOLDER_DATA'        => FOLDER_DATA,
        'FOLDER_CLASSES'     => FOLDER_CLASSES,
        'FOLDER_LIBS_SERVER' => FOLDER_LIBS_SERVER,
        'FOLDER_LIBS_CLIENT' => FOLDER_LIBS_CLIENT,
        'FOLDER_LANGUAGES'   => FOLDER_LANGUAGES,
        'FOLDER_THEMES'      => FOLDER_THEMES,
        'FOLDER_MODULES'     => FOLDER_MODULES,
        'FOLDER_PLUGINS'     => FOLDER_PLUGINS
    )
);
$gLogger->info('CONSTANTS: URLS & PATHS & FOLDERS', $constants);
