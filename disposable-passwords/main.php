<?php
/*
Plugin Name: Disposable Passwords
Description: Disposable Passwords to protect content
Version: 1.0
Author: Ehsan Marufi

*/
// The dependency is only due to the utilization of the 'parsidate()' function!
defined( 'ABSPATH' ) or die( 'No direct access please!' );

require_once 'lib/core.php';
Disposable_Passwords::init();

$plugin_slug = 'disposable-passwords';
$plugin_submenu_slug = 'disposable-passwords-settings';
$plugin_date_format = 'j F Y - H:i:s';
// Runs when plug-in is activated and creates new database field
register_activation_hook(__FILE__,'disposable_passwords_install');
function disposable_passwords_install() {
	$sql= 
		'CREATE TABLE IF NOT EXISTS disposable_passwords 
		(
			id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
			post_id INT NOT NULL,
			password VARCHAR( 50 ) NOT NULL,
			data VARCHAR( 200 ) NOT NULL,
			status INT NOT NULL DEFAULT 0,
			date_created DATETIME NOT NULL,
			consumer_ip VARCHAR(50),
			date_used DATETIME
		) DEFAULT CHARACTER SET = utf8 COLLATE utf8_general_ci;';
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta($sql);
}

// load the CSS file
add_action( 'init', 'my_enqueue_css' );
function my_enqueue_css() {
	wp_register_style('disposable-stylesheet', plugins_url('disposable-passwords/plugin-style.css'));
	wp_enqueue_style('disposable-stylesheet');
}

// Add the menu item to the WordPress menu
add_action( 'admin_menu', 'register_my_custom_menu_page' );
function register_my_custom_menu_page() {
	global $plugin_slug, $plugin_submenu_slug;
	add_menu_page( 'Disposable Passwords', 'Disposable Passwords', 'administrator', $plugin_slug, 'display_admin_screen');
	add_submenu_page(null, 'Settings', 'Settings', 'administrator', $plugin_submenu_slug, 'display_settings_screen');
}

//////////////
/* Define the custom box */
add_action( 'add_meta_boxes', 'add_disposable_passwords_toggle_box' );

/* Do something with the data entered */
add_action( 'save_post', 'disposable_passwords_save_postdata' );

/* Adds a box to the main column on the Post and Page edit screens */
function add_disposable_passwords_toggle_box() {
    add_meta_box( 
        'disposable_passwords',
        'Disposable Passwords',
        'inside_custom_disposable_passwords_box',
        'post',
        'side',
        'high'
    );
}

/* Prints the box content */
function inside_custom_disposable_passwords_box($post)
{
    // Use nonce for verification
    wp_nonce_field( 'disposable_passwords_field_nonce', 'disposable_passwords_noncename' );

    // Get saved value, if none exists, "default" is selected
    $disposable_passwords_isEnabled = get_post_meta( $post->ID, 'disposable_passwords_toggle', true);
	?>
	<input type="hidden" name="disposable_passwords_toggle" value="0" /><?php //http://stackoverflow.com/q/2520952/3709765 ?>
	<input type="checkbox" name="disposable_passwords_toggle" id="disposable_passwords_toggle" value="1" <?php if($disposable_passwords_isEnabled) echo 'checked'; ?> title="Toggle active status for disposable passwords on this post" />
	<label for="disposable_passwords_toggle">Active</label>
	<?php
}

/* When the post is saved, saves our custom data */
function disposable_passwords_save_postdata( $post_id ) 
{
      // verify if this is an auto save routine. 
      // If it is our form has not been submitted, so we dont want to do anything
      if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
          return;

      // verify this came from the our screen and with proper authorization,
      // because save_post can be triggered at other times
      if ( !wp_verify_nonce( $_POST['disposable_passwords_noncename'], 'disposable_passwords_field_nonce' ) )
          return;

      if ( isset($_POST['disposable_passwords_toggle']) && $_POST['disposable_passwords_toggle'] != "" ){
            update_post_meta( $post_id, 'disposable_passwords_toggle', $_POST['disposable_passwords_toggle'] );
      } 
}
//////////////



function display_admin_screen() {
	global $plugin_submenu_slug;
	?>
	<div id="disposable-passwords-plugin">
		<h1>Protected Posts:</h1>
		<?php
			//Protect against arbitrary paged values
			$paged = isset($_GET['paged']) ? $_GET['paged'] : 1;
			
			$all_protected_posts = new WP_Query(
							array(
								'meta_key'     => 'disposable_passwords_toggle',
								'meta_value'   => '1',
								'posts_per_page' => 20,
								'paged' => $paged,
								'order'=>'DESC',
								'orderby'=>'date'
							)
			);
			if($all_protected_posts->have_posts()) {
				?>
				<table cellpadding="3px">
					<tr><th colspan="3"></th><th id="status-count" colspan="3" class="center-align">Passwords Count</th><th></th></tr>
					<tr><th>Title</th><th>Date</th><th>Author</th><th class="center-align">Consumed</th><th class="center-align">Active</th><th class="center-align">Inactive</th><th class="center-align">Total</th></tr>
				<?php
				while($all_protected_posts->have_posts()) {
					$all_protected_posts->the_post();
					$post_id = get_the_id();
					?>
					<tr>
						<td><a href="<?php echo get_admin_url()."admin.php?page=$plugin_submenu_slug&post_id=".get_the_id(); ?>"><?php the_title(); ?></a></td>
						<td><?php echo get_the_date(); ?></td>
						<td><?php the_author(); ?></td>
						<td class="center-align"><?php echo Disposable_Passwords::get_disposable_passwords_count($post_id, Disposable_Passwords::STATUS_USED); ?></td>
						<td class="center-align"><?php echo Disposable_Passwords::get_disposable_passwords_count($post_id, Disposable_Passwords::STATUS_ACTIVE); ?></td>
						<td class="center-align"><?php echo Disposable_Passwords::get_disposable_passwords_count($post_id, Disposable_Passwords::STATUS_INACTIVE); ?></td>
						<td class="center-align"><?php echo Disposable_Passwords::get_disposable_passwords_count($post_id); ?></td>
					</tr>
					<?php
				}
				?>
				</table>
				<div class="pagination center-align"><?php
					$big = 999999999; // need an unlikely integer
					echo
					paginate_links( array(
						'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
						'format' => '?paged=%#%',
						'current' => max( 1, $paged ),
						'total' => $all_protected_posts->max_num_pages,
						'prev_text' => '« Prev',
						'next_text' => 'Next »',
					) );
				?></div><?php
			} else {
				?><div class="">There's no post utilizing Disposable Passwords<br />To get started, check the corresponding box in your posts.</div><?php
			}
		?>
		
	</div>
	<?php
}

function display_settings_screen() {
	global $plugin_date_format;
	global $plugin_slug;
	$plugin_settings_page_url = get_admin_url()."admin.php?page=$plugin_slug";
	?><div id="disposable-passwords-plugin">
		<div class="left-align"><a class="return-to-plugin-mainpage" href="<?php echo $plugin_settings_page_url; ?>">Return</a></div>
	<?php
	if(!isset($_GET['post_id'])) {
		die('please firstly select a post!');
	}
	else {
		if(isset($_POST['operation'], $_POST['data'])) {
			// processing the hidden_form operations
			$all_data = explode('|', $_POST['data']);
			switch($_POST['operation']) {
				case 'update':
					foreach($all_data as $data) {
						$single_data = explode(',', $data);
						Disposable_Passwords::update_disposable_password($single_data[0], $single_data[1], $single_data[2]);
					}
				break;
				
				case 'delete':
					foreach($all_data as $data) {
						Disposable_Passwords::delete_disposable_password($data);
					}
				break;
			}
			
			?><div class="operation-done"><?php echo count($all_data); ?> <?php echo count($all_data)!=-1 ? 'records' : 'record'; ?> <?php echo $_POST['operation']=='update' ? 'updated' : 'removed' ?></div><?php
		}
		else if(isset($_POST['create-DP'])) {
			Disposable_Passwords::insert_disposable_password($_POST['post_id'], $_POST['password'], $_POST['data'], $_POST['status']);
			?><div class="operation-done">Disposable password is created.</div><?php
		}
		else if(isset($_POST['create-random-DPs'])) {
			Disposable_Passwords::insert_random_passwords($_POST['post_id'], $_POST['status'], $_POST['count'], $_POST['length']);
			?><div class="operation-done"><?php echo $_POST['count']; ?> <?php echo $_POST['count'] != 1 ? 'Disposable passwords are' : 'Disposable password is'; ?> created.</div><?php			
		}
		$post_id = $_GET['post_id'];
		$GLOBALS['post'] = get_post($post_id);
		?>
		<h1>Disposable passwords for "<span class="the-post-title-in-setting-page"><?php the_title(); ?></span>" (id: <?php the_id(); ?>)</h1>
		<div class="a-glimpse-of-the-post-content">
		<?php
			$count = 50; // count of the words in the post content to display
			global $post;
			preg_match("#(?:\w+(?:\W+|$)){0,$count}#u", $post->post_content, $matches);
			echo "{$matches[0]}...";
		?>
		</div>
		<table cellpadding="3px">
		<tr><th>Post creation date</th><th>Author</th><th class="center-align">Consumtion</th><th class="center-align">Active</th><th class="center-align">Inactive</th><th class="center-align">Total</th></tr>
		<tr>
			<td><?php echo get_the_date(); ?></td>
			<td><?php the_author(); ?></td>
			<td class="center-align"><?php echo Disposable_Passwords::get_disposable_passwords_count($post_id, Disposable_Passwords::STATUS_USED); ?></td>
			<td class="center-align"><?php echo Disposable_Passwords::get_disposable_passwords_count($post_id, Disposable_Passwords::STATUS_ACTIVE); ?></td>
			<td class="center-align"><?php echo Disposable_Passwords::get_disposable_passwords_count($post_id, Disposable_Passwords::STATUS_INACTIVE); ?></td>
			<td class="center-align"><?php echo Disposable_Passwords::get_disposable_passwords_count($post_id); ?></td>
		</tr>
		</table>
		<h1>Consumed disposable passwords</h1>
		<?php
			$used_disposable_passwords = Disposable_Passwords::get_disposable_passwords($post_id, Disposable_Passwords::STATUS_USED);
			if(!empty($used_disposable_passwords)) {
				?>
				<table cellpadding="3px"><tr><th>#</th><th title="Id">Id</th><th>Password</th><th>Data</th><th>Consumer IP</th><th>Creation Date</th><th>Expiry Date</th></tr>
				<?php
				$index = 1;
				foreach($used_disposable_passwords as $used_disposable_password) {
					?>
					<tr>
						<td><?php echo $index; ?></td>
						<td><?php echo $used_disposable_password->id; ?></td>
						<td><?php echo $used_disposable_password->password; ?></td>
						<td><?php echo $used_disposable_password->data; ?></td>
						<td><?php echo $used_disposable_password->consumer_ip; ?></td>
						<td><?php echo $used_disposable_password->date_created; ?></td>
						<td><?php echo $used_disposable_password->date_used; ?></td>
					</tr>
					<?php
					$index++;
				}
				?>
				</table>
				<?php
			}
			else {
				?><div>There's no disposable passwords in this post.</div><?php
			}
			
			?>
			<script>
				function toggle_all() {
					var toggle_to = document.getElementById('avail_DP_select_all').checked;
					jQuery('.DP-enable-record-editing').each(function() { this.checked = toggle_to; });
				}
				function enable_record_editing(id) {
					document.getElementById(id).checked = true;
				}
				function submit_to_update_all() {
					var dataSet = new Array();
					jQuery('.DP-enable-record-editing').each(function() {
						if(this.checked) {
							var id = jQuery(this).data('dpid');
							var data = jQuery('#DP_data_'+id).val();
							var status = jQuery('#DP_status_'+id).val();
							var singleData = [id, data, status];
							dataSet.push(singleData.join());
						}
					});
					
					jQuery('#hidden_form input[name="data"]').val(dataSet.join('|'));
					jQuery('#hidden_form input[name="operation"]').val('update');
					jQuery('#hidden_form').submit();
				}
				function submit_to_delete_all() {
					var dataSet = new Array();
					jQuery('.DP-enable-record-editing').each(function() {
						if(this.checked) {
							dataSet.push(jQuery(this).data('dpid'));
						}
					});
					
					jQuery('#hidden_form input[name="data"]').val(dataSet.join('|'));
					jQuery('#hidden_form input[name="operation"]').val('delete');
					if(confirm('Are you sure to remove the selected items?'))
						jQuery('#hidden_form').submit();
				}
			</script>
			<h1>Available disposable passowrds:</h1><?php
			$avail_disposable_passwords = Disposable_Passwords::get_avail_disposable_passwords($post_id);
			if(!empty($avail_disposable_passwords)) {
				?>
				<table cellpadding="3px"><tr><th><input type="checkbox" id="avail_DP_select_all" onclick="toggle_all();" /></th><th>#</th><th title="Id">Id</th><th>Password creation date</th><th>Password</th><th>Data</th><th>Status</th></tr>
				<?php
				$index = 1;
				foreach($avail_disposable_passwords as $avail_disposable_password) {
					$DP_is_active = $avail_disposable_password->status == Disposable_Passwords::STATUS_ACTIVE;
					$DP_is_inactive = $avail_disposable_password->status == Disposable_Passwords::STATUS_INACTIVE;
					$DP_id = $avail_disposable_password->id;
					?>
					<tr class="<?php if($DP_is_inactive) echo 'inactive'; ?>">
						<td><input type="checkbox" class="DP-enable-record-editing" id="avail_DP_<?php echo $DP_id; ?>" data-dpid="<?php echo $DP_id; ?>" /></td>
						<td><?php echo $index; ?></td>
						<td><?php echo $avail_disposable_password->id;; ?></td>
						<td><?php echo $avail_disposable_password->date_created; ?></td>
						<td><?php echo $avail_disposable_password->password; ?></td>
						<td><input type="text" id="DP_data_<?php echo $DP_id; ?>" value="<?php echo $avail_disposable_password->data; ?>" onchange="enable_record_editing('avail_DP_<?php  echo $DP_id; ?>')" /></td>
						<td>
							<select name="status" id="DP_status_<?php echo $DP_id; ?>" onchange="enable_record_editing('avail_DP_<?php  echo $DP_id; ?>')" >
								<option value="<?php echo Disposable_Passwords::STATUS_ACTIVE; ?>" <?php if($DP_is_active) echo 'selected'; ?>>Active</option>
								<option value="<?php echo Disposable_Passwords::STATUS_INACTIVE; ?>" <?php if($DP_is_inactive) echo 'selected'; ?>>Inactive</option>
							</select>
						</td>
					</tr>
					<?php
					$index++;
				}
				?>
				</table>
				<form id="hidden_form" action="<?php echo $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']; ?>" method="post">
					<input type="hidden" name="data" value="">
					<input type="hidden" name="operation" value="">
				</form>
				<div class="left-align margin-top"><button style="margin-left:5px;" onclick="submit_to_update_all();">Update</button><button style="color:red;" onclick="submit_to_delete_all();">Remove</button></div>
				<?php
			}
			else {
				?><div>There's no other disposable password.</div><?php			
			}
		?>
		<div class="creation-wrap"
			><div class="creation-item">
				<h1>Create single disposable password:</h1>
				<form action="<?php echo $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']; ?>" method="post" class="creation-form">
					<table cellpadding="5px">
						<tr><td><label for="creation-form-password">Password:</label></td><td><input type="text" name="password" id="creation-form-password" required title="Please enter your disposable password." autocomplete="off"></td></tr>
						<tr><td><label for="creation-form-data">Data:</label></td><td><input type="text" name="data" id="creation-form-data"></td></tr>
						<tr>
							<td><label for="creation-form-status">Status:</label></td>
							<td>
								<select name="status" id="creation-form-status">
									<option value="<?php echo Disposable_Passwords::STATUS_ACTIVE; ?>">Active</option>
									<option value="<?php echo Disposable_Passwords::STATUS_INACTIVE; ?>">Inactive</option>
								</select>
							</td>
						</tr>
					</table>
					<input type="hidden" name="post_id" value="<?php echo $post_id; ?>" />
					<div class="margin-top"><input type="submit" name="create-DP" value="Create" /></div>
				</form>
			</div
			><div class="creation-item">
				<h1>Create a batch of disposable passwords:</h1>
				<form action="<?php echo $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']; ?>" method="post" class="creation-form">
					<table cellpadding="5px">
						<tr><td><label for="batch-creation-form-count">Count:</label></td><td><input type="number" name="count" id="batch-creation-form-count" required title="Please enter the count of disposable passwords to create."></td></tr>
						<tr><td><label for="batch-creation-form-length">Password length:</label></td><td><input type="number" name="length" id="batch-creation-form-length" required title="Please specify the length of the passwords."></td></tr>
						<tr>
							<td><label for="batch-creation-form-status">Status:</label></td>
							<td>
								<select name="status" id="batch-creation-form-status">
									<option value="<?php echo Disposable_Passwords::STATUS_ACTIVE; ?>">Active</option>
									<option value="<?php echo Disposable_Passwords::STATUS_INACTIVE; ?>">Inactive</option>
								</select>
							</td>
						</tr>
					</table>
					<input type="hidden" name="post_id" value="<?php echo $post_id; ?>" />
					<div class="margin-top"><input type="submit" name="create-random-DPs" value="Create" /></div>
				</form>
			</div
		></div>
		<?php
	}
}