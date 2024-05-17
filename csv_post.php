<?php
require  '/var/www/html/web/wp/wp-load.php';
if( ! env('ALLOW_LOCAL_IMPORTS')) {
  die();
}
include '/var/www/html/web/app/plugins/wicket-wp-memberships/includes/Import_Controller.php';


$uploaddir = '/var/www/html/web/app/uploads/';
$uploadfile = $uploaddir . basename($_FILES['userfile']['name']);

if( !empty($_FILES['userfile']['tmp_name']) && empty($_REQUEST['upload_type'])) {
  echo 'Choose a upload file type.<br><br>';
  unset($_FILES['userfile']['tmp_name']);
  unlink($_FILES['userfile']['tmp_name']);
}

if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
    echo "File is valid, and was successfully uploaded.\n<br>".$_FILES['userfile']['tmp_name'];
    $uploaded = true;
} else if( !empty( $_FILES['userfile']['tmp_name'] )) {
    echo "Possible file upload attack!\n";
} else {
  ?><h3>MDP Export file uploads</h3>
<form enctype="multipart/form-data" action="./csv_post.php" method="POST">
  <input type="radio" value="individual" name="upload_type"><label for="upload_type">membership_person.csv</label><br>
  <input type="radio" value="organization" name="upload_type"><label for="upload_type">organization_memberships.csv</label><br><br>
  <input type="checkbox" value="true" name="skip_approval"><label for="skip_approval">?skip_approval=1</label><br><br>
    Send this CSV file: <input name="userfile" type="file" />
    <input type="submit" value="Send File" />
</form>

  <?php
}

if( empty($uploaded)) {
  die();
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
if( $_REQUEST['upload_type'] == 'individual' ) {
  $endpoint = 'person_memberships';
} else if( $_REQUEST['upload_type'] == 'organization' ) {
  $endpoint = 'membership_organizations';
}
$import_url = 'https://nginx/wp-json/wicket_member/v1/import/' . $endpoint;

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
    
    if( $_REQUEST['upload_type'] == 'individual' && $array['Membership_Type'] != 'individual' ) {
      continue;
    }

    if( !empty( $_REQUEST['skip_approval'] ) ) {
      $array['skip_approval'] = 1;
    }
    
    //echo 'Membership Post created: ID#' . $I->create_individual_memberships( $array ).'<br>';
    curl_setopt($ch, CURLOPT_POSTFIELDS, $array);
    echo "<br>---------------------------------------<br>";
    var_dump( curl_exec($ch) );
    echo "<br>---------------------------------------<br>";
    if (curl_errno($ch)) {
      $error_msg = curl_error($ch);
      var_dump( $error_msg );
    }
  }
curl_close($ch);