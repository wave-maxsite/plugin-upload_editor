<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * MaxSite CMS
 * (c) http://max-3000.com/
 *
 * (c) http://wave-maxsite.github.io/
 */


# функция автоподключения плагина
function upload_editor_autoload()
{
	if (is_login() && mso_check_allow('admin_files'))
	{
		mso_hook_add('new_page', 'upload_editor_new_page');
		if (mso_segment(2) == 'page_new')
		{
			mso_hook_add('admin_content', 'upload_editor_js');
		}
	}
}


# функция выполняется при активации (вкл) плагина
function upload_editor_activate($args = array())
{
	mso_create_allow('upload_editor_upload', t('Админ-доступ к загрузкам upload_editor'));
	return $args;
}


# функция выполняется при деактивации (выкл) плагина
function upload_editor_deactivate($args = array())
{
	return $args;
}


# функция выполняется при деинсталяции плагина
function upload_editor_uninstall($args = array())
{
	mso_delete_option('plugin_upload_editor', 'plugins' ); // удалим созданные опции
	mso_remove_allow('upload_editor_upload'); // удалим созданные разрешения
	return $args;
}


# функция отрабатывающая миниопции плагина (function плагин_mso_options)
function upload_editor_mso_options()
{
	if ( !mso_check_allow('upload_editor_upload') )
	{
		echo t('Доступ запрещен');
		return;
	}

	# ключ, тип, ключи массива
	mso_admin_plugin_options('plugin_upload_editor', 'plugins',
		array(
			'uploads_temp_folder' => array(
							'type' => 'text',
							'name' => t('Каталог для временных загрузок'),
							'description' => t('Каталог, куда будут загружаться временные файлы, например, при создании новой страницы. В дальнейшем файлы будут переноситься или удаляться.'),
							'default' => 'tempfiles'
						),
			),
		t('Настройки плагина upload_editor'), // титул
		t('Укажите необходимые опции.')   // инфо
	);
}


# Подключаемся на page_new и выводим форму загрузки.
function upload_editor_js($out)
{
	global $MSO;
	$upload_div  = '';
	$current_dir = mso_get_option('plugin_upload_editor', 'plugins', Array('uploads_temp_folder' => 'tempfiles'));
	$current_dir = $current_dir['uploads_temp_folder'];
	$ajax_path   = getinfo('ajax') . base64_encode('plugins/upload_editor/upload-ajax.php');
	$update_path = getinfo('ajax') . base64_encode('admin/plugins/admin_page/all-files-update-ajax.php');

	// размер
	$resize_images   = (int) mso_get_option('resize_images', 'general', 600);
	// миниатюра
	$size_image_mini = (int) mso_get_option('size_image_mini', 'general', 150);
	// тип миниатюры
	$image_mini_type = mso_get_option('image_mini_type', 'general', 1);
	// водяной знак
	$use_watermark   = mso_get_option('use_watermark', 'general', 0);
	$watermark_type  = mso_get_option('watermark_type', 'general', 1);

	if ($resize_images < 1) $resize_images = 600;
	if ($size_image_mini < 1) $size_image_mini = 150;


	if (!function_exists('lightbox_head')) $lightbox = '';
	else
	{
		$url = getinfo('plugins_url') . 'lightbox/';
		$t_izob = t('Изображение');
		$t_iz = t('из');

		$lightbox = <<<EOF
var lburl = "{$url}images/";
$("a.lightbox").lightBox({
	imageLoading: lburl+"lightbox-ico-loading.gif",
	imageBtnClose: lburl+"lightbox-btn-close.gif",
	imageBtnPrev: lburl+"lightbox-btn-prev.gif",
	imageBtnNext: lburl+"lightbox-btn-next.gif",
	imageBlank: lburl+"lightbox-blank.gif",
	txtImage: "{$t_izob}",
	txtOf: "{$t_iz}",
});
EOF;
	}

	$upload_div = '
		<div class="all-files-nav">
			<a href="' . getinfo('site_admin_url') . 'files/' . $current_dir . '" target="_blank" class="goto-files">' . t('Управление файлами') . '</a>
			<a href="#" id="all-files-upload" class="all-files-upload">' . t('Загрузить файлы') . '</a>
		</div>

		<div id="all-files-upload-panel" style="display: none;">
			<div class="upload_file">
				<h2>' . t('Загрузка файлов') . '</h2>
				<p>' . t('Для загрузки файлов выставьте необходимые опции, нажмите кнопку «Обзор» и выберите один или несколько файлов.') . '.</p>
				<p><label><input type="checkbox" name="f_userfile_resize" checked="checked" value=""> ' . t('Для изображений изменить размер до') . '</label>
					<input type="text" name="f_userfile_resize_size" style="width: 50px" maxlength="4" value="' . $resize_images . '"> ' . t('px (по максимальной стороне).') . '</p>

				<p><label><input type="checkbox" name="f_userfile_mini" checked="checked" value=""> ' . t('Для изображений сделать миниатюру размером') . '</label>
					<input type="text" name="f_userfile_mini_size" style="width: 50px" maxlength="4" value="' . $size_image_mini . '"> ' . t('px (по максимальной стороне).') . ' <br><em>' . t('Примечание: миниатюра будет создана в каталоге') . ' <strong>uploads/' . $current_dir . '/mini</strong></em></p>

				<p>' . t('Миниатюру делать путем:') . ' <select name="f_mini_type">
				<option value="1"'.(($image_mini_type == 1)?(' selected="selected"'):('')).'>' . t('Пропорционального уменьшения') . '</option>
				<option value="2"'.(($image_mini_type == 2)?(' selected="selected"'):('')).'>' . t('Обрезки (crop) по центру') . '</option>
				<option value="3"'.(($image_mini_type == 3)?(' selected="selected"'):('')).'>' . t('Обрезки (crop) с левого верхнего края') . '</option>
				<option value="4"'.(($image_mini_type == 4)?(' selected="selected"'):('')).'>' . t('Обрезки (crop) с левого нижнего края') . '</option>
				<option value="5"'.(($image_mini_type == 5)?(' selected="selected"'):('')).'>' . t('Обрезки (crop) с правого верхнего края') . '</option>
				<option value="6"'.(($image_mini_type == 6)?(' selected="selected"'):('')).'>' . t('Обрезки (crop) с правого нижнего края') . '</option>
				<option value="7"'.(($image_mini_type == 7)?(' selected="selected"'):('')).'>' . t('Уменьшения и обрезки (crop) в квадрат') . '</option>
				</select>

				<p><label><input type="checkbox" name="f_userfile_water" value="" '
					. ((file_exists(getinfo('uploads_dir') . 'watermark.png')) ? '' : ' disabled="disabled"')
					. ($use_watermark ? (' checked="checked"') : (''))
					. '> ' . t('Для изображений установить водяной знак') . '</label>
					<br><em>' . t('Примечание: водяной знак должен быть файлом <strong>watermark.png</strong> и находиться в каталоге') . ' <strong>uploads</strong></em></p>

				<p>' . t('Водяной знак устанавливается:') . ' <select name="f_water_type">
				<option value="1"'.(($watermark_type == 1)?(' selected="selected"'):('')).'>' . t('По центру') . '</option>
				<option value="2"'.(($watermark_type == 2)?(' selected="selected"'):('')).'>' . t('В левом верхнем углу') . '</option>
				<option value="3"'.(($watermark_type == 3)?(' selected="selected"'):('')).'>' . t('В правом верхнем углу') . '</option>
				<option value="4"'.(($watermark_type == 4)?(' selected="selected"'):('')).'>' . t('В левом нижнем углу') . '</option>
				<option value="5"'.(($watermark_type == 5)?(' selected="selected"'):('')).'>' . t('В правом нижнем углу') . '</option>
				</select></p>

				<div class="attach unit">
					<span>
						<input id="attach_img" type="file" name="attach" data-url="" multiple>
						<div class="loader"><img src="'.getinfo('admin_url').'plugins/admin_page/images/loader.gif" width="16" height="11"></div>
						<div class="uploaded"></div>
						<div class="inserted"></div>              
					</span>
				</div>
			</div>
		</div>

		<div id="all-files-result" class="all-files-result">' . t('Загрузка...') . '</div>

		<script type="text/javascript">
			var sess = "' . $MSO->data['session']['session_id'] . '";
			var upload_path = "' . $ajax_path   . '",
				update_path = "' . $update_path . '",
				current_dir = "' . $current_dir . '";
		</script>
		<script src="'.getinfo('plugins_url').'upload_editor/upload/jquery.ui.widget.js"></script>
		<script src="'.getinfo('plugins_url').'upload_editor/upload/jquery.iframe-transport.js"></script>
		<script src="'.getinfo('plugins_url').'upload_editor/upload/jquery.fileupload.js"></script>
		<script src="'.getinfo('plugins_url').'upload_editor/upload/upload.js"></script>

<script>
	$(function(){
		$(".mso-tabs-box.all-files").text("");
		$(".mso-tabs-box.all-files").append($(".all-files-nav"));
		$(".mso-tabs-box.all-files").append($("#all-files-upload-panel"));
		$(".mso-tabs-box.all-files").append($("#all-files-result"));

		$.post(
			"' . getinfo('ajax') . base64_encode('admin/plugins/admin_page/all-files-update-ajax.php') . '",
			{
				dir: "' . $current_dir . '"
			},
			function(data)
			{
				$("#all-files-result").html(data);
				' . $lightbox . '
			}
		);

		$(function(){
			$.post(
				"' . getinfo('ajax') . base64_encode('admin/plugins/admin_page/all-files-update-ajax.php') . '",
				{
					dir: "' . $current_dir . '"
				},
				function(data)
				{
					$("#all-files-result").html(data);
					' . $lightbox . '
				}
			);

			$(window).on("storage", function(e) {
				var pageId = window.location.pathname.match(/\d+$/)[0],
					event = e.originalEvent;

				if (event.newValue === pageId) {
					$("#all-files-result").html("' . t('Обновление...') . '");

					$.post(
						"' . getinfo('ajax') . base64_encode('admin/plugins/admin_page/all-files-update-ajax.php') . '",
						{
							dir: "' . $current_dir . '"
						},
						function(data)
						{
							$("#all-files-result").html(data);
							' . $lightbox . '
							localStorage.clear();
						}
					);
				}
				return false;
			});

			$("#all-files-upload").click(function(event){
				$(".attach .loader").hide();
				$("#all-files-upload-panel").slideToggle();
				return false;
			});
		});
	});

	function addImgPage(img, t) {
		var e = $("input[name=\'f_options[image_for_page]\']");
		if ( e.length > 0 )
		{
			e.val(img);
			alert("' . t('Установлено:') . ' " + img);
		}
	}
</script>

';

$path = getinfo('uploads_dir') . $current_dir;

if (!is_dir($path) ) // нет каталога
{
	if (!is_dir(getinfo('uploads_dir') . '_pages') ) // нет _pages
	{
		@mkdir(getinfo('uploads_dir') . '_pages', 0777); // пробуем создать
	}

	// нет каталога, пробуем создать
	@mkdir($path, 0777);
	@mkdir($path . '/_mso_i', 0777);
	@mkdir($path . '/mini', 0777);
}

if (!is_dir($path)) // каталог не удалось создать
{
	$all_files = t('Не удалось создать каталог для файлов страницы');
	return $out . $all_files;
}

	return $out . $upload_div;
}


# Перемещаем файлы на постоянное место и меняем ссылки на них.
function upload_editor_new_page($arg = array())
{
	$current_dir  = mso_get_option('plugin_upload_editor', 'plugins', Array('uploads_temp_folder' => 'tempfiles'));
	$current_dir  = $current_dir['uploads_temp_folder'];
	$uploads_temp = $current_dir;

	$page_id      = $arg['0'];
	$current_dir  = '_pages' . '/' . $page_id;

	$uploads_temp_url    = getinfo('uploads_url') . $uploads_temp;
	$uploads_current_url = getinfo('uploads_url') . $current_dir;
	$uploads_temp_dir    = getinfo('uploads_dir') . $uploads_temp;
	$uploads_current_dir = getinfo('uploads_dir') . $current_dir;

	$CI = &get_instance();
	# По-простому контент мы получить не можем, придётся делать запрос в БД.
	$query = $CI->db->get_where('page', array('page_id' => $page_id), 1);
	if ($query->num_rows())
	{
		foreach ($query->result_array() as $row)
		{
			$page_content = $row['page_content'];
		}
	}

	# Если в тексте страницы есть ссылка на файл во временном каталоге, меняем на постоянный адрес и обновляем страницу в БД.
	if (strpos($page_content, $uploads_temp_url) !== false)
	{
		$page_content = str_replace ($uploads_temp_url, $uploads_current_url, $page_content);

		$CI->db->where(array('page_id'=>$page_id) );
		$res = ($CI->db->update('page', Array('page_content' => $page_content))) ? '1' : '0';
	}

	# Если в метаданных (обычно прикреплённой картинке) есть ссылка на файл…
	# По-простому мы получить это дело не можем, придётся делать ещё запрос в БД.
	$CI->db->select('*');
	$CI->db->where(Array('meta_id_obj' => $page_id, 'meta_table' => 'page'));
	$query = $CI->db->get('meta');
	if ($query->num_rows())
	{
		$meta = Array();
		$ids  = Array();
		foreach ($query->result_array() as $row)
		{
			if (strpos($row['meta_value'], $uploads_temp_url) !== false)
			{
				$row['meta_value'] = str_replace($uploads_temp_url, $uploads_current_url, $row['meta_value']);
				$meta[] = $row;
			}
		}
		if ($meta)
		{
			$CI->db->update_batch('meta', $meta, 'meta_id');
		}
	}

	if( !is_dir($uploads_current_dir) )
	{
		if( !is_dir(getinfo('uploads_dir').'_pages') )
		{
			@mkdir(getinfo('uploads_dir').'_pages', 0777);
		}

		@mkdir($uploads_current_dir, 0777);
		@mkdir($uploads_current_dir . '/_mso_i', 0777);
		@mkdir($uploads_current_dir . '/mini', 0777);
	}

	if( !is_dir($uploads_current_dir) )
	{
		# Не удалось создать каталог для файлов страницы.
		return;
	}

	$CI->load->helper('file');
	$tempfiles = get_filenames($uploads_temp_dir);

	global $MSO;
	$sessid = $MSO->data['session']['session_id'];

	foreach ($tempfiles as $file)
	{
		if (substr($file, strlen($file) - 32) == $sessid)
		{
			$file = substr($file, 0, strlen($file) - 33);

			# Если есть файлы, помеченные текущей сессией, то перемещаем их на постоянное место
			if( rename( $uploads_temp_dir . '/' . $file,  $uploads_current_dir . '/' . $file ) )
			{
				# Если получилось переместить файл, перемещаем его миниатюру
				if( file_exists($uploads_temp_dir . '/mini/' . $file) )
				{
				rename( $uploads_temp_dir . '/mini/' . $file, $uploads_current_dir . '/mini/' . $file );
				}

				# перемещаем иконку
				if( file_exists($uploads_temp_dir . '/_mso_i/' . $file) )
				{
				rename( $uploads_temp_dir . '/_mso_i/' . $file,  $uploads_current_dir . '/_mso_i/' . $file );
				}

				# удаляем файл сессии для аттача
				@unlink( $uploads_temp_dir . '/' . $file . '.' . $sessid );
			}

		}
	}
	# Если мы после создания страницы переходим на её редактирование, то удаление $sessid . '.sessid' уместно. Иначе бесполезно, но…
	@unlink($uploads_temp_dir . '/' . $sessid . '.sessid');
	return $arg;
}

# end file