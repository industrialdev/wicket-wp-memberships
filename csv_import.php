<?php

if(file_exists('../../../wp/wp-load.php')) {
  require '../../../wp/wp-load.php';
} else if(file_exists('../../../wp-load.php')) {
  require  '../../../wp-load.php';
} else {
  die('Could not load wp?');
}

if( empty( $_ENV['ALLOW_LOCAL_IMPORTS'] )) {
  die("Disabled");
}

include WP_PLUGIN_DIR . '/wicket-wp-memberships/includes/Import_Controller.php';
flush();
ob_flush();

if( count($argv) < 3 || strpos($argv[1], 'help') !== false ) {
  echo "\n\nphp csv_import.php { individual|organization } { file_path from /uploads/ } { api_domain - optional str } { skip_approval - optional bool }\n\n";
  exit;
}
var_dump($argv);

$entity_type = $argv[1];
$file_path = $argv[2];
$api_domain = $argv[3];
$skip_approval = $argv[4];

$uploaddir = wp_upload_dir();
// uploads dated folder and filename with extension uploaded to wp 
// Ex. 2024/10/cpa-wp-memberships-wrong-dates-2024-10-03-1-test-run.csv
$uploadfile = $uploaddir['basedir'] . '/' . $file_path; 
if( ! in_array($entity_type, ['individual', 'organization']) ) {
  echo "\n\nError: arg[1] - Membership type not found (individual|organization)\n\n";
  exit;
}

if(! file_exists( $uploadfile )) {
  echo "\n\nError: arg[2] - Import file not found ($uploadfile) \n\n";
  exit;
}

$path = $uploadfile;
$rows = [];
$handle = fopen($path, "r");
while (($row = fgetcsv($handle)) !== false) {
    $rows[] = $row;
}
fclose($handle);

$headers = array_shift($rows);
$headers = array_map( function( $item ) {
  if( $item = str_replace(" ", "_", $item) ) {
    return $item;
  }
}, $headers);

$array = [];
if( $entity_type == 'individual' ) {
  $endpoint = 'person_memberships';
} else if( $entity_type == 'organization' ) {
  $endpoint = 'membership_organizations';
}

$full_endpoint = '/wp-json/wicket_member/v1/import/' . $endpoint;
if( $api_domain != '' ) {
  $import_url = $api_domain . $full_endpoint;
} else {
  $import_url = get_site_url() . $full_endpoint;
}
echo "\n" . $import_url . "\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); 
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_URL, $import_url); 
curl_setopt($ch, CURLOPT_POST, true);

foreach ($rows as $row) {
    $array = array_combine($headers, $row);

    if( $entity_type == 'individual' && $array['Membership_Type'] != 'individual' ) {
      continue;
    }

    if( !empty( $skip_approval ) ) {
      $array['skip_approval'] = 1;
    }
    
    //echo 'Membership Post created: ID#' . $I->create_individual_memberships( $array ).'<br>';
    curl_setopt($ch, CURLOPT_POSTFIELDS, $array);
    echo "\n---------------------------------------\n";
    var_dump( curl_exec($ch) );
    echo "\n---------------------------------------\n";
    if (curl_errno($ch)) {
      $error_msg = curl_error($ch);
      var_dump( $error_msg );
    }
    flush();
    ob_flush();
  }
curl_close($ch);