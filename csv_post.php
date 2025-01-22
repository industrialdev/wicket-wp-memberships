<?php

use Wicket_Memberships\Membership_Tier;
use Wicket_Memberships\Membership_Config;

ini_set('display_errors', '0');
ini_set('max_execution_time', '0');

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

if(!empty($_REQUEST['mship_wipe_next_payment_date'])) {
  wicket_wipe_membership_subscription_next_payment_dates();
}

function wicket_wipe_membership_subscription_next_payment_dates() {
  $status = [ 'active','expired','delayed' ];

  $args = array(
    'post_type' => 'wicket_membership',
    'post_status' => 'publish',
    'meta_key' => 'user_id',
    'posts_per_page' => -1,
    'meta_query'     => array(
      array(
        'key'     => 'membership_status',
        'value'   => $status,
        'compare' => 'IN'
      ),
      array(
        'key'     => 'membership_subscription_id',
        'value'   => '',
        'compare' => '!='
      )
    )
  );
  $posts = new \WP_Query( $args );
  $cnt=0;
  foreach($posts->posts as $post) {
    $membership_subscription_id = get_post_meta( $post->ID, 'membership_subscription_id', true );
    $sub = wcs_get_subscription( $membership_subscription_id );
    echo 'clearing next_payment on subscription_id '.$membership_subscription_id.' for membership ID:'.$post->ID.'<br>';
    if(!empty($_REQUEST['no_debug'])) {
      $clear_dates_to_update['next_payment'] = '';
      $sub->update_dates($clear_dates_to_update);
      echo 'cleared<br>';
    }
  }
  die('terminated');
}

if(!empty($_REQUEST['mship_config_resync'])) {
  wicket_sync_membership_renewal_data_with_config();
}

function wicket_sync_membership_renewal_data_with_config() {
  $args = array(
    'post_type' => 'wicket_membership',
    'post_status' => 'publish',
    'meta_key' => 'user_id',
    'posts_per_page' => -1,
    
    'meta_query'     => array(
      array(
        'key'     => 'membership_type',
        'value'   => 'organization',
        'compare' => '='
      ),
      array(
        'key'     => 'membership_status',
        'value'   => 'active',
        'compare' => '='
      )
    )
  );
  # These will group the memberships by $query_args[ 'meta_key' => 'user_id' ]
  #add_filter('posts_groupby', 'wicket_get_members_list_group_by_filter' );
  $posts = new \WP_Query( $args );
  # These will group the memberships by $query_args[ 'meta_key' => 'user_id' ]
  #remove_filter('posts_groupby', 'wicket_get_members_list_group_by_filter' );
  $cnt=0;
  foreach($posts->posts as $post) {
    $cnt++;

    $membership_tier_post_id = get_post_meta( $post->ID, 'membership_tier_post_id', true );
    $membership_ends_at = get_post_meta( $post->ID, 'membership_ends_at', true );
    $membership_early_renew_at = get_post_meta( $post->ID, 'membership_early_renew_at', true );
    $Membership_Tier = new Membership_Tier( $membership_tier_post_id );
    $config_id = $Membership_Tier->get_config_id();
    if(!empty($_REQUEST['early_renew_days'])) {
      $early_renew_days = $_REQUEST['early_renew_days'];
    } else {
      $Membership_Config = new Membership_Config( $config_id );
      $early_renew_days = $Membership_Config->get_renewal_window_days();  
    }
    $ends_at_date_format =  date("Y-m-d H:i:s", strtotime( $membership_ends_at ));
    $early_renew_date_seconds = strtotime("- $early_renew_days days", strtotime( $ends_at_date_format ));
    $early_renew_date_tz  = (new \DateTime( date("Y-m-d H:i:s", $early_renew_date_seconds ), wp_timezone() ))->format('Y-m-d\TH:i:s\Z');

    echo '<br>scanning: '.$post->ID.'  - '. $config_id  .'-'. $membership_early_renew_at.' | '. $membership_ends_at. ' minus '. $early_renew_days . ' days = '.$early_renew_date_tz . '<br>';    

    if(!empty($early_renew_date_tz) && ($membership_early_renew_at != $early_renew_date_tz)) {
      echo '<pre>';
      var_dump(['updated', $post->ID, $early_renew_date_tz]);
      echo '</pre>';
      if(!empty($_REQUEST['no_debug'])) {
        update_post_meta($post->ID, 'membership_early_renew_at', $early_renew_date_tz);
        echo '<br>..updated..<br>';
      }
    } else {
      echo '<pre>';
      var_dump(['found', $post->ID, $early_renew_date_tz]);
      echo '</pre>';
    }
  }
  die('terminated');
}

if(!empty($_REQUEST['mship_tier_resync'])) {
  wicket_sync_membership_renewal_data_with_tier();
}

function wicket_sync_membership_renewal_data_with_tier() {
  $args = array(
    'post_type' => 'wicket_membership',
    'post_status' => 'publish',
    'meta_key' => 'user_id',
    'posts_per_page' => -1,
    
    'meta_query'     => array(
      array(
        'key'     => 'membership_type',
        'value'   => 'organization',
        'compare' => '='
      ),
      array(
        'key'     => 'membership_status',
        'value'   => 'active',
        'compare' => '='
      )
    )
  );
  # These will group the memberships by $query_args[ 'meta_key' => 'user_id' ]
  #add_filter('posts_groupby', 'wicket_get_members_list_group_by_filter' );
  $posts = new \WP_Query( $args );
  # These will group the memberships by $query_args[ 'meta_key' => 'user_id' ]
  #remove_filter('posts_groupby', 'wicket_get_members_list_group_by_filter' );
  $cnt=0;
  foreach($posts->posts as $post) {
    $cnt++;
    $membership_next_tier_form_page_id = get_post_meta( $post->ID, 'membership_next_tier_form_page_id', true );
    $membership_next_tier_id = get_post_meta( $post->ID, 'membership_next_tier_id', true );
    echo '<br>scanning: '.$post->ID.'  - '. $membership_next_tier_id .'-'.$membership_next_tier_form_page_id. '<br>';
    if(empty($membership_next_tier_form_page_id)) {
      $tier_post_id = get_post_meta( $post->ID, 'membership_tier_post_id', true );
      $Tier = new Membership_Tier($tier_post_id);
      echo $Tier->get_mdp_tier_name().'<br>';
      $next_tier_form_id = $Tier->get_next_tier_form_page_id();
      echo '<pre>';
      var_dump(['updated', $post->ID, $next_tier_form_id]);
      echo '</pre>';
      if(!empty($_REQUEST['no_debug'])) {
        update_post_meta($post->ID, 'membership_next_tier_form_page_id', $next_tier_form_id);
        update_post_meta($post->ID, 'membership_next_tier_id', '');
        echo '<br>..updated..<br>';
      }
    } else {
      echo '<pre>';
      var_dump(['found', $post->ID, $membership_next_tier_form_page_id]);
      echo '</pre>';
    }
  }
  die('terminated');
}


include WP_PLUGIN_DIR . '/wicket-wp-memberships/includes/Import_Controller.php';

$uploaddir = wp_upload_dir();
$uploadfile = $uploaddir['basedir'] . '/' . basename($_FILES['userfile']['name']);

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
    <input type="text" name="api_domain"><br>
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

$full_endpoint = '/wp-json/wicket_member/v1/import/' . $endpoint;
if( $_REQUEST['api_domain'] != '' ) {
  $import_url = $_REQUEST['api_domain'] . $full_endpoint;
} else {
  $import_url = get_site_url() . $full_endpoint;
}
echo '<br>' . $import_url . '<br>';

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