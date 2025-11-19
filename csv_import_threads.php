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
  echo "\n\nphp ./csv_import_threads.php { individual|organization } { file_path from /uploads/ } { api_domain - optional str } { skip_approval - optional bool }\n\n";
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
$headers = array_map(function ($h) {
  $h = trim($h);
  $h = preg_replace('/[^a-zA-Z0-9_\s]/', '', $h);
  return $h;
}, $headers);
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

foreach ($rows as $row) {
  $row = array_map('mshipSanitizeCSVField', $row);
  if(empty($row[6])) {
    continue;
  }
  $array = array_combine($headers, $row);

  if( $entity_type == 'individual' && $array['Membership_Type'] != 'individual' ) {
    continue;
  }

  if( !empty( $skip_approval ) ) {
    $array['skip_approval'] = 1;
  }
  $full_array[] = $array;
}

$urls = array_fill(1, 25, $import_url);
$cnt = 0;

while($cnt < count($full_array)) {

  $mh = curl_multi_init();
  $requests = [];
  
  foreach ($urls as $i => $url) {
    if ($cnt >= count($full_array)) break; // Prevent out-of-bounds access
    $requests[$i] = curl_init($url);
    curl_setopt($requests[$i], CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($requests[$i], CURLOPT_RETURNTRANSFER, 1);  
    curl_setopt($requests[$i], CURLOPT_CUSTOMREQUEST, "POST"); 
    curl_setopt($requests[$i], CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($requests[$i], CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($requests[$i], CURLOPT_URL, $import_url); 
    curl_setopt($requests[$i], CURLOPT_POST, true);
    curl_setopt($requests[$i], CURLOPT_POSTFIELDS, $full_array[$cnt]);
    curl_multi_add_handle($mh, $requests[$i]);
    $cnt++;
  }

  // Execute all queries simultaneously, continue while there are active handles
  do {
    curl_multi_exec($mh, $active);
    curl_multi_select($mh); // Optional: Wait for activity on any curl-connection
  } while ($active);

  // Collect the responses
  $response = [];
  foreach ($requests as $i => $request) {
    $responseContent = curl_multi_getcontent($request);
    $response[] = $responseContent;

    // Check for errors
    if (curl_errno($request)) {
        $errors[$i] = curl_error($request);
    }

    curl_multi_remove_handle($mh, $request);
    curl_close($request);

    echo "\n---------------------------------------\n";
    var_dump( $responseContent );
    echo "\n---------------------------------------\n";
  }

// Close the multi handle
  curl_multi_close($mh);

  echo "batched: $cnt\n";
  flush();
  ob_flush();

  sleep(1);
}
function mshipSanitizeCSVField($text) {
  return preg_replace('/[^\x20-\x7E\t]/u', '', trim($text));
}
