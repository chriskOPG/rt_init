<?php

session_start();
header('Content-Type: text/html; charset=utf-8');

DEFINE("ROOT_PATH", dirname( __FILE__ ) ."/" );
DEFINE("VIEWS_PATH", dirname( __FILE__ ) ."/front/views/" );
DEFINE("FRAMEWORK_PATH", dirname( __FILE__ ) ."/back/" );
DEFINE("SALT", "7HLyiu6r7GV8g8g76Ggy7sq0d9XCVPev9" ); // password salt

// Authorize.net credentials for credit card processing
DEFINE("AUTHORIZENET_SANDBOX", true); // set to true if using the Authorize.net test account (sandbox)
DEFINE("AUTHORIZENET_API_LOGIN_ID", "4Uh6v6LzY"); // test account api login id
DEFINE("AUTHORIZENET_TRANSACTION_KEY", "9752ca5CJyN9q99M"); // test account transaction key
//DEFINE("AUTHORIZENET_API_LOGIN_ID", "7c6CsQ9K"); // real account api login id
//DEFINE("AUTHORIZENET_TRANSACTION_KEY", "7zb242BNJu436vMg"); // real account transaction key

DEFINE("RAVETREE_ADMIN", "1" ); // TESTING SERVER: the user id of the Ravetree system administer (Ravetree Support)
//DEFINE("RAVETREE_ADMIN", "167" ); // PRODUCTION SERVER: the user id of the Ravetree system administer (Ravetree Support)
DEFINE("BASE_URL", "http://localhost:8888/RAVETREE_MAIN/");// TESTING SERVER: base URL
//DEFINE("BASE_URL", "http://www.ravetree.com/"); // PRODUCTION SERVER: base URL
DEFINE("UPLOADS_PATH", "privateuploads/"); // TESTING SERVER: path to the uploads directory located outside of public_html
//DEFINE("UPLOADS_PATH", "../uploads/"); // PRODUCTION SERVER: path to the uploads directory located outside of public_html
DEFINE("UPLOADS", "uploads/"); // same for TESTING & PRODUCTION SERVER: path to the uploads directory, as needed for images

require('back/registry/registry.class.php');
$registry = new Registry();

// setup our core registry objects
$registry->createAndStoreObject( 'template', 'template' );
$registry->createAndStoreObject( 'mysqldb', 'db' );
$registry->createAndStoreObject( 'authenticate', 'authenticate' );
$registry->createAndStoreObject( 'urlprocessor', 'url' );
$registry->getObject('url')->getURLData();

// database settings
include(ROOT_PATH . 'config.php');
// create a database connection
$registry->getObject('db')->newConnection( $configs['db_host_sn'], $configs['db_user_sn'], $configs['db_pass_sn'], $configs['db_name_sn']);

$controller = $registry->getObject('url')->getURLBit(0); // The first element in the url array is the controller (e.g., "profile")

if( $controller != 'api' )
{
    $registry->getObject('authenticate')->checkForAuthentication();
}

// store settings in our registry
$settingsSQL = "SELECT `key`, `value` FROM settings"; // Select the columns `key` and `value` from the database table "settings"
$registry->getObject('db')->executeQuery( $settingsSQL );

while( $setting = $registry->getObject('db')->getRows() )
{
    $registry->storeSetting( $setting['value'], $setting['key'] );
}

$registry->getObject('template')->getPage()->addTag( 'siteurl', $registry->getSetting('siteurl') );

$controllers = array();
$controllersSQL = "SELECT * FROM controllers WHERE active=1";
$registry->getObject('db')->executeQuery( $controllersSQL );

while( $cttrlr = $registry->getObject('db')->getRows() )
{
    $controllers[] = $cttrlr['controller'];
}

// Only add authentication-related template bits to the view, if the active controller isn't API
if( $registry->getObject('authenticate')->isLoggedIn() && $controller != 'api')
{
    if($controller == '' || $controller == 'invite')
    {
        $controller = 'profile';
    }
}
// the user has not yet logged in, so show them the homepage
elseif( $controller != 'api' )
{
    $registration_block = file_get_contents(VIEWS_PATH . 'homepage/rt_main_registration_block.tpl.php', FILE_USE_INCLUDE_PATH);
    // splash_page tells us from which page the user signed up
    $registration_block = str_replace("{splash_page}", 'main', $registration_block);

    $registry->getObject('template')->getPage()->addTag('registration_block', $registration_block);
    $registry->getObject('template')->buildFromTemplates('front/views/homepage/rt_main.tpl.php');

    $registry->getObject('template')->getPage()->addTag( 'heading', '' );
    $registry->getObject('template')->getPage()->addTag( 'content', '' );

    $reasons = file_get_contents(VIEWS_PATH . 'homepage/rt_reasons.tpl.php', FILE_USE_INCLUDE_PATH);
    $registry->getObject('template')->getPage()->addTag('reasons', $reasons);
}

if($controller == 'invite')
{
    $invite_key = $registry->getObject('url')->getURLBit(1);
    $registry->getObject('template')->getPage()->addTag('invite_key', $invite_key);
}
else
{
    $registry->getObject('template')->getPage()->addTag('invite_key', '');
}

if( $registry->getObject('authenticate')->isLoggedIn() )
{
    $user = $registry->getObject('authenticate')->getUser()->getUserID();
    
    // fill in common tags that are required for front->ravetree->shell.php
    require_once( FRAMEWORK_PATH . 'models/profile.php');
    $p = new Profile($registry, $user);
    $p->fillCommonTags($user);
}
else
{
    $user = 0;
}

if( in_array( $controller, $controllers ) )
{
    require_once(FRAMEWORK_PATH . 'controllers/' . $controller . '/controller.php');
    $controllerInc = $controller.'controller';
    
    if($controller == 'info') // info pages can be viewed if the user is not logged in
    {
        $controller = new $controllerInc($registry);
    }
    else
    {
        $controller = new $controllerInc($registry, $user);
    }
}
else
{
    // default controller?
}

$registry->getObject('template')->parseOutput();
print $registry->getObject('template')->getPage()->getContentToPrint();

?>