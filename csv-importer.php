<?php
/*
Plugin Name: CSV Importer
Description: Create post dynamically via CSV file.
Version: 1.0.0
Author: Vladimir Morozov 
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
class CSVImporter {
	
	 	/**
         * Construct the plugin object
         */
        public function __construct()
        {
           add_action( 'admin_bar_menu', array(&$this,'toolbar_link_to_wpc'), 999 );
        } // END public function __construct
	  	 	
		public function toolbar_link_to_wpc( $wp_admin_bar ) {
			$args = array(
				'id'    => 'csv_menu_bar',
				'title' => 'CSV Importer',
				'href'  => admin_url('tools.php?page=csv-importer'),
				'meta'  => array( 'class' => 'csv-toolbar-page' )
			);
			$wp_admin_bar->add_node( $args );
			//second lavel
			$wp_admin_bar->add_node( array(
				'id'    => 'csv-second-sub-item',
				'parent' => 'csv_menu_bar',
				'title' => 'Settings',
				'href'  => admin_url('tools.php?page=csv-importer'),
				'meta'  => array(
					'title' => __('Settings'),
					'target' => '_self' 
				),
			));
		}
		
    var $log = array();
    
    function process_option($name, $default, $params) {
        if (array_key_exists($name, $params)) {
            $value = stripslashes($params[$name]);
        } elseif (array_key_exists('_'.$name, $params)) {
            // unchecked checkbox value
            $value = stripslashes($params['_'.$name]);
        } else {
            $value = null;
        }
        $stored_value = get_option($name);
        if ($value == null) {
            if ($stored_value === false) {
                if (is_callable($default) &&
                    method_exists($default[0], $default[1])) {
                    $value = call_user_func($default);
                } else {
                    $value = $default;
                }
                add_option($name, $value);
            } else {
                $value = $stored_value;
            }
        } else {
            if ($stored_value === false) {
                add_option($name, $value);
            } elseif ($stored_value != $value) {
                update_option($name, $value);
            }
        }
        return $value;
    }
 

function csv_importer_form() {
    $opt_draft = $this->process_option('csv_importer_import_as_draft', 'publish', $_POST);
    $opt_cat = $this->process_option('csv_importer_cat', 0, $_POST);

    if ('POST' == $_SERVER['REQUEST_METHOD']) {
        $this->post(compact('opt_draft', 'opt_cat'));
    }
?>

<div id="wp-settings"> 
<div class="wrap">
    <h1>CSV Importer </h1><hr />
    <form class="add:the-list: validate" method="post" enctype="multipart/form-data">
 
 
	<div class="csv-importer-settings">

	<div class="first csv-importer" id="div-csv-importer-general"> 
		
		<?php wp_nonce_field( 'wp_import_csv_action', 'wp_csv_nonce_field' ); ?>
 
        <p><label for="csv_import">Upload file:</label><input name="csv_import" id="csv_import" type="file" value="" aria-required="true" /></p>
        <?php submit_button("Import Now"); ?>
         
		<hr>
		
		<h3><a href="<?php echo plugins_url( 'sample/sample.csv',__FILE__ ); ?>" target="_blank">Download sample csv file</a></h3>
		
	</div>
  	</div>
  </form>
</div>

<?php }
function print_messages() {
    if (!empty($this->log)) {
 
?>
<div class="wrap">
    <?php if (!empty($this->log['error'])): ?>
    <div class="error">
        <?php foreach ($this->log['error'] as $error): ?>
            <p><?php echo $error; ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($this->log['notice'])): ?>
    <div class="updated fade">
        <?php foreach ($this->log['notice'] as $notice): ?>
            <p><?php echo $notice; ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($this->log['success'])): ?>
    <div class="updated fade">
        <?php foreach ($this->log['success'] as $success): ?>
            <p><?php echo $success; ?></p>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>
<?php
$this->log = array();
    }
    }
  
	
function post($options) {
       
    if ( ! isset( $_POST['wp_csv_nonce_field'] ) || ! wp_verify_nonce( $_POST['wp_csv_nonce_field'], 'wp_import_csv_action' ) ) {
		$this->log['error'][] = 'Invalid attempt';
        $this->print_messages();
		return;
	}
			
	if (!current_user_can('import')) {
		$this->log['error'][] = 'You are not permitted for import data';
        $this->print_messages();
		return;
	}
			
     
    if (empty($_FILES['csv_import']['tmp_name'])) {
        $this->log['error'][] = 'No file uploaded, aborting.';
        $this->print_messages();
        return;
    }

    if (!current_user_can('publish_posts')) {
        $this->log['error'][] = 'You don\'t have the permissions to publish posts.';
        $this->print_messages();
        return;
    }
        
	$csv_file = $_FILES['csv_import']['tmp_name']; 
	$filename = sanitize_file_name($_FILES['csv_import']['name']); 
	$type = strtolower(substr($filename,-3));

    if ($type!='csv') {
        $this->log['error'][] = 'File format is wrong.';
        $this->print_messages();
        return;
    }
        
    if (! is_file( $csv_file )) {
        $this->log['error'][] = 'Failed to load file';
        $this->print_messages();
        return;
    }

$postType = 'post';
$fldAry = array("custom_id",
			  "post_title",
			  "post_slug",
			  "menu_order",
			  "post_status",
			  "post_content",
			  "author_id",
			  'meta_title',
			  'meta_desc',
			  'meta_key'
	);
	$arry = $this->csvIndexArray($csv_file, ",", $fldAry, 0);
	$skipped = 0;
	$imported = 0;
	$time_start = microtime(true);
	$upload_dir = wp_upload_dir();
	$upload_path=$upload_dir['baseurl'];

	global $post,$wpdb;
	if(count($arry) > 0):
	foreach ($arry as $data) {
		$data = wp_slash($data);
 
		wp_reset_postdata();
		$user_id =get_current_user_id();
			if(isset($data['author_id']) && $data['author_id']!='')
			{
				$user_id=$data['author_id'];
			}
			$post_title=$data['post_title'];
			
			/* check post exist or not */
			if(isset($data['custom_id']) && $data['custom_id']!='')
			{
			$customId=trim($data['custom_id']);
			$mainquery="SELECT p.ID FROM ".$wpdb->prefix."posts p, ".$wpdb->prefix."postmeta meta WHERE 
				p.ID = meta.post_id 
				AND ( (meta.meta_key = '_wp_importer_unique_id' 
				AND meta.meta_value = '$customId') || (meta.meta_key = 'custom_id' 
				AND meta.meta_value = '$customId') )
				AND p.post_type = '$postType'
				limit 0,1";			
            $csvpost = $wpdb->get_results($mainquery, OBJECT);
			}
			else
			{
				$csvpost=true;
				return;
				// If no customID is passed, do not add/edit the record
			} 
           $check_post_status='0';
			 
			/* create new post */	
			$new_post = array(
				'post_title'   => convert_chars($data['post_title']),
				'menu_order'   => $data['menu_order'],
				'post_name'   => trim($data['post_slug']),
				'post_status'  => 'publish',
				'post_content' => wpautop(convert_chars($data['post_content'])),
				'post_type'    => $postType,
				'post_author'  => $user_id,
			);
			// Insert the post into the database
			$existpost_id = wp_insert_post($new_post);
 

			if($check_post_status=='1'){$msg='Updated';}else{$msg='Created';}	
			$this->log['success'][] = '#'.$existpost_id.'. '.$data['post_title'].' page is <b>'.$msg.'</b>';
			$this->print_messages();
			} 
	endif;

        if (file_exists($csv_file)) {
            @unlink($csv_file);
        }

        $exec_time = microtime(true) - $time_start;

        if ($skipped) {
            $this->log['notice'][] = "<b>Skipped {$skipped} posts (most likely due to empty title, body and excerpt).</b>";
        }
        $this->log['notice'][] = sprintf("<b>Imported {$imported} pages in %.2f seconds.</b>", $exec_time);
        $this->print_messages();
    }
/** Reterive data from csv file to array format */
function csvIndexArray($filePath='', $delimiter='|', $header = null, $skipLines = -1) {
         $lineNumber = 0;
         $dataList = array();
         //$headerItems = array();
        if (($handle = fopen($filePath, 'r')) != FALSE) {
			
		   while (($items = fgetcsv($handle, 1000, ",")) !== FALSE) 
		   {
			    if($lineNumber == 0)
			    { 
					//$header = $items; 
					$lineNumber++; continue; 
				}
				
				$record = array();
				for($index = 0, $m = count($header); $index < $m; $index++){
					//If column exist then and then added in data with header name
					if(isset($items[$index])) {
				   		$itmcont = trim(mb_convert_encoding(str_replace('"','',$items[$index]), "utf-8", "HTML-ENTITIES" ));
				   		$record[$header[$index]] = str_replace('#',',',$itmcont);
					}
				}
				$dataList[] = $record; 				
				 
				
			}			
           fclose($handle);
        }
        return $dataList;
    }
}
// Add settings link to plugin list page in admin
if(!function_exists('wp_importer_add_settings_link')):
function wp_importer_add_settings_link( $links ) {
	$settings_link = '<a href="tools.php?page=csv-importer">' . __( 'Settings', 'csv-importer' ) . '</a>';
	$settings_link .= ' | <a href="mailto:vovan16m@gmail">' . __( 'Contact to Author', 'csv-importer' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
endif;

$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'wp_importer_add_settings_link' );
function wpimport_admin_menu() {
    require_once ABSPATH . '/wp-admin/admin.php';
    $plugin = new CSVImporter;
    add_submenu_page('tools.php','CSV Importer', 'CSV Importer', 'manage_options','csv-importer',
        array($plugin, 'csv_importer_form'));
}
add_action('admin_menu', 'wpimport_admin_menu');
if (isset($_GET['page']) && $_GET['page'] == 'csv-importer') {
   add_action('admin_footer','init_wp_importer_admin_scripts');
}
if(!function_exists('init_wp_importer_admin_scripts')):
function init_wp_importer_admin_scripts()
{
wp_register_style( 'wp_importer_admin_style', plugins_url( 'css/admin-min.css',__FILE__ ) );
wp_enqueue_style( 'wp_importer_admin_style' );
echo $script='<script type="text/javascript">
	/* CSV Importer js for admin */
	jQuery(document).ready(function(){
		jQuery(".csv-importer").hide();
		jQuery("#div-csv-importer-general").show();
	    jQuery(".csv-importer-links").click(function(){
		var divid=jQuery(this).attr("id");
		jQuery(".csv-importer-links").removeClass("active");
		jQuery(".csv-importer").hide();
		jQuery("#"+divid).addClass("active");
		jQuery("#div-"+divid).fadeIn();
		});
	jQuery(".button-primary").click(function(){
	 if(confirm("Click OK to continue?")){
      }
	})
	}); 
	</script>'  ;
}
endif;
