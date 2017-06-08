<?php

/**
 * Created by NextPay.ir
 * author: Nextpay Company
 * ID: @nextpay
 * Date: 11/02/2016
 * Time: 16:22 PM
 * Website: NextPay.ir
 * Email: info@nextpay.ir
 * @copyright 2016
 * @package NextPay_Gateway
 * @version 1.0
 */
/*
  Plugin Name: دانلود به ازای پرداخت نکست پی
  Plugin URI: http://www.nextpay.ir
  Description: اين افزونه امکان فروش فايل را از طريق درگاه پرداخت نکست پی براي شما فراهم مي نمايد
  Author: Nextpay Company
  Version: 1.0
  Author URI: http://www.nextpay.ir
*/
define('PD_RECORDS_PER_PAGE', '20');
define('PD_VERSION', 1.0);
wp_enqueue_script("jquery");
register_activation_hook(__FILE__, array("nextpaypaiddownloads_class", "install"));

class nextpaypaiddownloads_class {
	var $options;
	var $error;
	var $info;
	var $currency_list;
	
	var $nextpay_currency_list = array("ريال", "تومان");
	var $buynow_buttons_list = array("html", "nextpay", "css3", "custom");
	
	function __construct() {
		if (function_exists('load_plugin_textdomain')) {
			load_plugin_textdomain('nextpaypaiddownloads', false, dirname(plugin_basename(__FILE__)).'/languages/');
		}
		$this->currency_list = array_unique(array_merge($this->nextpay_currency_list));
		sort($this->currency_list);
		$this->currency_list = array_unique(array_merge(array("تومان"), $this->currency_list));
		
		$this->options = array (
			"exists" => 1,
			"version" => PD_VERSION,
			"enable_nextpay" => "on",
			"nextpay_api_key" => "",
			"nextpay_currency" => $nextpay_currency_list[1],
			"seller_email" => "alerts@".str_replace("www.", "", $_SERVER["SERVER_NAME"]),
			"from_name" => get_bloginfo("name"),
			"from_email" => "noreply@".str_replace("www.", "", $_SERVER["SERVER_NAME"]),
			"success_email_subject" => __('جزئيات دانلود محصول', 'nextpaypaiddownloads'),
			"success_email_body" => __('کاربر گرامي {name}،', 'nextpaypaiddownloads').PHP_EOL.PHP_EOL.__('ضمن تشکر از شما بابت خريد محصول {product_title}، جهت دانلود  برروي لينک زير کليک نماييد :', 'nextpaypaiddownloads').PHP_EOL.'{download_link}'.PHP_EOL.__('توجه داشته باشيد لينک فوق تنها به مدت {download_link_lifetime} روز داراي اعتبار جهت دريافت مي باشد .', 'nextpaypaiddownloads').PHP_EOL.PHP_EOL.__('با تشکر', 'nextpaypaiddownloads').PHP_EOL.get_bloginfo("name"),
			"failed_email_subject" => __('عمليات ناموفق پرداخت', 'nextpaypaiddownloads'),
			"failed_email_body" => __('کاربر گرامي {name}،', 'nextpaypaiddownloads').PHP_EOL.PHP_EOL.__('ضمن تشکر از شما جهت انتخاب محصول {product_title} ،', 'nextpaypaiddownloads').PHP_EOL.__('پرداخت شما با وضعيت " {payment_status} " در سيستم ثبت شده است .', 'nextpaypaiddownloads').PHP_EOL.__('در صورتي که پس از بررسي پرداخت شما موفقيت آميز بوده باشد جزئيات محصول اصلاح خواهد شد.', 'nextpaypaiddownloads').PHP_EOL.PHP_EOL.__('با تشکر', 'nextpaypaiddownloads').PHP_EOL.get_bloginfo("name"),
			"buynow_type" => "html",
			"buynow_image" => "",
			"link_lifetime" => "2",
			"terms" => "",
			"getphonenumber" => "off",
			"showdownloadlink" => "off"
		);

		if (!empty($_COOKIE["nextpaypaiddownloads_error"])) {
			$this->error = stripslashes($_COOKIE["nextpaypaiddownloads_error"]);
			setcookie("nextpaypaiddownloads_error", "", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
		}
		if (!empty($_COOKIE["nextpaypaiddownloads_info"])) {
			$this->info = stripslashes($_COOKIE["nextpaypaiddownloads_info"]);
			setcookie("nextpaypaiddownloads_info", "", time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
		}

		$this->get_settings();
		//$this->handle_versions();
		
		if (is_admin()) {
			if ($this->check_settings() !== true) add_action('admin_notices', array(&$this, 'admin_warning'));
			if (!file_exists(ABSPATH.'wp-content/uploads/paid-downloads/files')) add_action('admin_notices', array(&$this, 'admin_warning_reactivate'));
			add_action('admin_menu', array(&$this, 'admin_menu'));
			add_action('init', array(&$this, 'admin_request_handler'));
			add_action('admin_head', array(&$this, 'admin_header'), 15);
			if (isset($_GET['page']) && $_GET['page'] == 'paid-downloads-transactions') {
			  //	wp_enqueue_script("thickbox");
			   //	wp_enqueue_style("thickbox");
			}
		} else {
			add_action("init", array(&$this, "front_init"));
			add_action("wp_head", array(&$this, "front_header"));
			add_shortcode('paid-downloads', array(&$this, "shortcode_handler"));
			add_shortcode('nextpaypaiddownloads', array(&$this, "shortcode_handler"));
		}
	}

	function handle_versions() {
		global $wpdb;

	}
	
	function install () {
		global $wpdb;

        $table_name = $wpdb->prefix . "pd_orders";
		//if($wpdb->get_var("show tables like '".$table_name."'") != $table_name)
		//{
			$sql = "CREATE TABLE " . $table_name . " (
				id int(11) NOT NULL auto_increment,
				file_id int(11) NOT NULL,
				payer_name varchar(255) collate utf8_unicode_ci NOT NULL,
				payer_phone varchar(255) collate utf8_unicode_ci NOT NULL,
				payer_email varchar(255) collate utf8_unicode_ci NOT NULL,
				completed int(11) NOT NULL default '0',
				UNIQUE KEY  id (id)
			);";
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
		//}

		$table_name = $wpdb->prefix . "pd_files";
		$sql = "CREATE TABLE " . $table_name . " (
			id int(11) NOT NULL auto_increment,
			title varchar(255) collate utf8_unicode_ci NOT NULL,
			filename varchar(255) collate utf8_unicode_ci NOT NULL,
			uploaded int(11) NOT NULL default '1',
			filename_original varchar(255) collate utf8_unicode_ci NOT NULL,
			price float NOT NULL,
			currency varchar(7) collate utf8_unicode_ci NOT NULL,
			available_copies int(11) NOT NULL default '0',
			license_url varchar(255) NOT NULL default '',
			registered int(11) NOT NULL,
			deleted int(11) NOT NULL default '0',
			UNIQUE KEY  id (id)
		);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		$table_name = $wpdb->prefix . "pd_downloadlinks";
		$sql = "CREATE TABLE " . $table_name . " (
			id int(11) NOT NULL auto_increment,
			file_id int(11) NOT NULL,
			download_key varchar(255) collate utf8_unicode_ci NOT NULL,
			owner varchar(63) collate utf8_unicode_ci NOT NULL,
			source varchar(15) collate utf8_unicode_ci NOT NULL,
			created int(11) NOT NULL,
			deleted int(11) NOT NULL default '0',
			UNIQUE KEY  id (id)
		);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		$table_name = $wpdb->prefix . "pd_transactions";
		$sql = "CREATE TABLE " . $table_name . " (
			id int(11) NOT NULL auto_increment,
			file_id int(11) NOT NULL,
			payer_name varchar(255) collate utf8_unicode_ci NOT NULL,
			payer_phone varchar(255) collate utf8_unicode_ci NOT NULL,
			payer_email varchar(255) collate utf8_unicode_ci NOT NULL,
			gross float NOT NULL,
			currency varchar(15) collate utf8_unicode_ci NOT NULL,
			payment_status varchar(31) collate utf8_unicode_ci NOT NULL,
			transaction_type varchar(31) collate utf8_unicode_ci NOT NULL,
			details text collate utf8_unicode_ci NOT NULL,
			created int(11) NOT NULL,
			deleted int(11) NOT NULL default '0',
			UNIQUE KEY  id (id)
		);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		if (!file_exists(ABSPATH.'wp-content/uploads/paid-downloads')) {
			wp_mkdir_p(ABSPATH.'wp-content/uploads/paid-downloads');
			if (!file_exists(ABSPATH.'wp-content/uploads/paid-downloads/index.html')) {
				file_put_contents(ABSPATH.'wp-content/uploads/paid-downloads/index.html', 'Silence is the gold!');
			}
			if (!file_exists(ABSPATH.'wp-content/uploads/paid-downloads/files')) {
				wp_mkdir_p(ABSPATH.'wp-content/uploads/paid-downloads/files');
				if (!file_exists(ABSPATH.'wp-content/uploads/paid-downloads/files/.htaccess')) {
					file_put_contents(ABSPATH.'wp-content/uploads/paid-downloads/files/.htaccess', 'deny from all');
				}
			}
		}
	}

	function get_settings() {
		$exists = get_option('nextpaypaiddownloads_version');
		if ($exists) {
			foreach ($this->options as $key => $value) {
				$this->options[$key] = get_option('nextpaypaiddownloads_'.$key);
			}
		}
	}

	function update_settings() {
		foreach ($this->options as $key => $value) {
			update_option('nextpaypaiddownloads_'.$key, $value);
		}
	}

	function populate_settings() {
		foreach ($this->options as $key => $value) {
			if (isset($_POST['nextpaypaiddownloads_'.$key])) {
				$this->options[$key] = stripslashes($_POST['nextpaypaiddownloads_'.$key]);
			}
		}
	}

	function check_settings() {
		$errors = array();
		if (strlen($this->options['nextpay_api_key']) < 3) $errors[] = __('تعيين کلید مجوزدهی الزامي مي باشد ', 'nextpaypaiddownloads');
		if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $this->options['seller_email']) || strlen($this->options['seller_email']) == 0) $errors[] = __('ايميل وارد شده جهت دريافت اطلاع رساني ها صحيح نمي باشد', 'nextpaypaiddownloads');
		if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $this->options['from_email']) || strlen($this->options['from_email']) == 0) $errors[] = __('آدرس ايميل وارد شده براي فروشگاه صحيح نمي باشد', 'nextpaypaiddownloads');
		if (strlen($this->options['from_name']) < 3) $errors[] = __('نام فروشگاه کوتاه مي باشد', 'nextpaypaiddownloads');
		if (strlen($this->options['success_email_subject']) < 3) $errors[] = __('عنوان ايميل خريد موفقيت آميز مي بايست حداقل داراي 3 حرف باشد', 'nextpaypaiddownloads');
		else if (strlen($this->options['success_email_subject']) > 64) $errors[] = __('عنوان ايميل خريد موفقيت آميز مي بايست حداکثر داراي 64 حرف باشد', 'nextpaypaiddownloads');
		if (strlen($this->options['success_email_body']) < 3) $errors[] = __('متن ايميل خريد موفقيت آميز مي بايست حداقل داراي 3 حرف باشد', 'nextpaypaiddownloads');
		if (strlen($this->options['failed_email_subject']) < 3) $errors[] = __('عنوان ايميل خرید ناموفق مي بايست حداقل داراي 3 حرف باشد', 'nextpaypaiddownloads');
		else if (strlen($this->options['failed_email_subject']) > 64) $errors[] = __('عنوان ايميل خريد ناموفق مي بايست حداکثر داراي 64 حرف باشد', 'nextpaypaiddownloads');
		if (strlen($this->options['failed_email_body']) < 3) $errors[] = __('متن ايميل خريد ناموفق مي بايست حداقل داراي 3 حرف باشد', 'nextpaypaiddownloads');
		if (intval($this->options['link_lifetime']) != $this->options['link_lifetime'] || intval($this->options['link_lifetime']) < 1 || intval($this->options['link_lifetime']) > 365) $errors[] = __('مدت اعتبار لينک را در بازه  [1...365] روز تعيين نماييد ', 'nextpaypaiddownloads');
		if (empty($errors)) return true;
		return $errors;
	}

	function admin_menu() {
		if (get_bloginfo('version') >= 3.0) {
			define("PAID_DOWNLOADS_PERMISSION", "add_users");
		}
		else{
			define("PAID_DOWNLOADS_PERMISSION", "edit_themes");
		}	
		add_menu_page(
			"پرداخت نکست پی"
			, "پرداخت نکست پی"
			, PAID_DOWNLOADS_PERMISSION
			, "paid-downloads"
			, array(&$this, 'admin_settings')
		);
		add_submenu_page(
			"paid-downloads"
			, __('تنظيمات', 'nextpaypaiddownloads')
			, __('تنظيمات', 'nextpaypaiddownloads')
			, PAID_DOWNLOADS_PERMISSION
			, "paid-downloads"
			, array(&$this, 'admin_settings')
		);
		add_submenu_page(
			"paid-downloads"
			, __('فايلها', 'nextpaypaiddownloads')
			, __('فايلها', 'nextpaypaiddownloads')
			, PAID_DOWNLOADS_PERMISSION
			, "paid-downloads-files"
			, array(&$this, 'admin_files')
		);
		add_submenu_page(
			"paid-downloads"
			, __('اضافه کردن فايل', 'nextpaypaiddownloads')
			, __('اضافه کردن فايل', 'nextpaypaiddownloads')
			, PAID_DOWNLOADS_PERMISSION
			, "paid-downloads-add"
			, array(&$this, 'admin_add_file')
		);
		add_submenu_page(
			"paid-downloads"
			, __('لينک هاي دريافت', 'nextpaypaiddownloads')
			, __('لينک هاي دريافت', 'nextpaypaiddownloads')
			, PAID_DOWNLOADS_PERMISSION
			, "paid-downloads-links"
			, array(&$this, 'admin_links')
		);
		add_submenu_page(
			"paid-downloads"
			, __('اضافه کردن لينک', 'nextpaypaiddownloads')
			, __('اضافه کردن لينک', 'nextpaypaiddownloads')
			, PAID_DOWNLOADS_PERMISSION
			, "paid-downloads-add-link"
			, array(&$this, 'admin_add_link')
		);
		add_submenu_page(
			"paid-downloads"
			, __('پرداخت ها', 'nextpaypaiddownloads')
			, __('پرداخت ها', 'nextpaypaiddownloads')
			, PAID_DOWNLOADS_PERMISSION
			, "paid-downloads-transactions"
			, array(&$this, 'admin_transactions')
		);
	}

	function admin_settings() {
		global $wpdb;
		$message = "";
		$errors = array();
		if (!empty($this->error)) $message = "<div class='error'><p>".$this->error."</p></div>";
		else {
			$errors = $this->check_settings();
			if (is_array($errors)) echo "<div class='error'><p>".__('خطا هاي موجود در فرم  :', 'nextpaypaiddownloads')."<br />- ".implode("<br />- ", $errors)."</p></div>";
		}
		if ($_GET["updated"] == "true") {
			$message = '<div class="updated"><p>'.__('تنظیمات افزونه با  <strong>موفقیت</strong> بروزرسانی گردید .', 'nextpaypaiddownloads').'</p></div>';
		}
		if (!in_array($this->options['buynow_type'], $this->buynow_buttons_list)) $this->options['buynow_type'] = $this->buynow_buttons_list[0];
		if ($this->options['buynow_type'] == "custom")
		{
			if (empty($this->options['buynow_image'])) $this->options['buynow_type'] = $this->buynow_buttons_list[0];
		}
		print ('
		<div class="wrap admin_nextpaypaiddownloads_wrap">
			<div id="icon-options-general" class="icon32"><br /></div><h2>'.__('پرداخت نکست پی - تنظيمات', 'nextpaypaiddownloads').'</h2><br />
			'.$message);

		print ('
			<form enctype="multipart/form-data" method="post" style="margin: 0px" action="'.get_bloginfo('wpurl').'/wp-admin/admin.php">

			<div class="postbox-container" style="width: 100%;">
				<div class="metabox-holder">
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<!--<div class="handlediv" title="Click to toggle"><br></div>-->
							<h3 class="hndle" style="cursor: default;"><span>'.__('تنظیمات اصلی', 'nextpaypaiddownloads').'</span></h3>
							<div class="inside">
								<table class="nextpaypaiddownloads_useroptions">
									<tr>
										<th>'.__('ايميل اطلاع رساني', 'nextpaypaiddownloads').':</th>
										<td><input type="text" id="nextpaypaiddownloads_seller_email" name="nextpaypaiddownloads_seller_email" value="'.htmlspecialchars($this->options['seller_email'], ENT_QUOTES).'" class="widefat"><br /><em>'.__('لطفا يک آدرس ايميل جهت دریافت کليه رويداد ها خريد/پرداخت وارد نماييد.', 'nextpaypaiddownloads').'</em></td>
									</tr>
									<tr>
										<th>'.__('نام فروشگاه', 'nextpaypaiddownloads').':</th>
										<td><input type="text" id="nextpaypaiddownloads_from_name" name="nextpaypaiddownloads_from_name" value="'.htmlspecialchars($this->options['from_name'], ENT_QUOTES).'" class="widefat"><br /><em>'.__('لطفا نام مورد نظر خود جهت پيام هاي ارسالي به خريدار را در اين قسمت تعيين نماييد .', 'nextpaypaiddownloads').'</em></td>
									</tr>
									<tr>
										<th>'.__('ايميل فروشگاه', 'nextpaypaiddownloads').':</th>
										<td><input type="text" id="nextpaypaiddownloads_from_email" name="nextpaypaiddownloads_from_email" value="'.htmlspecialchars($this->options['from_email'], ENT_QUOTES).'" class="widefat"><br /><em>'.__('تمامي ايميل هاي ارسالي براي خريدار از طرف اين ايميل ارسال خواهد شد.', 'nextpaypaiddownloads').'</em></td>
									</tr>
									<tr>
										<th>'.__('عنوان ايميل خريد موفق', 'nextpaypaiddownloads').':</th>
										<td><input type="text" id="nextpaypaiddownloads_success_email_subject" name="nextpaypaiddownloads_success_email_subject" value="'.htmlspecialchars($this->options['success_email_subject'], ENT_QUOTES).'" class="widefat"><br /><em>'.__('پس از پرداخت موفقيت آميز پرداخت کننده يک ايميل با اين عنوان دريافت مي نمايد.', 'nextpaypaiddownloads').'</em></td>
									</tr>
									<tr>
										<th>'.__('متن ايميل خريد موفق', 'nextpaypaiddownloads').':</th>
										<td><textarea id="nextpaypaiddownloads_success_email_body" name="nextpaypaiddownloads_success_email_body" class="widefat" style="height: 120px;">'.htmlspecialchars($this->options['success_email_body'], ENT_QUOTES).'</textarea><br /><em>'.__('پس از خريد موفقيت آميز متن فوق براي کاربر ارسال مي گردد ، جهت جايگزيني در هنگام ارسال از فيلد هاي زير استفاده نماييد: {name}, {payer_email}, {product_title}, {product_price}, {product_currency}, {download_link}, {download_link_lifetime}, {license_info}.', 'nextpaypaiddownloads').'</em></td>
									</tr>

									<tr>
										<th>'.__('عنوان ايميل خريد ناموفق', 'nextpaypaiddownloads').':</th>
										<td><input type="text" id="nextpaypaiddownloads_failed_email_subject" name="nextpaypaiddownloads_failed_email_subject" value="'.htmlspecialchars($this->options['failed_email_subject'], ENT_QUOTES).'" class="widefat"><br /><em>'.__('پس از پرداخت ناموفق پرداخت کننده يک ايميل با اين عنوان دريافت مي نمايد.', 'nextpaypaiddownloads').'</em></td>
									</tr>
									<tr>
										<th>'.__('متن ايميل خريد ناموفق', 'nextpaypaiddownloads').':</th>
										<td><textarea id="nextpaypaiddownloads_failed_email_body" name="nextpaypaiddownloads_failed_email_body" class="widefat" style="height: 120px;">'.htmlspecialchars($this->options['failed_email_body'], ENT_QUOTES).'</textarea><br /><em>'.__('پس از خريد ناموفق متن فوق براي کاربر ارسال مي گردد ، جهت جايگزيني در هنگام ارسال از فيلد هاي زير استفاده نماييد : {name}, {payer_email}, {product_title}, {product_price}, {product_currency}, {payment_status}.', 'nextpaypaiddownloads').'</em></td>
									</tr>
									<tr>
										<th>'.__('مدت اعتبار لينک دانلود', 'nextpaypaiddownloads').':</th>
										<td><input type="text" id="nextpaypaiddownloads_link_lifetime" name="nextpaypaiddownloads_link_lifetime" value="'.htmlspecialchars($this->options['link_lifetime'], ENT_QUOTES).'" style="width: 60px; text-align: right;"> روز<br /><em>'.__('لطفا مدت زمان اعتبار لينک دانلود را تعيين نماييد.', 'nextpaypaiddownloads').'</em></td>
									</tr>
									<tr>
										<th>'.__('نوع کليد خريد', 'nextpaypaiddownloads').':</th>
										<td>
											<table style="border: 0px; padding: 0px;">
											<tr><td style="padding-top: 8px; width: 20px;"><input type="radio"  name="nextpaypaiddownloads_buynow_type" value="html"'.($this->options['buynow_type'] == "html" ? ' checked="checked"' : '').'></td><td>'.__('کليد استاندارد HTML', 'nextpaypaiddownloads').'<br /><button style="font-family:tahoma; padding:2px; width:100px" onclick="return false;">'.__('خريد', 'nextpaypaiddownloads').'</button></td></tr>
											<tr><td style="padding-top: 8px;"><input type="radio" name="nextpaypaiddownloads_buynow_type" value="nextpay"'.($this->options['buynow_type'] == "nextpay" ? ' checked="checked"' : '').'></td><td>'.__('کليد پيش فرض نکست پی', 'nextpaypaiddownloads').'<br /><img src="'.plugins_url('/images/btn_buynow_LG.gif', __FILE__).'" border="0"></td></tr>
											<tr><td style="padding-top: 8px;"><input type="radio" name="nextpaypaiddownloads_buynow_type" value="css3"'.($this->options['buynow_type'] == "css3" ? ' checked="checked"' : '').'></td><td>'.__('کليد با استفاده از CSS3', 'nextpaypaiddownloads').'<br />
											<a href="#" class="nextpaypaiddownloads-btn" onclick="return false;">
  												<span class="nextpaypaiddownloads-btn-icon-right"><span></span></span>
												<span class="nextpaypaiddownloads-btn-slide-text">1000 تومان</span>
                                                <span class="nextpaypaiddownloads-btn-text">'.__('خريد', 'nextpaypaiddownloads').'</span>
											</a>
											</td></tr>
											<tr><td style="padding-top: 8px;"><input type="radio" name="nextpaypaiddownloads_buynow_type" value="custom"'.($this->options['buynow_type'] == "custom" ? ' checked="checked"' : '').'></td><td>'.__('کليد سفارشي', 'nextpaypaiddownloads').(!empty($this->options['buynow_image']) ? '<br /><img src="'.get_bloginfo("wpurl").'/wp-content/uploads/paid-downloads/'.rawurlencode($this->options['buynow_image']).'" border="0">' : '').'<br /><input type="file" id="nextpaypaiddownloads_buynow_image" name="nextpaypaiddownloads_buynow_image" class="widefat"><br /><em>'.__('مجاز به انتخاب تصوير با ابعاد : 600px در  600px و پسوند : JPG, GIF, PNG.', 'nextpaypaiddownloads').'</em></td></tr>
											</table>
										</td>
									</tr>
                                    <tr>
										<th>'.__('گزینه ها', 'nextpaypaiddownloads').$this->options['getphonenumber'] .':</th>
										<td>
                                            <input type="checkbox" '.($this->options['getphonenumber'] == "on" ? ' checked="checked"' : '').' name="nextpaypaiddownloads_getphonenumber"  id="nextpaypaiddownloads_getphonenumber" /><label for="nextpaypaiddownloads_getphonenumber">دریافت شماره تلفن همراه کاربر</label>
                                            <br/>
                                            <input type="checkbox" '.($this->options['showdownloadlink'] == "on" ? ' checked="checked"' : '').' name="nextpaypaiddownloads_showdownloadlink"  id="nextpaypaiddownloads_showdownloadlink" /><label for="nextpaypaiddownloads_showdownloadlink">نمایش لینک دانلود پایان خرید</label>
                                        </td>
									</tr>
									<tr>
										<th>'.__('قوانين و مقررات', 'nextpaypaiddownloads').':</th>
										<td><textarea id="nextpaypaiddownloads_terms" name="nextpaypaiddownloads_terms" class="widefat" style="height: 120px;">'.htmlspecialchars($this->options['terms'], ENT_QUOTES).'</textarea><br /><em>'.__('در صورتي که نيازمند پذيرش قوانين و مقررات جهت خريد و دانلود فايل هاي سايت خود هستيد از اين قسمت استفاده نماييد ، خالي گذاشتن فيلد فوق به معني نداشتن قوانين و مقررات خواهد بود.', 'nextpaypaiddownloads').'</em></td>
									</tr>
								</table>
								<div class="alignright">
								<input type="hidden" name="ak_action" value="nextpaypaiddownloads_update_settings" />
								<input type="hidden" name="nextpaypaiddownloads_exists" value="1" />
								<input type="submit" class="paiddownoads_button button-primary" name="submit" value="'.__('ذخيره تنظيمات', 'nextpaypaiddownloads').' »">
								</div>
								<br class="clear">
							</div>
						</div>

						<div class="postbox">
							<!--<div class="handlediv" title="Click to toggle"><br></div>-->
							<h3 class="hndle" style="cursor: default;"><span>'.__('تنظيمات درگاه پرداخت نکست پی', 'nextpaypaiddownloads').'</span></h3>
							<div class="inside">
								<table class="nextpaypaiddownloads_useroptions">
									<tr><th colspan="2">'.(!in_array('curl', get_loaded_extensions()) ? __('جهت استفاده از درپاه نکست پی CURL برروي سرور هاست خود فعال نماييد !', 'nextpaypaiddownloads') : __('توجه ! جهت دريافت اطلاعات درگاه مي بايست از طريق عضويت و دريافت درگاه در سايت <a target="_blank" href="https://www.nextpay.ir">نکست پی</a> اقدام نماييد.', 'nextpaypaiddownloads')).'</th></tr>

									<tr>
										<th>'.__('کلید مجوزدهی - Api_Key', 'nextpaypaiddownloads').':</th>
										<td><input type="text" id="nextpaypaiddownloads_nextpay_api_key" name="nextpaypaiddownloads_nextpay_api_key" value="'.htmlspecialchars($this->options['nextpay_api_key'], ENT_QUOTES).'" class="widefat"'.(!in_array('curl', get_loaded_extensions()) ? ' disabled="disabled"' : '').'><br /><em>'.__('لطفا کلید مجوزدهی دريافتي از نکست پی را در اين قسمت وارد نماييد.', 'nextpaypaiddownloads').'</em></td>
									</tr>




                                    <tr>
										<th>'.__('واحد پول', 'nextpaypaiddownloads').':</th>
										<td>
											<select name="nextpaypaiddownloads_nextpay_currency" id="nextpaypaiddownloads_nextpay_currency"'.(!in_array('curl', get_loaded_extensions()) ? ' disabled="disabled"' : '').'>');
		for ($i=0; $i<sizeof($this->nextpay_currency_list); $i++)
		{
			echo '
												<option value="'.$this->nextpay_currency_list[$i].'"'.($this->nextpay_currency_list[$i] == $this->options['nextpay_currency'] ? ' selected="selected"' : '').'>'.$this->nextpay_currency_list[$i].'</option>';
		}
		print('
											</select>
											<br /><em>'.__('نوع واحد پول مورد نظر خود را تعيين نماييد.', 'nextpaypaiddownloads').'</em>
										</td>
									</tr>
								</table>
								<div class="alignright">
									<input type="submit" class="paiddownoads_button button-primary" name="submit" value="'.__('ذخيره تنظيمات', 'nextpaypaiddownloads').' »">
								</div>
								<br class="clear">
							</div>
						</div>
					</div>
				</div>
			</div>
			</form>
		</div>');
	}

	function admin_files() {
		global $wpdb;

		if (isset($_GET["s"])) $search_query = trim(stripslashes($_GET["s"]));
		else $search_query = "";
		
		$tmp = $wpdb->get_row("SELECT COUNT(*) AS total FROM ".$wpdb->prefix."pd_files WHERE deleted = '0'  ".((strlen($search_query) > 0) ? "and filename_original LIKE '%".addslashes($search_query)."%' OR deleted = '0' and  title LIKE '%".addslashes($search_query)."%'" : ""), ARRAY_A);
		$total = $tmp["total"];
		$totalpages = ceil($total/PD_RECORDS_PER_PAGE);
		if ($totalpages == 0) $totalpages = 1;
		if (isset($_GET["p"])) $page = intval($_GET["p"]);
		else $page = 1;
		if ($page < 1 || $page > $totalpages) $page = 1;
		$switcher = $this->page_switcher(get_bloginfo("wpurl")."/wp-admin/admin.php?page=paid-downloads-files".((strlen($search_query) > 0) ? "&s=".rawurlencode($search_query) : ""), $page, $totalpages);

		$sql = "SELECT * FROM ".$wpdb->prefix."pd_files WHERE deleted = '0' ".((strlen($search_query) > 0) ? "and filename_original LIKE '%".addslashes($search_query)."%' OR deleted = '0' and  title LIKE '%".addslashes($search_query)."%'" : "")." ORDER BY registered DESC LIMIT ".(($page-1)*PD_RECORDS_PER_PAGE).", ".PD_RECORDS_PER_PAGE;
		$rows = $wpdb->get_results($sql, ARRAY_A);
		if (!empty($this->error)) $message = "<div class='error'><p>".$this->error."</p></div>";
		if (!empty($this->info)) $message = "<div class='updated'><p>".$this->info."</p></div>";

		print ('
			<div class="wrap admin_nextpaypaiddownloads_wrap">
				<div id="icon-upload" class="icon32"><br /></div><h2>'.__('پرداخت نکست پی - فايل ها', 'nextpaypaiddownloads').'</h2><br />
				'.$message.'
				<form action="'.get_bloginfo("wpurl").'/wp-admin/admin.php" method="get" style="margin-bottom: 10px;">
				<input type="hidden" name="page" value="paid-downloads-files" />
				'.__('جستجو :', 'nextpaypaiddownloads').' <input type="text" name="s" value="'.htmlspecialchars($search_query, ENT_QUOTES).'">
				<input type="submit" class="button-secondary action" value="'.__('جستجو', 'nextpaypaiddownloads').'" />
				'.((strlen($search_query) > 0) ? '<input type="button" class="button-secondary action" value="'.__('برگشت به حالت ليست', 'nextpaypaiddownloads').'" onclick="window.location.href=\''.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-files\';" />' : '').'
				</form>
				<div class="nextpaypaiddownloads_buttons"><a class="button" href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-add">'.__('بارگزاري فايل جديد', 'nextpaypaiddownloads').'</a></div>
				<div class="nextpaypaiddownloads_pageswitcher">'.$switcher.'</div>
				<table class="nextpaypaiddownloads_files">
				<tr>
					<th>'.__('فايل', 'nextpaypaiddownloads').'</th>
					<th style="width: 190px;">'.__('کد فايل', 'nextpaypaiddownloads').'</th>
					<th style="width: 90px;">'.__('مبلغ', 'nextpaypaiddownloads').'</th>
					<th style="width: 90px;">'.__('تعداد فروش', 'nextpaypaiddownloads').'</th>
					<th style="width: 130px;">'.__('عمليات', 'nextpaypaiddownloads').'</th>
				</tr>
		');
		if (sizeof($rows) > 0)
		{
			foreach ($rows as $row)
			{
				$sql = "SELECT COUNT(id) AS sales FROM ".$wpdb->prefix."pd_transactions WHERE file_id = '".$row["id"]."' AND (payment_status = '100')";
				$sales = $wpdb->get_row($sql, ARRAY_A);
				print ('
				<tr>
					<td><strong>'.$row['title'].'</strong><br /><em style="font-size: 12px; line-height: 14px;">'.htmlspecialchars($row['filename_original'], ENT_QUOTES).'</em></td>
					<td dir="ltr" style="direction:ltr; text-align:center">[nextpaypaiddownloads id="'.$row['id'].'"]</td>
					<td style="text-align: right;">'.number_format($row['price'],0).' '.$row['currency'].'</td>
					<td style="text-align: right;">'.intval($sales["sales"]).' / '.(($row['available_copies'] == 0) ? '&infin;' : $row['available_copies']).'</td>
					<td style="text-align: center;">
						<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-add&id='.$row['id'].'" title="'.__('ويرايش جزئيات', 'nextpaypaiddownloads').'"><img src="'.plugins_url('/images/edit.png', __FILE__).'" alt="'.__('ويرايش جزئيات', 'nextpaypaiddownloads').'" border="0"></a>
						<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-add-link&fid='.$row['id'].'" title="'.__('ايجاد لينک دانلود', 'nextpaypaiddownloads').'"><img src="'.plugins_url('/images/downloadlink.png', __FILE__).'" alt="'.__('ايجاد لينک دانلود', 'nextpaypaiddownloads').'" border="0"></a>
						<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-links&fid='.$row['id'].'" title="'.__('لينک هاي دانلود ايجاد شده', 'nextpaypaiddownloads').'"><img src="'.plugins_url('/images/linkhistory.png', __FILE__).'" alt="'.__('لينک هاي دانلود ايجاد شده', 'nextpaypaiddownloads').'" border="0"></a>
						<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-transactions&fid='.$row['id'].'" title="'.__('تراکنش هاي پرداختي', 'nextpaypaiddownloads').'"><img src="'.plugins_url('/images/transactions.png', __FILE__).'" alt="'.__('تراکنش هاي پرداختي', 'nextpaypaiddownloads').'" border="0"></a>
						<a href="'.get_bloginfo("wpurl").'/?nextpaypaiddownloads_id='.$row['id'].'" title="'.__('دانلود فايل', 'nextpaypaiddownloads').'"><img src="'.plugins_url('/images/download01.png', __FILE__).'" alt="'.__('دانلود فايل', 'nextpaypaiddownloads').'" border="0"></a>
						<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?ak_action=nextpaypaiddownloads_delete&id='.$row['id'].'" title="'.__('حذف فايل', 'nextpaypaiddownloads').'" onclick="return nextpaypaiddownloads_submitOperation();"><img src="'.plugins_url('/images/delete.png', __FILE__).'" alt="'.__('حذف فايل', 'nextpaypaiddownloads').'" border="0"></a>
					</td>
				</tr>
				');
			}
		}
		else
		{
			print ('
				<tr><td colspan="5" style="padding: 20px; text-align: center;">'.((strlen($search_query) > 0) ? __('هيچ نتيجه اي يافت نشد', 'nextpaypaiddownloads').' "<strong>'.htmlspecialchars($search_query, ENT_QUOTES).'</strong>"' : __('هيچ فايلي يافت نشد.', 'nextpaypaiddownloads')).'</td></tr>
			');
		}
		print ('
				</table>
				<div class="nextpaypaiddownloads_buttons"><a class="button" href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-add">'.__('بارگزاري فايل جديد', 'nextpaypaiddownloads').'</a></div>
				<div class="nextpaypaiddownloads_pageswitcher">'.$switcher.'</div>
				<div class="nextpaypaiddownloads_legend">
				<strong>'.__('راهنما :', 'nextpaypaiddownloads').'</strong>
					<p><img src="'.plugins_url('/images/edit.png', __FILE__).'" alt="'.__('ويرايش جزئيات', 'nextpaypaiddownloads').'" border="0"> '.__('ويرايش جزئيات', 'nextpaypaiddownloads').'</p>
					<p><img src="'.plugins_url('/images/downloadlink.png', __FILE__).'" alt="'.__('ايجاد لينک دانلود', 'nextpaypaiddownloads').'" border="0"> '.__('ايجاد لينک دانلود', 'nextpaypaiddownloads').'</p>
					<p><img src="'.plugins_url('/images/linkhistory.png', __FILE__).'" alt="'.__('لينک هاي دانلود ايجاد شده', 'nextpaypaiddownloads').'" border="0"> '.__('لينک هاي دانلود ايجاد شده', 'nextpaypaiddownloads').'</p>
					<p><img src="'.plugins_url('/images/transactions.png', __FILE__).'" alt="'.__('تراکنش هاي پرداختي', 'nextpaypaiddownloads').'" border="0"> '.__('تراکنش هاي پرداختي', 'nextpaypaiddownloads').'</p>
					<p><img src="'.plugins_url('/images/download01.png', __FILE__).'" alt="'.__('دانلود فايل', 'nextpaypaiddownloads').'" border="0"> '.__('دانلود فايل', 'nextpaypaiddownloads').'</p>
					<p><img src="'.plugins_url('/images/delete.png', __FILE__).'" alt="'.__('حذف فايل', 'nextpaypaiddownloads').'" border="0"> '.__('حذف فايل', 'nextpaypaiddownloads').'</p>
				</div>
			</div>
		');
	}

	function admin_add_file() {
		global $wpdb;

		unset($id);
		$status = "";
		if (isset($_GET["id"]) && !empty($_GET["id"])) {
			$id = intval($_GET["id"]);
			$file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."pd_files WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
			if (intval($file_details["id"]) == 0) unset($id);
		}
		$errors = true;
		if (!empty($this->error)) $message = "<div class='error'><p>".$this->error."</p></div>";
		else if (!empty($this->info)) $message = "<div class='updated'><p>".$this->info."</p></div>";

		$file = array();
		if (file_exists(ABSPATH.'wp-content/uploads/paid-downloads/files') && is_dir(ABSPATH.'wp-content/uploads/paid-downloads/files')) {
			$dircontent = scandir(ABSPATH.'wp-content/uploads/paid-downloads/files');
			for ($i=0; $i<sizeof($dircontent); $i++) {
				if ($dircontent[$i] != "." && $dircontent[$i] != ".." && $dircontent[$i] != "index.html" && $dircontent[$i] != ".htaccess") {
					if (is_file(ABSPATH.'wp-content/uploads/paid-downloads/files/'.$dircontent[$i])) {
						$files[] = $dircontent[$i];
					}
				}
			}
		}
		print ('
		<div class="wrap admin_nextpaypaiddownloads_wrap">
			<div id="icon-options-general" class="icon32"><br /></div><h2>'.(!empty($id) ? __('پرداخت نکست پی - ويرايش فايل', 'nextpaypaiddownloads') : __('پرداخت نکست پی - بارگزاري فايل جديد', 'nextpaypaiddownloads')).'</h2>
			'.$message.'
			<form enctype="multipart/form-data" method="post" style="margin: 0px" action="'.get_bloginfo('wpurl').'/wp-admin/admin.php">
			<div class="postbox-container" style="width: 100%;">
				<div class="metabox-holder">
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<!--<div class="handlediv" title="Click to toggle"><br></div>-->
							<h3 class="hndle" style="cursor: default;"><span>'.(!empty($id) ? __('رايش فايل', 'nextpaypaiddownloads') : __('بارگزاري فايل جديد', 'nextpaypaiddownloads')).'</span></h3>
							<div class="inside">
								<table class="nextpaypaiddownloads_useroptions">
									<tr>
										<th>'.__('عنوان', 'nextpaypaiddownloads').':</th>
										<td><input type="text" name="nextpaypaiddownloads_title" id="nextpaypaiddownloads_title" value="'.htmlspecialchars($file_details['title'], ENT_QUOTES).'" class="widefat"><br /><em>'.__('لطفا عنوان فايل خود را مشخص نماييد ، در صورت خالي گذاشتن اين فيلد نام فايل اصلي به انتخاب خواهد شد.', 'nextpaypaiddownloads').'</em></td>
									</tr>
									<tr>
										<th>'.__('فايل', 'nextpaypaiddownloads').':</th>
										<td>

                                        <div class="container-tab">

                                        	<ul class="tabs">
                                        		<li class="tab-link current" data-tab="tab-1" onclick="changeTab(this)">بارگزاری فایل</li>
                                        		<li class="tab-link" data-tab="tab-2" onclick="changeTab(this)">انتخاب از فایل ها موجود</li>
                                        		<li class="tab-link" data-tab="tab-3" onclick="changeTab(this)">لینک به فایل</li>
                                        	</ul>

                                        	<div id="tab-1" class="tab-content current">
    											<input type="file" name="nextpaypaiddownloads_file" id="nextpaypaiddownloads_file" class="widefat"><br /><em>'.__('انتخاب فايل براي بارگزاري', 'nextpaypaiddownloads').'</em>
                                        	</div>
                                        	<div id="tab-2" class="tab-content">
                                                <select name="nextpaypaiddownloads_fileselector" id="nextpaypaiddownloads_fileselector">
												<option value="">-- '.__('انتخاب از فايل هاي موجود', 'nextpaypaiddownloads').' --</option>');
                                        		for ($i=0; $i<sizeof($files); $i++)
                                        		{
                                        			echo '<option value="'.htmlspecialchars($files[$i], ENT_QUOTES).'"'.($files[$i] == $file_details['filename'] ? ' selected="selected"' : '').'>'.htmlspecialchars($files[$i], ENT_QUOTES).'</option>';
                                        		}
                                        		print('	</select><br /><em>'.__('شما مي توانيد يک فايل را از مسير <strong>/wp-content/uploads/paid-downloads/files/</strong> انتخاب و يا نسبت به بارگزاري فايل اقدام نماييد.', 'nextpaypaiddownloads').'</em><br /><br />
                                        	</div>
                                        	<div id="tab-3" class="tab-content">
                                                 لینک دانلود فایل : <br><br>
										         <input type="text" name="nextpaypaiddownloads_filelink" id="nextpaypaiddownloads_filelink" value="'.($file_details['uploaded'] == 2 ? $file_details['filename'] : "").'" class="widefat enput">
                                                 <br /><em>مسیر فایل را به صورت کامل جهت هدایت کاربر برای دانلود وارد نمایید ،&nbsp;مثال : http://www.mydownloadhost.com/files/filename.zip</em>
                                        	</div>

                                            <input name="nextpaypaiddownloads_filetype" id="nextpaypaiddownloads_filetype" type="hidden" value="'.(isset($_GET['ty']) ? $_GET['ty'] : ($file_details['uploaded'] == 2 ? 'tab-3' : (!empty($file_details['uploaded']) ? 'tab-2' : 'tab-1' ))).'" />
                                        </div>




										</td>
									</tr>
									<tr>
										<th>'.__('مبلغ', 'nextpaypaiddownloads').':</th>
										<td>
											<input type="text" name="nextpaypaiddownloads_price" id="nextpaypaiddownloads_price" value="'.(!empty($id) ? number_format($file_details['price'], 0, '.', '') : '0').'" style="width: 80px; text-align: right;">
											<select name="nextpaypaiddownloads_currency" style="vertical-align: inherit; height:26px;" id="nextpaypaiddownloads_currency" onchange="nextpaypaiddownloads_supportedmethods();">');
		foreach ($this->currency_list as $currency) {
			echo '
												<option value="'.$currency.'"'.($currency == $file_details['currency'] ? ' selected="selected"' : '').'>'.$currency.'</option>';
		}
		print('
											</select>
											<label id="nextpaypaiddownloads_supported" style="color: green; display:none"></label>
											<br /><em>'.__('مبلغ مورد نظر جهت خريد فايل را تعيين نماييد ، ورود مبلغ 0 به معني رايگان بودن فايل خواهد بود.', 'nextpaypaiddownloads').'</em>
										</td>
									</tr>
									<tr>
										<th>'.__('تعداد موجود', 'nextpaypaiddownloads').':</th>
										<td><input type="text" name="nextpaypaiddownloads_available_copies" id="nextpaypaiddownloads_available_copies" value="'.(!empty($id) ? intval($file_details['available_copies']) : '0').'" style="width: 80px; text-align: right;"><br /><em>'.__('تعداد موجود جهت فروش فايل را تعيين نماييد . پس از رسيدن فروش فايل به اين سقف کليد خريد غير فعال خواهد شد. خالي گذاشتن و يا مقدار 0 براي اين فيلد به معني تعداد نامحدود مي باشد.در صورتي که محدوديتي در فروش نداريد اين گزينه را خالي بگذاريد.', 'nextpaypaiddownloads').'</em></td>
									</tr>
									<tr>
										<th>'.__('مسير دريافت لايسنس', 'nextpaypaiddownloads').':</th>
										<td><input type="text" name="nextpaypaiddownloads_license_url" id="nextpaypaiddownloads_license_url" value="'.htmlspecialchars($file_details['license_url'], ENT_QUOTES).'" class="widefat enput"'.(!in_array('curl', get_loaded_extensions()) ? ' readonly="readonly"' : '').'><br /><em>'.__('در صورتي که استفاده از اين فايل نيازمند دريافت لايسنس مي باشد . پس از پرداخت موفقيت آميز اطلاعات خريد نکست پی به اين مسير ارسال مي گردد. سپس محتواي برگشتي از اين مسير در  <strong>متن ايميل خريد موفق</strong> جايگزين فيلد {license_info} خواهد شد. در صورتي که فايل شما بدون لايسنس مي باشد از اين فيلد صرف نظر نماييد. استفاده از اين گزينه نيازمند فعال بودن CURL بر روي سرور هاست مي باشد.', 'nextpaypaiddownloads').'</em></td>
									</tr>
								</table>
								<div class="alignright">
								<input type="hidden" name="ak_action" value="nextpaypaiddownloads_update_file" />
								'.(!empty($id) ? '<input type="hidden" name="nextpaypaiddownloads_id" value="'.$id.'" />' : '').'
								<input type="submit" class="paiddownoads_button button-primary" name="submit" value="'.__('ذخيره اطلاعات', 'nextpaypaiddownloads').' »">
								</div>
								<br class="clear">
							</div>
						</div>
					</div>
				</div>
			</div>
			</form>
			<script type="text/javascript">
				function nextpaypaiddownloads_supportedmethods() {
					var nextpay_currencies = new Array("'.implode('", "', $this->nextpay_currency_list).'");
					var currency = jQuery("#nextpaypaiddownloads_currency").val();
					var supported = "";
					if (jQuery.inArray(currency, nextpay_currencies) >= 0) supported = "nextpay, ";
					supported = supported + "InterKassa";
					jQuery("#nextpaypaiddownloads_supported").html("'.__('Supported payment methods:', 'nextpaypaiddownloads').' " + supported);
				}
				nextpaypaiddownloads_supportedmethods();

              	function changeTab(tab){

              		var tab_id = jQuery(tab).attr("data-tab");

              		jQuery("ul.tabs li").removeClass("current");
              		jQuery(".tab-content").removeClass("current");

              		jQuery(tab).addClass("current");
              		jQuery("#"+tab_id).addClass("current");
                    jQuery("#nextpaypaiddownloads_filetype").val(tab_id);

              	}
                changeTab(jQuery("li[data-tab=\'"+jQuery("#nextpaypaiddownloads_filetype").val()+"\'"));
			</script>
		</div>');
	}

	function admin_links() {
		global $wpdb;

		if (isset($_GET["fid"])) $file_id = intval(trim(stripslashes($_GET["fid"])));
		else $file_id = 0;

		$tmp = $wpdb->get_row("SELECT COUNT(*) AS total FROM ".$wpdb->prefix."pd_downloadlinks WHERE deleted = '0'".($file_id > 0 ? " AND file_id = '".$file_id."'" : ""), ARRAY_A);
		$total = $tmp["total"];
		$totalpages = ceil($total/PD_RECORDS_PER_PAGE);
		if ($totalpages == 0) $totalpages = 1;
		if (isset($_GET["p"])) $page = intval($_GET["p"]);
		else $page = 1;
		if ($page < 1 || $page > $totalpages) $page = 1;
		$switcher = $this->page_switcher(get_bloginfo("wpurl")."/wp-admin/admin.php?page=paid-downloads-links".($file_id > 0 ? '&fid='.$file_id : ''), $page, $totalpages);

		$sql = "SELECT t1.*, t2.title AS file_title FROM ".$wpdb->prefix."pd_downloadlinks t1 LEFT JOIN ".$wpdb->prefix."pd_files t2 ON t2.id = t1.file_id WHERE t1.deleted = '0'".($file_id > 0 ? " AND file_id = '".$file_id."'" : "")." ORDER BY t1.created DESC LIMIT ".(($page-1)*PD_RECORDS_PER_PAGE).", ".PD_RECORDS_PER_PAGE;
		$rows = $wpdb->get_results($sql, ARRAY_A);
		if (!empty($this->error)) $message = "<div class='error'><p>".$this->error."</p></div>";
		if (!empty($this->info)) $message = "<div class='updated'><p>".$this->info."</p></div>";

		print ('
			<div class="wrap admin_nextpaypaiddownloads_wrap">
				<div id="icon-upload" class="icon32"><br /></div><h2>'.__('پرداخت نکست پی - لينک هاي دريافت', 'nextpaypaiddownloads').'</h2><br />
				'.$message.'
				<div class="nextpaypaiddownloads_buttons"><a class="button" href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-add-link'.($file_id > 0 ? '&fid='.$file_id : '').'">'.__('اضافه کردن لينک جديد', 'nextpaypaiddownloads').'</a></div>
				<div class="nextpaypaiddownloads_pageswitcher">'.$switcher.'</div>
				<table class="nextpaypaiddownloads_files">
				<tr>
					<th>'.__('لينک دانلود', 'nextpaypaiddownloads').'</th>
					<th style="width: 160px;">'.__('صاحب', 'nextpaypaiddownloads').'</th>
					<th style="width: 160px;">'.__('فايل', 'nextpaypaiddownloads').'</th>
					<th style="width: 80px;">'.__('منبع', 'nextpaypaiddownloads').'</th>
					<th style="width: 50px;">'.__('حذف', 'nextpaypaiddownloads').'</th>
				</tr>
		');
		if (sizeof($rows) > 0)
		{
			foreach ($rows as $row)
			{
				if (time() <= $row["created"] + 24*3600*$this->options['link_lifetime']) {
					$expired = "منقضي در ".$this->period_to_string($row["created"] + 24*3600*$this->options['link_lifetime'] - time())." ديگر";
					$bg_color = "#FFFFFF";
				} else {
					$expired = "";
					$bg_color = "#F0F0F0";
				}
				print ('
				<tr style="background-color: '.$bg_color .';">
					<td><input type="text" class="widefat" onclick="this.focus();this.select();" readonly="readonly" dir="ltr" value="'.get_bloginfo('wpurl').'/?nextpaypaiddownloads_key='.$row["download_key"].'">'.(!empty($expired) ? '<br /><em>'.$expired.'</em>' : '').'</td>
					<td>'.htmlspecialchars($row['owner'], ENT_QUOTES).'</td>
					<td>'.(!empty($row['file_title']) ? htmlspecialchars($row['file_title'], ENT_QUOTES) : '-').'</td>
					<td>'.htmlspecialchars($row['source'] == 'purchasing' ? 'فروش' : 'دستي', ENT_QUOTES).'</td>
					<td style="text-align: center;">
						<a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?ak_action=nextpaypaiddownloads_delete_link&id='.$row['id'].'" title="'.__('حذف لينک دريافت', 'nextpaypaiddownloads').'" onclick="return nextpaypaiddownloads_submitOperation();"><img src="'.plugins_url('/images/delete.png', __FILE__).'" alt="'.__('Delete download link', 'nextpaypaiddownloads').'" border="0"></a>
					</td>
				</tr>
				');
			}
		}
		else
		{
			print ('
				<tr><td colspan="5" style="padding: 20px; text-align: center;">'.__('هيچ لينک دريافتي موجود نمي باشد', 'nextpaypaiddownloads').'</td></tr>
			');
		}
		print ('
				</table>
				<div class="nextpaypaiddownloads_buttons"><a class="button" href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-add-link'.($file_id > 0 ? '&fid='.$file_id : '').'">'.__('اضافه کردن لينک جديد', 'nextpaypaiddownloads').'</a></div>
				<div class="nextpaypaiddownloads_pageswitcher">'.$switcher.'</div>
				<div class="nextpaypaiddownloads_legend">
				<strong>'.__('راهنما :', 'nextpaypaiddownloads').'</strong>
					<p><img src="'.plugins_url('/images/delete.png', __FILE__).'" alt="'.__('حذف لينک دريافت', 'nextpaypaiddownloads').'" border="0"> '.__('حذف لينک دريافت', 'nextpaypaiddownloads').'</p>
					<br />
					<div style="width: 14px; height: 14px; float: right; border: 1px solid #CCC; margin: 0px 10px 0px 0px; background-color: #FFFFFF;""></div> '.__('لينک هاي فعال', 'nextpaypaiddownloads').'<br />
					<div style="width: 14px; height: 14px; float: right; border: 1px solid #CCC; margin: 0px 10px 0px 0px; background-color: #F0F0F0;"></div> '.__('لينک هاي منقضي', 'nextpaypaiddownloads').'<br />
				</div>
			</div>
		');
	}

	function admin_add_link() {
		global $wpdb;

		if (isset($_GET["fid"])) $file_id = intval(trim(stripslashes($_GET["fid"])));
		else $file_id = 0;
		
		if (!empty($this->error)) $message = "<div class='error'><p>".$this->error."</p></div>";
		else if (!empty($this->info)) $message = "<div class='updated'><p>".$this->info."</p></div>";

		$sql = "SELECT * FROM ".$wpdb->prefix."pd_files WHERE deleted = '0' ORDER BY registered DESC";
		$files = $wpdb->get_results($sql, ARRAY_A);
		if (empty($files)) {
			print ('
			<div class="wrap admin_nextpaypaiddownloads_wrap">
				<div id="icon-options-general" class="icon32"><br /></div><h2>'.__('پرداخت نکست پی - اضافه کردن لينک دريافت', 'nextpaypaiddownloads').'</h2>
				<div class="error"><p>'.__('ابتدا يک فايل را جهت ايجاد لينک به سيستم اضافه نماييد .', 'nextpaypaiddownloads').'</p></div>
			</div>');
			return;
		}

		print ('
		<div class="wrap admin_nextpaypaiddownloads_wrap">
			<div id="icon-options-general" class="icon32"><br /></div><h2>'.__('پرداخت نکست پی - اضافه کردن لينک دريافت', 'nextpaypaiddownloads').'</h2>
			'.$message.'
			<form enctype="multipart/form-data" method="post" style="margin: 0px" action="'.get_bloginfo('wpurl').'/wp-admin/admin.php">
			<div class="postbox-container" style="width: 100%;">
				<div class="metabox-holder">
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<!--<div class="handlediv" title="Click to toggle"><br></div>-->
							<h3 class="hndle" style="cursor: default;"><span>'.__('اضافه کردن لينک دريافت', 'nextpaypaiddownloads').'</span></h3>
							<div class="inside">
								<table class="nextpaypaiddownloads_useroptions">
									<tr>
										<th>'.__('فايل', 'nextpaypaiddownloads').':</th>
										<td>
											<select name="nextpaypaiddownloads_fileselector" id="nextpaypaiddownloads_fileselector">
												<option value="">-- '.__('انتخاب فايل', 'nextpaypaiddownloads').' --</option>');
		foreach ($files as $file)
		{
			echo '<option value="'.$file["id"].'"'.($file["id"] == $file_id ? 'selected="selected"' : '').'>'.htmlspecialchars($file["title"], ENT_QUOTES).'</option>';
		}
		print('
											</select><br /><em>'.__('لطفا يک فايل را انتخاب نماييد .', 'nextpaypaiddownloads').'</em>
										</td>
									</tr>
									<tr>
										<th>'.__('صاحب لينک', 'nextpaypaiddownloads').':</th>
										<td><input type="text" name="nextpaypaiddownloads_link_owner" id="nextpaypaiddownloads_link_owner" value="" style="width: 50%;"><br /><em>'.__('لطفا يک آدرس ايميل را جهت ايجاد لينک دريافت وارد نماييد .', 'nextpaypaiddownloads').'</em></td>
									</tr>
								</table>
								<div class="alignright">
								<input type="hidden" name="ak_action" value="nextpaypaiddownloads_update_link" />
								<input type="submit" class="paiddownoads_button button-primary" name="submit" value="'.__('ذخيره اطلاعات', 'nextpaypaiddownloads').' »">
								</div>
								<br class="clear">
							</div>
						</div>
					</div>
				</div>
			</div>
			</form>
		</div>');
	}
	
	function admin_transactions() {

		global $wpdb;
		if (isset($_GET["s"])) $search_query = trim(stripslashes($_GET["s"]));
		else $search_query = "";
		if (isset($_GET["fid"])) $file_id = intval(trim(stripslashes($_GET["fid"])));
		else $file_id = 0;
		$tmp = $wpdb->get_row("SELECT COUNT(*) AS total FROM ".$wpdb->prefix."pd_transactions WHERE id > 0".($file_id > 0 ? " AND file_id = '".$file_id."'" : "").((strlen($search_query) > 0) ? " AND (payer_name LIKE '%".addslashes($search_query)."%' OR payer_email LIKE '%".addslashes($search_query)."%')" : ""), ARRAY_A);
		$total = $tmp["total"];
		$totalpages = ceil($total/PD_RECORDS_PER_PAGE);
		if ($totalpages == 0) $totalpages = 1;
		if (isset($_GET["p"])) $page = intval($_GET["p"]);
		else $page = 1;
		if ($page < 1 || $page > $totalpages) $page = 1;
		$switcher = $this->page_switcher(get_bloginfo("wpurl")."/wp-admin/admin.php?page=paid-downloads-transactions".((strlen($search_query) > 0) ? "&s=".rawurlencode($search_query) : "").($file_id > 0 ? "&fid=".$file_id : ""), $page, $totalpages);

		$sql = "SELECT t1.*, t2.title AS file_title FROM ".$wpdb->prefix."pd_transactions t1 LEFT JOIN ".$wpdb->prefix."pd_files t2 ON t1.file_id = t2.id WHERE t1.id > 0".($file_id > 0 ? " AND t1.file_id = '".$file_id."'" : "").((strlen($search_query) > 0) ? " AND (t1.payer_name LIKE '%".addslashes($search_query)."%' OR t1.payer_email LIKE '%".addslashes($search_query)."%')" : "")." ORDER BY t1.created DESC LIMIT ".(($page-1)*PD_RECORDS_PER_PAGE).", ".PD_RECORDS_PER_PAGE;
		$rows = $wpdb->get_results($sql, ARRAY_A);

		print ('
			<div class="wrap admin_nextpaypaiddownloads_wrap">
				<div id="icon-edit-pages" class="icon32"><br /></div><h2>'.__('پرداخت نکست پی - پرداخت ها', 'nextpaypaiddownloads').'</h2><br />
				<form action="'.get_bloginfo("wpurl").'/wp-admin/admin.php" method="get" style="margin-bottom: 10px;">
				<input type="hidden" name="page" value="paid-downloads-transactions" />
				'.($file_id > 0 ? '<input type="hidden" name="bid" value="'.$file_id.'" />' : '').'
				'.__('جستجوي خريدار :', 'nextpaypaiddownloads').' <input type="text" name="s" value="'.htmlspecialchars($search_query, ENT_QUOTES).'">
				<input type="submit" class="button-secondary action" value="'.__('جستجو', 'nextpaypaiddownloads').'" />
				'.((strlen($search_query) > 0) ? '<input type="button" class="button-secondary action" value="'.__('برگشت به حالت ليست', 'nextpaypaiddownloads').'" onclick="window.location.href=\''.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-transactions'.($file_id > 0 ? '&bid='.$file_id : '').'\';" />' : '').'
				</form>
				<div class="nextpaypaiddownloads_pageswitcher">'.$switcher.'</div>
				<table class="nextpaypaiddownloads_files">
				<tr>
					<th>'.__('فايل', 'nextpaypaiddownloads').'</th>
					<th>'.__('خريدار', 'nextpaypaiddownloads').'</th>
					<th style="width: 100px;">'.__('مبلغ', 'nextpaypaiddownloads').'</th>
					<th style="width: 120px;">'.__('وضعيت', 'nextpaypaiddownloads').'</th>
					<th style="width: 130px;">'.__('ايجاد', 'nextpaypaiddownloads').'*</th>
				</tr>
		');
		if (sizeof($rows) > 0)
		{
			foreach ($rows as $row)
			{
				print ('
				<tr>
					<td>'.htmlspecialchars($row['file_title'], ENT_QUOTES).'</td>
					<td>'.htmlspecialchars($row['payer_name'], ENT_QUOTES).'<br /><em>'.htmlspecialchars($row['payer_email'], ENT_QUOTES).'</em><br /><em>'.htmlspecialchars($row['payer_phone'], ENT_QUOTES).'</em></td>
					<td style="text-align: right;">'.number_format($row['gross'], 0, ".", "").' '.$row['currency'].'</td>
					<td><a href="'.get_bloginfo("wpurl").'/wp-admin/admin.php?ak_action=nextpaypaiddownloads_transactiondetails&id='.$row['id'].'" class="thickbox" title="Transaction Details">'.$row["payment_status"].'</a><br /><em>'.$row["transaction_type"].'</em></td>
					<td>'.date("Y-m-d H:i", $row["created"]).'</td>
				</tr>
				');
			}
		}
		else
		{
			print ('
				<tr><td colspan="5" style="padding: 20px; text-align: center;">'.((strlen($search_query) > 0) ? __('هيچ نتيجه اي يافت نشد', 'nextpaypaiddownloads').' "<strong>'.htmlspecialchars($search_query, ENT_QUOTES).'</strong>"' : __('هيچ پرداختي يافت نشد .', 'nextpaypaiddownloads')).'</td></tr>
			');
		}
		print ('
				</table>
				<div class="nextpaypaiddownloads_pageswitcher">'.$switcher.'</div>
			</div>');
	}

	function admin_request_handler() {
		global $wpdb;
		if (!empty($_POST['ak_action'])) {
			switch($_POST['ak_action']) {
				case 'nextpaypaiddownloads_update_settings':
					$this->populate_settings();
					$this->options['enable_nextpay'] = "on";

					if (isset($_POST["nextpaypaiddownloads_nextpay_address"])) $this->options['nextpay_address'] = "on";
					else $this->options['nextpay_address'] = "off";
					if (isset($_POST["nextpaypaiddownloads_handle_unverified"])) $this->options['handle_unverified'] = "on";
					else $this->options['handle_unverified'] = "off";

                    if (!empty($_POST["nextpaypaiddownloads_getphonenumber"]))
                        $this->options['getphonenumber'] = "on";
                    else
                        $this->options['getphonenumber'] = "off";
                    if (!empty($_POST["nextpaypaiddownloads_showdownloadlink"]))
                        $this->options['showdownloadlink'] = "on";
                    else
                        $this->options['showdownloadlink'] = "off";

					$buynow_image = "";
					$errors_info = "";
					if (is_uploaded_file($_FILES["nextpaypaiddownloads_buynow_image"]["tmp_name"]))
					{
						$ext = strtolower(substr($_FILES["nextpaypaiddownloads_buynow_image"]["name"], strlen($_FILES["nextpaypaiddownloads_buynow_image"]["name"])-4));
						if ($ext != ".jpg" && $ext != ".gif" && $ext != ".png") $errors[] = __('Custom "Buy Now" button has invalid image type', 'nextpaypaiddownloads');
						else
						{
							list($width, $height, $type, $attr) = getimagesize($_FILES["nextpaypaiddownloads_buynow_image"]["tmp_name"]);
							if ($width > 600 || $height > 600) $errors[] = __('Custom "Buy Now" button has invalid image dimensions', 'nextpaypaiddownloads');
							else
							{
								$buynow_image = "button_".md5(microtime().$_FILES["nextpaypaiddownloads_buynow_image"]["tmp_name"]).$ext;
								if (!move_uploaded_file($_FILES["nextpaypaiddownloads_buynow_image"]["tmp_name"], ABSPATH."wp-content/uploads/paid-downloads/".$buynow_image))
								{
									$errors[] = "Can't save uploaded image";
									$buynow_image = "";
								}
								else
								{
									if (!empty($this->options['buynow_image']))
									{
										if (file_exists(ABSPATH."wp-content/uploads/paid-downloads/".$this->options['buynow_image']) && is_file(ABSPATH."wp-content/uploads/paid-downloads/".$this->options['buynow_image']))
											unlink(ABSPATH."wp-content/uploads/paid-downloads/".$this->options['buynow_image']);
									}
								}
							}
						}
					}
					if (!empty($buynow_image)) $this->options['buynow_image'] = $buynow_image;
					if ($this->options['buynow_type'] == "custom" && empty($this->options['buynow_image']))
					{
						$this->options['buynow_type'] = "html";
						$errors_info = __('Due to "Buy Now" image problem "Buy Now" button was set to Standard HTML button.', 'nextpaypaiddownloads');
					}
					$errors = $this->check_settings();
					if (empty($errors_info) && $errors === true)
					{
						$this->update_settings();
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=paid-downloads&updated=true');
						die();
					}
					else
					{
						$this->update_settings();
						$message = "";
						if (is_array($errors)) $message = __('در ثبت اطلاعات خطاهاي زير وجود دارد :', 'nextpaypaiddownloads').'<br />- '.implode('<br />- ', $errors);
						if (!empty($errors_info)) $message .= (empty($message) ? "" : "<br />").$errors_info;
						setcookie("nextpaypaiddownloads_error", $message, time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=paid-downloads');
						die();
					}
					break;

				case 'nextpaypaiddownloads_update_file':
					if (isset($_POST["nextpaypaiddownloads_id"]) && !empty($_POST["nextpaypaiddownloads_id"])) {
						$id = intval($_POST["nextpaypaiddownloads_id"]);
						$file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."pd_files WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
						if (intval($file_details["id"]) == 0) unset($id);
					}
					$title = trim(stripslashes($_POST["nextpaypaiddownloads_title"]));
					$price = trim(stripslashes($_POST["nextpaypaiddownloads_price"]));
					$price = number_format(floatval($price), 2, '.', '');
					$currency = trim(stripslashes($_POST["nextpaypaiddownloads_currency"]));
					$available_copies = trim(stripslashes($_POST["nextpaypaiddownloads_available_copies"]));
					$available_copies = intval($available_copies);
					$file_selector = trim(stripslashes($_POST["nextpaypaiddownloads_fileselector"]));
   					$file_link = trim(stripslashes($_POST["nextpaypaiddownloads_filelink"]));
                    $filetype = trim(stripslashes($_POST["nextpaypaiddownloads_filetype"]));

					$license_url = trim(stripslashes($_POST["nextpaypaiddownloads_license_url"]));
					if (!preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $license_url) || strlen($license_url) == 0) $license_url = "";

					if ($filetype == "" || $filetype == "tab-1") {
    					  if(is_uploaded_file($_FILES["nextpaypaiddownloads_file"]["tmp_name"]))
                          {
        						$uploaded = 1;
                                if (empty($title)) $title = $_FILES["nextpaypaiddownloads_file"]["name"];
        						if ($file_details["uploaded"] == 1) {
        							if (file_exists(ABSPATH."wp-content/uploads/paid-downloads/files/".$file_details["filename"]) && is_file(ABSPATH."wp-content/uploads/paid-downloads/files/".$file_details["filename"]))
        								unlink(ABSPATH."wp-content/uploads/paid-downloads/files/".$file_details["filename"]);
        						}
        						$filename = $this->get_filename(ABSPATH.'wp-content/uploads/paid-downloads/files/', $_FILES["nextpaypaiddownloads_file"]["name"]);
        						$filename_original = $_FILES["nextpaypaiddownloads_file"]["name"];
        						if (!move_uploaded_file($_FILES["nextpaypaiddownloads_file"]["tmp_name"], ABSPATH."wp-content/uploads/paid-downloads/files/".$filename)) {
        							setcookie("nextpaypaiddownloads_error", __('Unable to save uploaded file on server', 'nextpaypaiddownloads'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
        							header('Location: '.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-add'.(!empty($id) ? '&id='.$id : '').'&ty='.$filetype);
        							exit;
        						}
                          }
                          else
                          {
                                setcookie("nextpaypaiddownloads_error", __('خطا ! فایل مورد نظر خود را جهت بارگزاری انتخاب نمایید ', 'nextpaypaiddownloads'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
    							header('Location: '.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-add'.(!empty($id) ? '&id='.$id : '').'&ty='.$filetype);
    							exit;
                          }
					}
                    else if ($filetype == "tab-2")
                    {
						if ($file_selector != "" && file_exists(ABSPATH."wp-content/uploads/paid-downloads/files/".$file_selector) && is_file(ABSPATH."wp-content/uploads/paid-downloads/files/".$file_selector)) {
						    if (empty($title)) $title = $_FILES["nextpaypaiddownloads_file"]["name"];
							$filename = $file_selector;
							$filename_original = $filename;
							if (empty($title)) $title = $filename;
							if ($file_selector == $file_details["filename"]) {
								$uploaded = 1;
								$filename_original = $file_details["filename_original"];
							} else {
								$uploaded = 0;
								$filename_original = $filename;
							}
						} else {
							setcookie("nextpaypaiddownloads_error", __('خطا ! هیچ فایلی انتخاب نشده است', 'nextpaypaiddownloads'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
							header('Location: '.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-add'.(!empty($id) ? '&id='.$id : '').'&ty='.$filetype);
							exit;
						}
					} else if ($filetype == "tab-3") {
                        if($file_link != "")
                        {
                            if (preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i",$file_link))
                            {
                                if (empty($title)) $title = 'بدون عنوان';
                                 $uploaded = 2;
                                 $filename_original = $file_link;
                                 $filename = $file_link;
                            }
                            else
                            {
                                  setcookie("nextpaypaiddownloads_error", __('خطا ! مسیر فایل وارد شده صحیح نمی باشد', 'nextpaypaiddownloads'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
                                  header('Location: '.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-add'.(!empty($id) ? '&id='.$id : '').'&ty='.$filetype);
                                  exit;
                            }
                        }
                        else
                        {
                            setcookie("nextpaypaiddownloads_error", __('خطا ! مسیر دانلود فایل وارد نشده است', 'nextpaypaiddownloads'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
							header('Location: '.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-add'.(!empty($id) ? '&id='.$id : '').'&ty='.$filetype);
							exit;
                        }
                    }
					if (!empty($id)) {
						$sql = "UPDATE ".$wpdb->prefix."pd_files SET
							title = '". esc_sql($title)."',
							filename = '". esc_sql($filename)."',
							filename_original = '". esc_sql($filename_original)."',
							price = '".$price."',
							currency = '".$currency."',
							available_copies = '".$available_copies."',
							uploaded = '".$uploaded."',
							license_url = '". esc_sql($license_url)."'
							WHERE id = '".$id."'";
						if ($wpdb->query($sql) !== false) {
							setcookie("nextpaypaiddownloads_info", __('فايل با موفقيت بارگزاري گرديد', 'nextpaypaiddownloads'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
							header('Location: '.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-files');
							exit;
						} else {
							setcookie("nextpaypaiddownloads_error", __('سرويس در دسترس نمي باشد', 'nextpaypaiddownloads'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
							header('Location: '.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-add'.(!empty($id) ? '&id='.$id : ''));
							exit;
						}
					} else {
						$sql = "INSERT INTO ".$wpdb->prefix."pd_files (
							title, filename, filename_original, price, currency, registered, available_copies, uploaded, license_url, deleted) VALUES (
							'". esc_sql($title)."',
							'". esc_sql($filename)."',
							'". esc_sql($filename_original)."',
							'".$price."',
							'".$currency."',
							'".time()."',
							'".$available_copies."',
							'".$uploaded."',
							'". esc_sql($license_url)."',
							'0'
							)";
						if ($wpdb->query($sql) !== false) {
							setcookie("nextpaypaiddownloads_info", __('فايل با موفقيت اضافه گرديد', 'nextpaypaiddownloads'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
							header('Location: '.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-files');
							exit;
						} else {
							setcookie("nextpaypaiddownloads_error", __('سرويس در دسترس نمي باشد', 'nextpaypaiddownloads'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
							header('Location: '.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-add'.(!empty($id) ? '&id='.$id : ''));
							exit;
						}
					}
					break;

				case 'nextpaypaiddownloads_update_link':
					$link_owner = trim(stripslashes($_POST["nextpaypaiddownloads_link_owner"]));
					if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/i", $link_owner) || strlen($link_owner) == 0) {
						setcookie("nextpaypaiddownloads_error", __('ايميل صاحب لينک به صورت صحيح وارد نشده است.', 'nextpaypaiddownloads'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-add-link');
						exit;
					}
					$file_id = trim(stripslashes($_POST["nextpaypaiddownloads_fileselector"]));
					$file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."pd_files WHERE id = '".$file_id."' AND deleted = '0'", ARRAY_A);
					if (intval($file_details["id"]) == 0) {
						setcookie("nextpaypaiddownloads_error", __('خطا در فراخواني سرويس .', 'nextpaypaiddownloads'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-add-link');
						exit;
					}
					$link = $this->generate_downloadlink($file_details["id"], $link_owner, "manual");
					setcookie("nextpaypaiddownloads_info", __('لينک دريافت فايل با موفقيت ايجاد گرديد', 'nextpaypaiddownloads'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
					header('Location: '.get_bloginfo("wpurl").'/wp-admin/admin.php?page=paid-downloads-links');
					exit;
					break;
			}
		}
		if (!empty($_GET['ak_action'])) {
			switch($_GET['ak_action']) {
				case 'nextpaypaiddownloads_delete':
					$id = intval($_GET["id"]);
					$file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."pd_files WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
					if (intval($file_details["id"]) == 0) {
						setcookie("nextpaypaiddownloads_error", __('Invalid service call', 'nextpaypaiddownloads'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=paid-downloads-files');
						die();
					}

					$sql = "UPDATE ".$wpdb->prefix."pd_files SET deleted = '1' WHERE id = '".$id."'";
					if ($wpdb->query($sql) !== false) {
						if (file_exists(ABSPATH."wp-content/uploads/paid-downloads/files/".$file_details["filename"]) && is_file(ABSPATH."wp-content/uploads/paid-downloads/files/".$file_details["filename"])) {
							$tmp_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."pd_files WHERE filename = '".$file_details["filename"]."' AND deleted = '0'", ARRAY_A);
							if (intval($tmp_details["id"]) == 0 && $file_details["uploaded"] == 1) {
								unlink(ABSPATH."wp-content/uploads/paid-downloads/files/".$file_details["filename"]);
							}
						}
						setcookie("nextpaypaiddownloads_info", __('File successfully removed', 'nextpaypaiddownloads'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=paid-downloads-files');
						die();
					} else {
						setcookie("nextpaypaiddownloads_error", __('Invalid service call', 'nextpaypaiddownloads'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=paid-downloads-files');
						die();
					}
					break;
				case 'nextpaypaiddownloads_delete_link':
					$id = intval($_GET["id"]);
					$file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."pd_downloadlinks WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
					if (intval($file_details["id"]) == 0) {
						setcookie("nextpaypaiddownloads_error", __('Invalid service call', 'nextpaypaiddownloads'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=paid-downloads-links');
						die();
					}
					$sql = "UPDATE ".$wpdb->prefix."pd_downloadlinks SET deleted = '1' WHERE id = '".$id."'";
					if ($wpdb->query($sql) !== false) {
						setcookie("nextpaypaiddownloads_info", __('Temporary download link successfully removed.', 'nextpaypaiddownloads'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=paid-downloads-links');
						die();
					} else {
						setcookie("nextpaypaiddownloads_error", __('Invalid service call.', 'nextpaypaiddownloads'), time()+30, "/", ".".str_replace("www.", "", $_SERVER["SERVER_NAME"]));
						header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=paid-downloads-links');
						die();
					}
					break;
				case 'nextpaypaiddownloads_hidedonationbox':
					$this->options['show_donationbox'] = PD_VERSION;
					$this->update_settings();
					header('Location: '.get_bloginfo('wpurl').'/wp-admin/admin.php?page=paid-downloads');
					die();
					break;
				case 'nextpaypaiddownloads_transactiondetails':
					if (isset($_GET["id"]) && !empty($_GET["id"])) {
						$id = intval($_GET["id"]);
						$transaction_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."pd_transactions WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
						if (intval($transaction_details["id"]) != 0) {
							echo '
<html>
<head>
	<title>Transaction Details</title>
</head>
<body>
	<table style="width: 100%;">';
							$details = explode("&", $transaction_details["details"]);
							foreach ($details as $param) {
								$data = explode("=", $param, 2);
								echo '
		<tr>
			<td style="width: 170px; font-weight: bold;">'.esc_attr($data[0]).'</td>
			<td>'.esc_attr(urldecode($data[1])).'</td>
		</tr>';
							}
							echo '
	</table>						
</body>
</html>';
						} else echo 'No data found!';
					} else echo 'No data found!';
					die();
					break;
				default:
					break;
					
			}
		}
	}

	function admin_warning() {
		echo '
		<div class="updated"><p>'.__('<strong>»افزونه پرداخت نکست پی نصب شده است.</strong> هم اکنون جهت استفاده نیازمند اعمال <a href="admin.php?page=paid-downloads">تنظیمات</a> می باشد.', 'nextpaypaiddownloads').'</p></div>
		';
	}

	function admin_warning_reactivate() {
		echo '
		<div class="error"><p>'.__('<strong>Please deactivate Paid Downloads plugin and activate it again.</strong> If you already done that and see this message, please create the folder "/wp-content/uploads/paid-downloads/files/" manually and set permission 0777 for this folder.', 'nextpaypaiddownloads').'</p></div>
		';
	}

	function admin_header() {
		global $wpdb;
		echo '
		<link rel="stylesheet" type="text/css" href="'.plugins_url('/css/style.css?ver='.PD_VERSION, __FILE__).'" media="screen" />
		<link href="http://fonts.googleapis.com/css?family=Oswald" rel="stylesheet" type="text/css" />
		<script type="text/javascript">
			function nextpaypaiddownloads_submitOperation() {
				var answer = confirm("Do you really want to continue?")
				if (answer) return true;
				else return false;
			}
		</script>';
	}

	function front_init() {
		global $wpdb;
		if (isset($_GET['nextpaypaiddownloads_id']) || isset($_GET['nextpaypaiddownloads_key'])) {
			ob_start();
			if(!ini_get('safe_mode')) set_time_limit(0);
			ob_end_clean();
			if (isset($_GET["nextpaypaiddownloads_id"])) {
				$id = intval($_GET["nextpaypaiddownloads_id"]);
				$file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix . "pd_files WHERE id = '".$id."' AND deleted = '0'", ARRAY_A);
				if (intval($file_details["id"]) == 0) die(__('Invalid download link', 'nextpaypaiddownloads'));
				if ($file_details["price"] != 0 && !current_user_can('manage_options')) die(__('Invalid download link', 'nextpaypaiddownloads'));
			} else {
				if (!isset($_GET["nextpaypaiddownloads_key"])) die(__('Invalid download link', 'nextpaypaiddownloads'));
				$download_key = $_GET["nextpaypaiddownloads_key"];
				$download_key = preg_replace('/[^a-zA-Z0-9]/', '', $download_key);
				$sql = "SELECT * FROM ".$wpdb->prefix."pd_downloadlinks WHERE download_key = '".$download_key."' AND deleted = '0'";
				$link_details = $wpdb->get_row($sql, ARRAY_A);
				if (intval($link_details["id"]) == 0) die(__('Invalid download link', 'nextpaypaiddownloads'));
				$file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix . "pd_files WHERE id = '".$link_details["file_id"]."' AND deleted = '0'", ARRAY_A);
				if (intval($file_details["id"]) == 0) die(__('Invalid download link', 'nextpaypaiddownloads'));
				if ($link_details["created"]+24*3600*intval($this->options['link_lifetime']) < time()) die(__('Download link was expired', 'nextpaypaiddownloads'));
			}
          $filename = ABSPATH."wp-content/uploads/paid-downloads/files/".$file_details["filename"];
          $filename_original = $file_details["filename_original"];
            if($file_details["uploaded"] == 0 || $file_details["uploaded"] == 1)
            {


        			if (!file_exists($filename) || !is_file($filename)) die(__('File not found', 'nextpaypaiddownloads'));

        			$length = filesize($filename);
                    ob_clean();
        			if (strstr($_SERVER["HTTP_USER_AGENT"],"MSIE")) {
        				header("Pragma: public");
        				header("Expires: 0");
        				header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        				header("Content-type: application-download");
        				header("Content-Length: ".$length);
        				header("Content-Disposition: attachment; filename=\"".$filename_original."\"");
        				header("Content-Transfer-Encoding: binary");
        			} else {
        				header("Content-type: application-download");
        				header("Content-Length: ".$length);
        				header("Content-Disposition: attachment; filename=\"".$filename_original."\"");
        			}

        			$handle_read = fopen($filename, "rb");
        			while (!feof($handle_read) && $length > 0) {
        				$content = fread($handle_read, 1024);
        				echo substr($content, 0, min($length, 1024));
        				$length = $length - strlen($content);
        				if ($length < 0) $length = 0;
        			}
        			fclose($handle_read);
        			exit;
            }
            else if($file_details["uploaded"] == 2)
            {
                        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
                   $messagePage = '<!DOCTYPE html>
                    <html>
                    <head runat="server">
                        <title>Downloading ...</title>
                        <meta http-equiv="Content-Type" content="Type=text/html; charset=utf-8" />

                    </head>
                    <body style="text-align:center">
                        <br />            <br />            <br />            <br />
                        <script type="text/javascript" src="'.get_bloginfo("wpurl").'/wp-includes/js/jquery/jquery.js?ver=1.11.3"></script>
                        <script>
                        jQuery(document).ready(function() {
                            ';
                        $messagePage .= 'var arr = [ ';
                        for ($i = strlen($filename_original)-1; $i >= 0; $i--) {
                            $messagePage .= '"'.$filename_original[$i].'" , "'.$characters[mt_rand(0, 29)].'" , ';
                        }

                       $messagePage .= ' ""]';
                       $messagePage .= '

                            var path = "";

                            for(var i = arr.length -1 ; i >= 0 ; i=i-2)
                                path += arr[i];

                            setTimeout("window.location.assign(\'"+path+"\');", 1000);
                        });
                        </script>
                    </body>
                    </html>';
                    echo $messagePage;
                    exit;
            } else
            {
                echo 'Uploaded Not Find !';
                exit;
            }
		}
         else if (isset($_GET['nextpaypaiddownloads_ipn']))
        {
        $messagePage = '<html xmlns="http://www.w3.org/1999/xhtml">
        <head runat="server">
            <title>نتيجه پرداخت </title>
            <meta http-equiv="Content-Type" content="Type=text/html; charset=utf-8" />
        </head>
        <body style="text-align:center">
            <br />            <br />            <br />            <br />
            <div style="border: 1px solid;margin:auto;padding:15px 10px 15px 50px; width:600px;font-size:8pt; line-height:25px;$Style$">
             $Message$
            </div> <br /></br> <a href="'.get_bloginfo("wpurl").'" style="font:size:8pt ; color:#333333; font-family:tahoma; font-size:7pt" >بازگشت به صفحه اصلي</a>
        </body>
        </html>';
        $style = 'font-family:tahoma; text-align:right; direction:rtl';
        $style_succ = 'color: #4F8A10;background-color: #DFF2BF;'.$style;
        $style_alrt = 'color: #9F6000;background-color: #FEEFB3;'.$style;
        $style_errr = 'color: #D8000C;background-color: #FFBABA;'.$style;

        $trans_id = $_POST['trans_id'];
        $order_id = $_POST['order_id'];
        if(!isset($trans_id) && !isset($order_id))
        {

            $mss = 'پرداخت ناموفق / خطا در عمليات پرداخت ! کاربر گرامي ، فرايند پرداخت با خطا مواجه گرديد !<br> با تشکر <br>'.PHP_EOL.get_bloginfo("name");
            $messagePage = str_replace('$Message$',$mss,$messagePage);
            $messagePage = str_replace('$Style$',$style_errr,$messagePage);
        }
        else
        {
		


            
            $resNumber = $_GET['resNum'];
			
            $order_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."pd_orders WHERE id = '".intval($resNumber)."'", ARRAY_A);

			if (intval($file_details["id"]) == 0) $payment_status = "Unrecognized";

            $file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."pd_files WHERE id = '".intval($order_details["file_id"])."'", ARRAY_A);
			
			


            $postPrice = intval($file_details["price"]);

             if($file_details['currency'] == "ريال")
                    $postPrice = $postPrice / 10;

				
				 $client = new SoapClient('http://api.nextpay.org/gateway/verify.wsdl', array('encoding' => 'UTF-8'));
				$result = $client->PaymentVerification(
									array(
											'api_key'     => $this->options['nextpay_api_key'],
											'trans_id'      => $trans_id,
											'order_id'      => $order_id,
											'amount'         => $postPrice,
										)
				);
			$result = $result->PaymentVerificationResult;

			$response = $result->code;


            $payment_status = $response;

            $mc_currency = $file_details['currency'];
            $payer_nextpay = $order_details["payer_name"];
            $payer_phone = $order_details["payer_phone"];
            $payer_email = $order_details["payer_email"];
            if (intval($response == 0)) {
			$refNumber = $trans_id;
            	$sql = "INSERT INTO ".$wpdb->prefix."pd_transactions (
            		file_id, payer_name, payer_email, payer_phone, gross, currency, payment_status, transaction_type, details, created) VALUES (
            		'".intval($order_details["file_id"])."',
            		'". esc_sql($payer_nextpay)."',
            		'". esc_sql($payer_email)."',
                    '". esc_sql($payer_phone)."',
            		'".floatval($file_details["price"])."',
            		'".$mc_currency."',
            		'".$payment_status."',
            		'نکست پی : ".$trans_id."',
            		'".$payment_status."',
            		'".time()."'
            	)";

                $wpdb->query($sql);

                $license_info = "";
                if (preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $file_details["license_url"]) && strlen($file_details["license_url"]) != 0 && in_array('curl', get_loaded_extensions())) {
                	$request = "";
                	foreach ($_POST as $key => $value) {
                		$value = urlencode(stripslashes($value));
                		$request .= "&".$key."=".$value;
                	}
                	$data = $this->get_license_info($file_details["license_url"], $request);
                	$license_info = $data["content"];
                }
                $download_link = $this->generate_downloadlink($file_details["id"], $payer_email, "purchasing");
                $tags = array("{name}", "{payer_email}", "{product_title}", "{product_price}", "{product_currency}", "{download_link}", "{download_link_lifetime}", "{license_info}", "{transaction_date}");
                $vals = array($payer_nextpay,  $payer_email, $file_details["title"], $postPrice, $mc_currency, "<a href='". $download_link."' >".$download_link."</a>" ,$this->options['link_lifetime'], $license_info, date("Y-m-d H:i:s")." (server time)");

                $body =  '<div style="'.$style.'" >'.str_replace($tags, $vals, $this->options['success_email_body']).'</div>';
                $body =  str_replace("\n", "<br>", $body);

                $mail_headers = "Content-Type: text/html; charset=utf-8\r\n";
                $mail_headers .= "From: ".$this->options['from_name']." <".$this->options['from_email'].">\r\n";
                $mail_headers .= "X-Mailer: PHP/".phpversion()."\r\n";
                wp_mail($payer_email, $this->options['success_email_subject'], $body, $mail_headers);


                $mss = 'کاربر گرامي ، '.$payer_nextpay.' عمليات پرداخت با موفقيت به پايان رسيد .<br> لينک دانلود به آدرس ايميل '.$payer_email.' ارسال گرديد . <br><bt>جهت پيگيري هاي آتي شماره رسيد پرداخت خود را ياداشت فرماييد : '.$refNumber.'<br> با تشکر <br>'.PHP_EOL.get_bloginfo("name");

                if($this->options['showdownloadlink'] == "on")
                    $mss .= '<center><br><br> لینک دانلود به ایمیل شما ارسال شده است ، همچنین می توانید هم اکنون از طریق لینک زیر نسبت به دریافت فایل اقدام نمایید :<br><a target="_blank" href="'.$download_link.'"><img src="'.plugins_url('/images/download.png', __FILE__).'" ></a>';

                $messagePage = str_replace('$Message$',$mss,$messagePage);
                $messagePage = str_replace('$Style$',$style_succ,$messagePage);


                $body = '<div style="'.$style.'" >'.str_replace($tags, $vals, __('مدير گرامي !', 'nextpaypaiddownloads').PHP_EOL.PHP_EOL.__('به اطلاع مي رسانيم کاربري با نام " {name} " ({payer_email}) نسبت به خريد محصول {product_title} از طريق درگاه پرداخت نکست پی اقدام نموده است ، شماره رسيد پرداخت '.$refNumber.' و زمان پرداخت در سرور  {transaction_date} مي باشد . خريدار لينک دانلود را به شرح زير دريافت نموده است :', 'nextpaypaiddownloads').PHP_EOL.'{download_link}'.PHP_EOL.__('لينک فوق براي خريدار به مدت {download_link_lifetime} روز معتبر خواهد بود.', 'nextpaypaiddownloads').PHP_EOL.PHP_EOL.__('باتشکر,', 'nextpaypaiddownloads').PHP_EOL.'افزونه پرداخت نکست پی<br>www.nextpay.ir').'</div>';
                $body =  str_replace("\n", "<br>", $body);

                $mail_headers = "Content-Type: text/html; charset=utf-8\r\n";
                $mail_headers .= "From: ".$this->options['from_name']." <".$this->options['from_email'].">\r\n";
                $mail_headers .= "X-Mailer: PHP/".phpversion()."\r\n";
                wp_mail($this->options['seller_email'], __('رسيد تحويل سفارش موفق', 'nextpaypaiddownloads'), $body, $mail_headers);


			}
			else
			{



                    $mss = 'کاربر گرامي ، عمليات  اعتبار سنجي پرداخت شما با خطا مواجه گرديد .<br> درصورتي که پرداخت شما موفقيت آميز انجام شده باشد پس از بررسي اطلاعات سفارش براي شما ارسال خواهد شد . <br> با تشکر <br>'.PHP_EOL.get_bloginfo("name");
                    $messagePage = str_replace('$Message$',$mss,$messagePage);
                    $messagePage = str_replace('$Style$',$style_alrt,$messagePage);

                    $tags = array("{name}", "{payer_email}", "{product_title}", "{product_price}", "{product_currency}", "{payment_status}", "{transaction_date}");
                    $vals = array($payer_nextpay ,$payer_email, $file_details["title"], $postPrice, $mc_currency, $payment_status, date("Y-m-d H:i:s")." (server time)");

                    $body = '<div style="'.$style.'" >'.str_replace($tags, $vals, $this->options['failed_email_body']).'</div>';
                    $body =  str_replace("\n", "<br>", $body);

                    $mail_headers = "Content-Type: text/html; charset=utf-8\r\n";
                    $mail_headers .= "From: ".$this->options['from_name']." <".$this->options['from_email'].">\r\n";
                    $mail_headers .= "X-Mailer: PHP/".phpversion()."\r\n";
                    wp_mail($payer_email, $this->options['failed_email_subject'], $body, $mail_headers);

                    $body = '<div style="'.$style.'" >'.str_replace($tags, $vals, __('مدير گرامي !', 'nextpaypaiddownloads').PHP_EOL.PHP_EOL.__('کاربري با نام " {name} " ({payer_email}) نسبت به خريد محصول {product_title} اقدام نمود ، متاسفانه عمليت پرداخت را به صورت ناموفق به پايان رسانيد {transaction_date}. ', 'nextpaypaiddownloads').PHP_EOL.__('وضعيت پرداخت : {payment_status}', 'nextpaypaiddownloads').PHP_EOL.PHP_EOL.__('متاسفانه به دليل عدم تاييد پرداختي هيچ لينکي به کاربر تحويل نگرديد.', 'nextpaypaiddownloads').PHP_EOL.PHP_EOL.__('باتشکر,', 'nextpaypaiddownloads').PHP_EOL.'افزونه پرداخت نکست پی<br>www.nextpay.ir').'</div>';
                    $body =  str_replace("\n", "<br>", $body);

                    $mail_headers = "Content-Type: text/html; charset=utf-8\r\n";
                    $mail_headers .= "From: ".$this->options['from_name']." <".$this->options['from_email'].">\r\n";
                    $mail_headers .= "X-Mailer: PHP/".phpversion()."\r\n";
                    wp_mail($this->options['seller_email'], __('عمليات ناموفق پرداخت', 'nextpaypaiddownloads'), $body, $mail_headers);

            }
            }

            echo $messagePage;

			exit;
		 } else if (isset($_GET['nextpaypaiddownloads_connect'])) {

                $sql = "INSERT INTO ".$wpdb->prefix."pd_orders (
    				file_id, payer_name, payer_email,payer_phone, completed ) VALUES (
    				'".intval($_POST['ResNumber'])."',
    				'". esc_sql($_POST['Paymenter'])."',
    				'". esc_sql($_POST['Email'])."',
                    '". esc_sql($_POST['Mobile'])."',
    				'0'
    			)";
    			$wpdb->query($sql);
                $resNum = $wpdb->insert_id;


                $file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."pd_files WHERE id = '".intval(intval($_POST['ResNumber']))."'", ARRAY_A);

                $price =  intval($file_details['price']);

                if($file_details['currency'] == "ريال")
                    $price = $price / 10;

                $mess = '<div style="border: 1px solid;margin:auto;padding:15px 10px 15px 50px; width:600px;font-size:8pt; line-height:25px;font-family:tahoma; text-align:right; direction:rtl;color: #00529B;background-color: #BDE5F8">
                         درحال اتصال به درگاه پرداخت نکست پی ...
                        </div>';


				 $client = new SoapClient('http://api.nextpay.org/gateway/token.wsdl', array('encoding' => 'UTF-8'));
				$result = $client->TokenGenerator(
									array(
											'api_key'     => $this->options['nextpay_api_key'],
											'amount'         => $price,
											'order_id'    => $resNum,
											'callback_uri'    => get_bloginfo("wpurl").'/?nextpaypaiddownloads_ipn=nextpay&resNum='. $resNum,
										)
				);
				$result = $result->TokenGeneratorResult;
				$trans_id = $result->trans_id;
				//Redirect to URL You can do it also by creating a form
				$request_payment = 'http://api.nextpay.org/gateway/payment';
				if (intval($result->code) == -1) {
					header_remove();
					ob_clean();
					if (headers_sent()) {
					    echo "<script> location.replace(\"".$request_payment."/$trans_id"."\"); </script>";
					}
					else
					{
					    header('Location: '.$request_payment."/$trans_id");
					    exit(0);
					}
				} else {
					echo'ERR: '.$result->code;
				}
				
                exit;
         }

	}

	function front_header() {
		echo '
		<link href="http://fonts.googleapis.com/css?family=Oswald" rel="stylesheet" type="text/css" />
		<link rel="stylesheet" type="text/css" href="'.plugins_url('/css/style.css?ver='.PD_VERSION, __FILE__).'" media="screen" />';
	}

	function shortcode_handler($_atts) {
		global $post, $wpdb, $current_user;
		if ($this->check_settings() === true)
		{
			$id = intval($_atts["id"]);
			$return_url = "";
			if (!empty($_atts["return_url"])) {
				$return_url = $_atts["return_url"];
				if (!preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $return_url) || strlen($return_url) == 0) $return_url = "";
			}
			if (empty($return_url)) $return_url = 'http://'.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
			$file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix . "pd_files WHERE id = '".$id."'", ARRAY_A);
			if (intval($file_details["id"]) == 0) return "";
			if ($file_details["price"] == 0) return '<a href="'.get_bloginfo("wpurl").'/?nextpaypaiddownloads_id='.$file_details["id"].'">'.__('Download', 'nextpaypaiddownloads').' '.htmlspecialchars($file_details["title"]).'</a>';
			$sql = "SELECT COUNT(id) AS sales FROM ".$wpdb->prefix."pd_transactions WHERE file_id = '".$file_details["id"]."' AND (payment_status = '100' )";
			$sales = $wpdb->get_row($sql, ARRAY_A);
			if (intval($sales["sales"]) < $file_details["available_copies"] || $file_details["available_copies"] == 0)
			{
				if ($this->options['enable_interkassa'] == "on") {
					if ($file_details["currency"] != $this->options['interkassa_currency']) {

					} else $rate = 1;
				}
				if (!in_array($file_details["currency"], $this->nextpay_currency_list)) $this->options['enable_nextpay'] = "off";

    			$methods = 0;
				if ($this->options['enable_nextpay'] == "on") $methods++;
				if ($methods == 0) return 'Not Active Gateway !';

				$button = '';
				$terms = htmlspecialchars($this->options['terms'], ENT_QUOTES);
				$terms = str_replace("\n", "<br />", $terms);
				$terms = str_replace("\r", "", $terms);
				if (!empty($this->options['terms'])) {
					$terms_id = "t".rand(100,999).rand(100,999).rand(100,999);
					$button .= '
					<div id="'.$terms_id.'" style="display: none;">
						<div class="nextpaypaiddownloads_terms">'.$terms.'</div>
					</div>'.__('کليک جهت خريد کالا ، به منظور پذيرش', 'nextpaypaiddownloads').' <a href="#" onclick="jQuery(\'#'.$terms_id.'\').slideToggle(300); return false;">'.__('قوانين و مقررات', 'nextpaypaiddownloads').'</a> سايت مي باشد  .<br />';
				}
				$button_id = "b".md5(rand(100,999).microtime());
				$button .= '
				<script type="text/javascript">
					var active_'.$button_id.' = "'.($this->options['enable_nextpay'] == "on" ? 'nextpay_'.$button_id : '' ).'";
                    var opened_'.$button_id.' = false;
					function nextpaypaiddownloads_'.$button_id.'() {

						if (jQuery("#method_nextpay_'.$button_id.'").attr("checked")) active_'.$button_id.' = "nextpay_'.$button_id.'";
						if (active_'.$button_id.' == "nextpay_'.$button_id.'") {
                            if(!opened_'.$button_id.')
                            {
                                nextpaypaiddownloads_toggle_nextpaypaiddownloads_email_'.$button_id.'();
                                opened_'.$button_id.' = true;
                                return;
                            }' ;
                if($this->options['getphonenumber'] == "on")
                {
                $button .=  'if (jQuery("#nextpaypaiddownloads_phone_'.$button_id.'")) {
                                var nextpaypaiddownloads_phone = jQuery("#nextpaypaiddownloads_phone_'.$button_id.'").val();
                                var mo = /^-{0,1}\d*\.{0,1}\d+$/;
                                if(nextpaypaiddownloads_phone == "")
                                {
                                    alert("'.esc_attr(__('لطفا شماره تلفن همراه خود را جهت سهولت در پیگیری های آتی وارد نمایید', 'nextpaypaiddownloads')).'");
                                    jQuery("#nextpaypaiddownloads_phone_'.$button_id.'").focus();
                                  	return;
                                }
                                else if(nextpaypaiddownloads_phone.length != 11 || nextpaypaiddownloads_phone.indexOf("09") != 0 || !nextpaypaiddownloads_phone.match(mo))
                                {
    								alert("'.esc_attr(__('شماره تلفن همراه وارد شده صحیح نمی باشد', 'nextpaypaiddownloads')).'");
                                    jQuery("#nextpaypaiddownloads_phone_'.$button_id.'").focus();
                                   	return;
                                }
							}';
                }
				$button .=	'if (!jQuery("#nextpaypaiddownloads_email_'.$button_id.'")) {
								alert("'.esc_attr(__('لطفا آدرس پست الکترونيکي خود را به صورت صحيح وارد نماييد ، لينک دانلود به اين آدرس ارسال خواهد شد', 'nextpaypaiddownloads')).'");
                                jQuery("#nextpaypaiddownloads_email_'.$button_id.'").focus();
								return;
							}
							var nextpaypaiddownloads_email = jQuery("#nextpaypaiddownloads_email_'.$button_id.'").val();
							var re = /^[\w-]+(\.[\w-]+)*@([\w-]+\.)+[a-zA-Z]{2,7}$/;
							if (!nextpaypaiddownloads_email.match(re)) {
								alert("'.esc_attr(__('لطفا آدرس پست الکترونيکي خود را به صورت صحيح وارد نماييد ، لينک دانلود به اين آدرس ارسال خواهد شد', 'nextpaypaiddownloads')).'");
                                jQuery("#nextpaypaiddownloads_email_'.$button_id.'").focus();
								return;
							}

							jQuery("#nextpay_email_'.$button_id.'").val(nextpaypaiddownloads_email);
   							jQuery("#nextpay_payer_'.$button_id.'").val(jQuery("#nextpaypaiddownloads_payer_'.$button_id.'").val());';
                            if($this->options['getphonenumber'] == "on")
                            {
                                $button .=  'jQuery("#nextpay_phone_'.$button_id.'").val(jQuery("#nextpaypaiddownloads_phone_'.$button_id.'").val());';
                            }
				   $button .= '}
						jQuery("#" + active_'.$button_id.').click();
						return;
					}
					function nextpaypaiddownloads_toggle_nextpaypaiddownloads_email_'.$button_id.'() {
						if (jQuery("#nextpaypaiddownloads_email_container_'.$button_id.'")) {
							jQuery("#nextpaypaiddownloads_email_container_'.$button_id.'").slideDown(100);
						}
					}
				</script>';
				$checked = ' checked="checked"';

                $price = $rate*$file_details["price"]  ;
				if ($this->options['enable_nextpay'] == "on") {

                $button .= '
				<div id="nextpaypaiddownloads_email_container_'.$button_id.'" style="display: none; font-size:8pt">
                    جهت سفارش لطفا اطلاعات زير را تکميل نماييد : <br>

              <input type="text" id="nextpaypaiddownloads_payer_'.$button_id.'" style="font-family: tahoma, verdana; font-size: 14px; line-height: 14px; margin: 5px 0px;
                 padding: 3px 5px; background: #FFF; border: 1px solid #888; width: 200px; -webkit-border-radius: 3px; border-radius: 3px; color: #666; min-height:28px"
                  value="'.esc_attr(__('نام و نام خانوادگي', 'nextpaypaiddownloads')).'" onfocus="if (this.value == \''.esc_attr(__('نام و نام خانوادگي', 'nextpaypaiddownloads')).'\')
                   {this.value = \'\';}" onblur="if (this.value == \'\') {this.value = \''.esc_attr(__('نام و نام خانوادگي', 'nextpaypaiddownloads')).'\';}" />
                <br>';

              if($this->options['getphonenumber'] == "on")
              {
                $button .= '<input type="text" id="nextpaypaiddownloads_phone_'.$button_id.'" maxlength="11" style="font-family: tahoma, verdana; font-size: 14px; line-height: 14px; margin: 5px 0px;
                 padding: 3px 5px; background: #FFF; border: 1px solid #888; width: 200px; -webkit-border-radius: 3px; border-radius: 3px; color: #666; min-height:28px"
                  value="'.esc_attr(__('شماره تلفن همراه', 'nextpaypaiddownloads')).'" onfocus="if (this.value == \''.esc_attr(__('شماره تلفن همراه', 'nextpaypaiddownloads')).'\')
                   {this.value = \'\'; this.style.textAlign= \'left\'; this.style.direction= \'ltr\'}" onblur="if (this.value == \'\') {this.value = \''.esc_attr(__('شماره تلفن همراه', 'nextpaypaiddownloads')).'\'; this.style.textAlign= \'right\'; this.style.direction= \'rtl\'}" />
                <br>';
               }
                   $button .= '<input type="text" id="nextpaypaiddownloads_email_'.$button_id.'" style="font-family: tahoma, verdana; font-size: 14px; line-height: 14px; margin: 5px 0px;
                 padding: 3px 5px; background: #FFF; border: 1px solid #888; width: 200px; -webkit-border-radius: 3px; border-radius: 3px; color: #666; min-height:28px"
                  value="'.esc_attr(__('آدرس پست الکترونيکي', 'nextpaypaiddownloads')).'" onfocus="if (this.value == \''.esc_attr(__('آدرس پست الکترونيکي', 'nextpaypaiddownloads')).'\')
                   {this.value = \'\'; this.style.textAlign= \'left\'; this.style.direction= \'ltr\' }" onblur="if (this.value == \'\') {this.value = \''.esc_attr(__('آدرس پست الکترونيکي', 'nextpaypaiddownloads')).'\';  this.style.textAlign= \'right\'; this.style.direction= \'rtl\'}" />

                   </div>

				';
//                      
					$button .= '
				<form action="'.get_bloginfo("wpurl").'/?nextpaypaiddownloads_connect=nextpay" method="post" style="display:none;">
                   <input type="hidden" name="Description" value="سفارش '.htmlspecialchars($file_details["title"], ENT_QUOTES).'">
					<input type="hidden" name="ResNumber" value="'.$file_details["id"].'">
					<input type="hidden" name="Price" value="'.$file_details["price"].'">
					<input type="hidden" id="nextpay_payer_'.$button_id.'" name="Paymenter" value="">
   					<input type="hidden" id="nextpay_email_'.$button_id.'" name="Email" value="">
   					<input type="hidden" id="nextpay_phone_'.$button_id.'" name="Mobile" value="">
					<input type="hidden" name="ReturnPath" value="'.get_bloginfo("wpurl").'/?nextpaypaiddownloads_ipn=nextpay">
					<input id="nextpay_'.$button_id.'" type="submit" value="Buy Now" style="margin: 0px; padding: 0px;">
				</form>';
				}


				if ($this->options['buynow_type'] == "custom") $button .= '<input type="image" src="'.get_bloginfo("wpurl").'/wp-content/uploads/paid-downloads/'.rawurlencode($this->options['buynow_image']).'" name="submit" alt="'.htmlspecialchars($file_details["title"], ENT_QUOTES).'" style="margin: 5px 0px; padding: 0px; border: 0px;" onclick="nextpaypaiddownloads_'.$button_id.'(); return false;">';
				else if ($this->options['buynow_type'] == "nextpay") $button .= '<input type="image" src="'.plugins_url('/images/btn_buynow_LG.gif', __FILE__).'" name="submit" alt="'.htmlspecialchars($file_details["title"], ENT_QUOTES).'" style="margin: 5px 0px; padding: 0px; border: 0px;" onclick="nextpaypaiddownloads_'.$button_id.'(); return false;">';
				else if ($this->options['buynow_type'] == "css3") $button .= '
				<div style="border: 0px; margin: 5px 0px; padding: 0px; height: 100%; overflow: hidden;">
				<a href="#" class="nextpaypaiddownloads-btn" onclick="nextpaypaiddownloads_'.$button_id.'(); return false;">
                    <span class="nextpaypaiddownloads-btn-icon-right"><span></span></span>
					<span class="nextpaypaiddownloads-btn-slide-text">'.number_format($file_details["price"], 0, ".", "").' '.$file_details["currency"].'</span>
					<span class="nextpaypaiddownloads-btn-text">'.__('خريد', 'nextpaypaiddownloads').'</span>
				</a>
				</div>';
				else $button .= '<input type="button" value="'.__('خريد', 'nextpaypaiddownloads').'" style="margin: 5px 0px;; padding:2px; width:100px; font-family:tahoma" onclick="nextpaypaiddownloads_'.$button_id.'(); return false;">';
			}
			else $button = "-";
			return $button;
		}
		return "";
	}	

	function generate_downloadlink($_fileid, $_owner, $_source) {
		global $wpdb;
		$file_details = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."pd_files WHERE id = '".intval($_fileid)."'", ARRAY_A);
		if (intval($file_details["id"]) == 0) return false;
		$download_key = md5(microtime().rand(1,10000)).md5(microtime().$file_details["title"]);
		$sql = "INSERT INTO ".$wpdb->prefix."pd_downloadlinks (
			file_id, download_key, owner, source, created) VALUES (
			'".$_fileid."',
			'".$download_key."',
			'". esc_sql($_owner)."',
			'".$_source."',
			'".time()."'
		)";
		$wpdb->query($sql);
		return get_bloginfo("wpurl").'/?nextpaypaiddownloads_key='.$download_key;
	}

	function page_switcher ($_urlbase, $_currentpage, $_totalpages) {
		$pageswitcher = "";
		if ($_totalpages > 1) {
			$pageswitcher = '<div class="tablenav bottom"><div class="tablenav-pages">'.__('Pages:', 'nextpaypaiddownloads').' <span class="pagiation-links">';
			if (strpos($_urlbase,"?") !== false) $_urlbase .= "&amp;";
			else $_urlbase .= "?";
			if ($_currentpage == 1) $pageswitcher .= "<a class='page disabled'>1</a> ";
			else $pageswitcher .= " <a class='page' href='".$_urlbase."p=1'>1</a> ";

			$start = max($_currentpage-3, 2);
			$end = min(max($_currentpage+3,$start+6), $_totalpages-1);
			$start = max(min($start,$end-6), 2);
			if ($start > 2) $pageswitcher .= " <b>...</b> ";
			for ($i=$start; $i<=$end; $i++) {
				if ($_currentpage == $i) $pageswitcher .= " <a class='page disabled'>".$i."</a> ";
				else $pageswitcher .= " <a class='page' href='".$_urlbase."p=".$i."'>".$i."</a> ";
			}
			if ($end < $_totalpages-1) $pageswitcher .= " <b>...</b> ";

			if ($_currentpage == $_totalpages) $pageswitcher .= " <a class='page disabled'>".$_totalpages."</a> ";
			else $pageswitcher .= " <a class='page' href='".$_urlbase."p=".$_totalpages."'>".$_totalpages."</a> ";
			$pageswitcher .= "</span></div></div>";
		}
		return $pageswitcher;
	}
	
	function get_filename($_path, $_filename) {
		$filename = preg_replace('/[^a-zA-Z0-9\s\-\.\_]/', ' ', $_filename);
		$filename = preg_replace('/(\s\s)+/', ' ', $filename);
		$filename = trim($filename);
		$filename = preg_replace('/\s+/', '-', $filename);
		$filename = preg_replace('/\-+/', '-', $filename);
		if (strlen($filename) == 0) $filename = "file";
		else if ($filename[0] == ".") $filename = "file".$filename;
		while (file_exists($_path.$filename)) {
			$pos = strrpos($filename, ".");
			if ($pos !== false) {
				$ext = substr($filename, $pos);
				$filename = substr($filename, 0, $pos);
			} else {
				$ext = "";
			}
			$pos = strrpos($filename, "-");
			if ($pos !== false) {
				$suffix = substr($filename, $pos+1);
				if (is_numeric($suffix)) {
					$suffix++;
					$filename = substr($filename, 0, $pos)."-".$suffix.$ext;
				} else {
					$filename = $filename."-1".$ext;
				}
			} else {
				$filename = $filename."-1".$ext;
			}
		}
		return $filename;
	}

	function period_to_string($period) {
		$period_str = "";
		$days = floor($period/(24*3600));
		$period -= $days*24*3600;
		$hours = floor($period/3600);
		$period -= $hours*3600;
		$minutes = floor($period/60);
		if ($days > 1) $period_str = $days.' '.__('روز', 'nextpaypaiddownloads').' و ';
		else if ($days == 1) $period_str = $days.' '.__('روز', 'nextpaypaiddownloads').' و ';
		if ($hours > 1) $period_str .= $hours.' '.__('ساعت', 'nextpaypaiddownloads').' و ';
		else if ($hours == 1) $period_str .= $hours.' '.__('ساعت', 'nextpaypaiddownloads').' و ';
		else if (!empty($period_str)) $period_str .= '0 '.__('ساعت', 'nextpaypaiddownloads').' و ';
		if ($minutes > 1) $period_str .= $minutes.' '.__('دقيقه', 'nextpaypaiddownloads');
		else if ($minutes == 1) $period_str .= $minutes.' '.__('دقيقه', 'nextpaypaiddownloads');
		else $period_str .= '0 '.__('دقيقه', 'nextpaypaiddownloads');
		return $period_str;
	}

	function get_license_info($_url, $_postdata) {
		$uagent = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322)";
		$ch = curl_init($_url);
		curl_setopt($ch, CURLOPT_URL, $_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_ENCODING, "");
		curl_setopt($ch, CURLOPT_USERAGENT, $uagent);
		curl_setopt($ch, CURLOPT_TIMEOUT, 120);
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $_postdata);
		$content = curl_exec( $ch );
		$err     = curl_errno( $ch );
		$errmsg  = curl_error( $ch );
		$header  = curl_getinfo( $ch );
		curl_close( $ch );

		$header['errno']   = $err;
		$header['errmsg']  = $errmsg;
		$header['content'] = $content;
		return $header;
	}
	
	function get_currency_rate($_from, $_to) {
		$url = 'http://www.google.com/ig/calculator?hl=en&q=1'.$_from.'=?'.$_to;
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_ENCODING, "");
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322)');
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $_postdata);
		$data  = curl_exec( $ch );
		curl_close( $ch );
		preg_match("!rhs: \"(.*?)\s!si", $data, $rate);
		$rate = floatval($rate[1]);
		if ($rate <= 0) return false;
		return $rate;
	}
}
if (class_exists("nextpaypaiddownloadspro_class")) {
	add_action('admin_notices', 'nextpaypaiddownloads_warning');
} else {
	$nextpaypaiddownloads = new nextpaypaiddownloads_class();
}
function nextpaypaiddownloads_warning() {
	echo '
	<div class="updated"><p>'.__('لطفا افزونه <strong>Paid Downloads Pro</strong> را غير فعال نماييد .', 'nextpaypaiddownloads').'</p></div>';
}
?>
