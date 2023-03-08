<?php
defined( 'ABSPATH' ) or die( 'No direct access please!' );

class Disposable_Passwords {
	const STATUS_ACTIVE   = 0;
	const STATUS_INACTIVE = 1;
	const STATUS_USED     = 2;
	
	const DISPOSABLE_PASSWORD_VERIFICATION_OK  =  0;
	const ERR_POST_ANDOR_PASSWORD_DONOT_EXISIT = -1;
	const ERR_DISPOSABLE_PASSWORD_IS_INACTIVE  = -2;
	const ERR_DISPOSABLE_PASSWORD_IS_USED      = -3;
	
	
	private static $table_name = 'disposable_passwords';
	private static $possible_random_password_chars = array();
	private static $readable_msgs = array();
	
	public static function init() {
		self::$possible_random_password_chars = array_merge( range('A', 'Z'), range('a', 'z'), range(0, 9) );
		self::$readable_msgs = array(
			Disposable_Passwords::DISPOSABLE_PASSWORD_VERIFICATION_OK => 'Valid disposable password',
			Disposable_Passwords::ERR_POST_ANDOR_PASSWORD_DONOT_EXISIT => "Password doesn't exists",
			Disposable_Passwords::ERR_DISPOSABLE_PASSWORD_IS_INACTIVE => 'Password inactive',
			Disposable_Passwords::ERR_DISPOSABLE_PASSWORD_IS_USED => 'Password is already used',
		);
	}
	
	public static function plugin_install() {
		$sql= 
		"CREATE TABLE IF NOT EXISTS {self::$table_name}
		(
			id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
			post_id INT NOT NULL,
			password VARCHAR( 50 ) NOT NULL,
			data VARCHAR( 200 ),
			status INT NOT NULL DEFAULT 0,
			date_created DATETIME NOT NULL,
			Consumer_ip VARCHAR(50),
			date_used DATETIME
		);";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta($sql);
	}
	
	public static function get_disposable_passwords_count($post_id, $status = null) {
		$criteria = " WHERE post_id = $post_id";
		if(isset($status))
			$criteria .= " AND status = $status";
		global $wpdb;
		return $wpdb->get_var( 'SELECT COUNT(*) FROM '.self::$table_name." $criteria;" );
	}
	
	public static function get_disposable_passwords($post_id, $status = null) {
		$criteria = " WHERE post_id = $post_id";
		if(isset($status))
			$criteria .= " AND status = $status";
		$orderBy = $status == Disposable_Passwords::STATUS_USED ? 'date_used' : 'date_created';
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM '.self::$table_name." $criteria ORDER BY $orderBy DESC;" );
	}
	
	public static function get_avail_disposable_passwords($post_id) {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM '.self::$table_name." WHERE post_id = $post_id AND status != ".self::STATUS_USED." ORDER BY date_created DESC;" );
	}
	
	public static function verify_disposable_password($post_id, $password, &$disposable_password_id = null) {
		global $wpdb;
		$row = $wpdb->get_row('SELECT * FROM '.self::$table_name." WHERE post_id = $post_id AND password = '$password' LIMIT 1;", 'ARRAY_A');
		if(empty($row)) {
			$disposable_password_id = null;
			return Disposable_Passwords::ERR_POST_ANDOR_PASSWORD_DONOT_EXISIT;
		}
		else {
			$disposable_password_id = $row['id'];
			switch($row['status']) {
				case Disposable_Passwords::STATUS_INACTIVE:
					return Disposable_Passwords::ERR_DISPOSABLE_PASSWORD_IS_INACTIVE;
					break; // is not necessary, but keeps the good programming practices :)
				case Disposable_Passwords::STATUS_USED:
					return Disposable_Passwords::ERR_DISPOSABLE_PASSWORD_IS_USED;
					break; // is not necessary, but keeps the good programming practices :)
				default:
					return Disposable_Passwords::DISPOSABLE_PASSWORD_VERIFICATION_OK;
			}
		}
	}
	
	public static function set_disposable_password_used($disposable_password_id, $consumer_ip) {
		global $wpdb;
		$wpdb->update(self::$table_name, array('consumer_ip' => $consumer_ip, 'date_used' => date('Y-m-d H:i:s'), 'status' => Disposable_Passwords::STATUS_USED), array( 'id' => $disposable_password_id));
	}
	
	public static function update_disposable_password($disposable_password_id, $data, $status) {
		global $wpdb;
		$wpdb->update(self::$table_name, array('data' => $data, 'status' => $status), array( 'id' => $disposable_password_id));
	}
	
	public static function delete_disposable_password($disposable_password_id) {
		global $wpdb;
		$wpdb->delete(self::$table_name, array( 'id' => $disposable_password_id));
	}
	
	public static function insert_disposable_password($post_id, $password, $data = null, $status = STATUS_ACTIVE) {
		global $wpdb;
		$wpdb->insert(self::$table_name, array( 'post_id' => $post_id, 'password' => $password, 'data' => $data, 'status' => $status, 'date_created' => date('Y-m-d H:i:s')));
	}
	
	public static function insert_random_passwords($post_id, $initial_status, $count, $password_length) {
		for($i=0; $i<$count; $i++) {
			$rand_pass = '';
			for($j=0; $j<$password_length; $j++)
				$rand_pass .= self::$possible_random_password_chars[array_rand(self::$possible_random_password_chars, 1)];
			
			self::insert_disposable_password($post_id, $rand_pass, null, $initial_status);
		}
	}
	
	public static function get_readable_message($msg_code) {
		return self::$readable_msgs[$msg_code];
	}

}


function is_disposable_password_protected($post_id = null) {
	// if the $post_id is null, then use the global $post
	if(!isset($post_id)) {
		$p = $GLOBALS['post'];
		$post_id = $p->ID;
	}
	
	if(get_post_meta( $post_id, 'disposable_passwords_toggle', true))
		return true;
	return false;
}