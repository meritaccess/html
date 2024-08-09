<?php
// ========== begin WEBAPP KONFIGURACE
// zavedeni session
session_start();
// zakazani cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
// zakazani error reportu
error_reporting(0);
mysqli_report(MYSQLI_REPORT_OFF);
// url webu
$url = $_SERVER['REQUEST_URI'];
// blacklist slov v url
$wrong_words_url_array = ['meritaccess', '/', 'index.php', '?'];
// cisteni url
$clean_url = str_replace($wrong_words_url_array, '', $url);
// prevod aktualniho casu na UNIX format
$act_datetime_unix = strtotime(date('Y-m-d H:i:s'));
// hledany vyraz #0
$search_definition_0 = 'v=';
// pole pohledu
$stat_view_array = ['l' => 'lock', 'h' => 'home', 'a' => 'access', 'c' => 'cards', 't' => 'timezones', 'u' => 'users', 'o' => 'config', 'r' => 'running', 'm' => 'more', 'x' => 'drop', 'v' => 'logs'];
$stat_cond_array = ['e' => 'edit', 'p' => 'past', 'd' => 'delete', 'b' => 'block'];
$stat_info_array = ['o' => 'order'];
$stat_fact_array = ['z' => 'alert'];
// titulky v title
$title_view_array = ['lock' => 'Login', 'home' => 'Dashboard', 'access' => 'Access', 'config' => 'Configuration', 'cards' => 'Cards', 'running' => 'Running', 'timezones' => 'Timezones', 'users' => 'Users', 'more' => 'More', 'logs' => 'Logs'];
// pole vyloucenych, ktere musi mit login
$keys_url_array_except = [$stat_view_array['h'], $stat_view_array['a'], $stat_view_array['o'], $stat_view_array['c'], $stat_view_array['r'], $stat_view_array['t'], $stat_view_array['u'], $stat_view_array['d'], $stat_view_array['m'], $stat_view_array['v']];
// pokud chybi v url, doplni
if(!strpos($url, $search_definition_0) !== false) {
	if(!isset($_SESSION['access_granted'])) {
		header('location: ./?v=lock');
	} else {
		header('location: ./?v=home');
	}
}
// roztrhani url
$temp_url = explode(';', $clean_url);
// parsovani url
foreach($temp_url as $pair_url) {
	list($value_url, $key_url) = explode('=', $pair_url);
	$part_url[$value_url] = $key_url;
}
// klic pohledu
$key_view = strval($part_url['v'] ?? "");
$key_cond = strval($part_url['s'] ?? "");
$key_info = strval($part_url['i'] ?? "");
$key_fact = strval($part_url['f'] ?? "");
// presmerovani pokud v adrese jsou jine stavy nez definovane
if(!in_array($key_view, $stat_view_array)) {
	if(!isset($_SESSION['access_granted'])) {
		header('location: ./?v=lock');
	} else {
		header('location: ./?v=home');
	}
}
// presmerovani pokud neni uzivatel prihlasen
if(!isset($_SESSION['access_granted'])) {
	if(in_array($key_view, $keys_url_array_except)) {
		header('location: ./?v=lock');
	}
}
// funkce pro verzovani
function fileVersioning($tracked_file_input) {
	$datetime_update = date('ymdHi', filemtime($tracked_file_input));
	$tracked_file_output = $tracked_file_input . '?v=' . $datetime_update;
	return $tracked_file_output;
}
// datum aktualizace - index
$update_datetime = filemtime(__FILE__);
// ========== end WEBAPP KONFIGURACE

// ========== begin SQL PRIPOJENI
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'ma');
define('DB_PASSWORD', 'FrameWork5414*');
define('DB_NAME', 'MeritAccessLocal');
$connection = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
// pokud nefunguje spojeni - chyba
if($connection === false) {
	die('CONNECTION ERROR! ' . mysqli_connect_error());
}
// ========== end SQL PRIPOJENI
// ========== begin PRIHLASOVACI PROCES
if(isset($_POST['login'])) {
	$input_username = $_POST['username'];
	$input_password = $_POST['password'];
// pripravene dotazy pro ochranu proti sql injection
	$stmt = $connection->prepare("SELECT * FROM users WHERE LogonName = ? AND MD5 = MD5(?) AND rights = 255");
	$stmt->bind_param("ss", $input_username, $input_password);
	$stmt->execute();
	$result = $stmt->get_result();
	if($result->num_rows == 1) {
		$_SESSION['access_granted'] = true;
		header('Location: ./?v=home');
		exit;
	} else {
// kontrola pro neplatne prava
		$stmt_rights = $connection->prepare("SELECT rights FROM users WHERE LogonName = ?");
		$stmt_rights->bind_param("s", $input_username);
		$stmt_rights->execute();
		$result_rights = $stmt_rights->get_result();
		if($result_rights->num_rows == 1) {
			$row_rights = $result_rights->fetch_assoc();
			if($row_rights['rights'] != 255) {
				$_SESSION['error_message'] = 'User does not have sufficient rights!';
				header('Location: ./?v=lock;f=alert');
				exit;
			} else {
				$_SESSION['error_message'] = 'Wrong username or password!';
				header('Location: ./?v=lock;f=alert');
				exit;
			}
		} else {
			$_SESSION['error_message'] = 'Error getting user rights!';
			header('Location: ./?v=lock;f=alert');
			exit;
		}
	}
}
// odhlaseni
if($key_view == $stat_view_array['x']) {
	session_destroy();
	header('Location: ./?v=lock');
	exit;
}
// ========== end PRIHLASOVACI PROCES
// ========== begin CONFIGDU
// editace konfigurace
if($key_view == $stat_view_array['o'] && $key_cond == $stat_cond_array['e']) {
	$config_data = array();
	$config_data_temp = array();
// editace zaznamu konfigurace
	if(isset($_POST['edit-config'])) {
// pokud existuje session, presune jej do temp_session
		if(!isset($_SESSION['config_data_temp']) && isset($_SESSION['config_data'])) {
			$_SESSION['config_data_temp'] = $_SESSION['config_data'];
		}
		$id = $_POST['id'];
		$edit_property = $_POST['edit-property'];
		$edit_value = $_POST['edit-value'];
		$configdu_valid = $_POST['configdu-valid'];
		if(!preg_match('/' . $configdu_valid . '/', $edit_value)) {
			$_SESSION['error_message'] = 'Error! Invalid format for property: ' . $edit_property . '!';
			header('Location: ./?v=config;f=alert');
			exit;
		} else {
// najde index daneho id v poli session
			$index = array_search($id, array_column($_SESSION['config_data_temp'], 'id'));
// pokud index byl nalezen
			if($index !== false) {
// aktualizuje data v session
				$_SESSION['config_data_temp'][$index]['property'] = $edit_property;
				$_SESSION['config_data_temp'][$index]['value'] = $edit_value;
			} else {
// pokud index nebyl nalezen, prida novy zaznam
				$_SESSION['config_data_temp'][] = array('id' => $id, 'property' => $edit_property, 'value' => $edit_value);
			}
			header('Location: ./?v=config');
			exit;
		}
	}
}
// editace konfigurace - ulozeni
if($key_view == $stat_view_array['o'] && $key_cond == $stat_cond_array['p']) {
	$sqlquery_edit = "UPDATE ConfigDU SET property = ?, value = ? WHERE id = ?";
	$stmt = mysqli_prepare($connection, $sqlquery_edit);
	if($stmt === false) {
		$_SESSION['error_message'] = 'Error in preparing SQL: ' . mysqli_error($connection) . '!';
		header('Location: ./?v=config;f=alert');
		exit;
	}
// projde vsechny zaznamy v session
	if(isset($_SESSION['config_data_temp'])) {
		foreach($_SESSION['config_data_temp'] as $data) {
			$id = $data['id'];
			$edit_property = $data['property'];
			$edit_value = $data['value'];
// priprava zaznamu
			mysqli_stmt_bind_param($stmt, "ssi", $edit_property, $edit_value, $id);
			$result_edit = mysqli_stmt_execute($stmt);
// pokud doslo k chybe, vypise ji
			if(!$result_edit) {
				$_SESSION['error_message'] = 'Error updating database: ' . mysqli_error($connection) . '!';
				header('Location: ./?v=config;f=alert');
				exit;
			}
		}
		unset($_SESSION['config_data_temp']);
	}
// aktualizace running
	$sqlquery_update_running = "UPDATE running SET value = 1 WHERE property = 'restart' AND value = 0";
	$result_update_running = mysqli_query($connection, $sqlquery_update_running);
	if(!$result_update_running) {
		$_SESSION['error_message'] = 'Error updating running table: ' . mysqli_error($connection) . '!';
		header('Location: ./?v=config;f=alert');
		exit;
	}
// presmerovani
	header('Location: ./?v=config');
	exit;
}
// ========== end CONFIGDU
// ========== begin CARDS
// novy zaznam karty
if($key_view == $stat_view_array['c'] && $key_cond == $stat_cond_array['p'] && $key_cond != $stat_cond_array['e'] && $key_cond != $stat_cond_array['d']) {
// ulozeni dat karty do db
	if(isset($_POST['pridat-karta'])) {
// ziskani dat z post
		$nova_karta = $_POST['nova-karta'];
		$nova_ctecka = $_POST['nova-ctecka'];
		$novy_cas_plan = $_POST['novy-cas-plan'];
		$nove_povoleni = $_POST['nove-povoleni'];
		$nove_smazano = $_POST['nove-smazano'];
		$nove_pozn = $_POST['nove-pozn'];
// priprava sql dotazu
		$sqlquery_novy_zaznam = "INSERT INTO Karty (Karta, Ctecka, CasPlan, Povoleni, Smazano, Pozn) VALUES (?, ?, ?, ?, ?, ?)";
		$stmt = mysqli_prepare($connection, $sqlquery_novy_zaznam);
		if(!$stmt) {
// chyba pri priprave dotazu
			$_SESSION['error_message'] = 'Error preparing query: ' . mysqli_error($connection);
			header('Location: ./?v=cards&f=alert');
			exit;
		}
// bindovani parametru
		mysqli_stmt_bind_param($stmt, "ssssss", $nova_karta, $nova_ctecka, $novy_cas_plan, $nove_povoleni, $nove_smazano, $nove_pozn);
// vykonani dotazu
		if(mysqli_stmt_execute($stmt)) {
			header('Location: ./?v=cards');
			exit;
		} else {
// chyba pri vykonani dotazu
			$_SESSION['error_message'] = 'Error creating record: ' . mysqli_stmt_error($stmt);
			header('Location: ./?v=cards&f=alert');
			exit;
		}
// uzavreni statementu
		mysqli_stmt_close($stmt);
	}
}
// editace karty
if($key_view == $stat_view_array['c'] && $key_cond != $stat_cond_array['p'] && $key_cond == $stat_cond_array['e'] && $key_cond != $stat_cond_array['d']) {
// nacteni dat karty do formulare
	$cardid = $key_info;
	$sqlquery_select = "SELECT * FROM Karty WHERE cardid = ?";
	$stmt = mysqli_prepare($connection, $sqlquery_select);
	mysqli_stmt_bind_param($stmt, "i", $cardid);
	if(mysqli_stmt_execute($stmt)) {
		$result_select = mysqli_stmt_get_result($stmt);
		if(mysqli_num_rows($result_select) > 0) {
			$row_select = mysqli_fetch_array($result_select);
		} else {
			$_SESSION['error_message'] = 'Record not found!';
			header('Location: ./?v=cards;f=alert');
			exit;
		}
	} else {
		$_SESSION['error_message'] = 'Error executing the query: ' . mysqli_error($connection) . '!';
		header('Location: ./?v=cards;f=alert');
		exit;
	}
// editace zaznamu karty do db
	if(isset($_POST['edit-zaznam'])) {
		$cardid = $_POST['cardid'];
		$edit_karta = $_POST['edit-karta'];
		$edit_ctecka = $_POST['edit-ctecka'];
		$edit_cas_plan = $_POST['edit-cas-plan'];
		$edit_povoleni = $_POST['edit-povoleni'];
		$edit_smazano = $_POST['edit-smazano'];
		$edit_pozn = $_POST['edit-pozn'];
		$sqlquery_edit = "UPDATE Karty SET Karta = ?, Ctecka = ?, CasPlan = ?, Povoleni = ?, Smazano = ?, Pozn = ? WHERE cardid = ?";
		$stmt = mysqli_prepare($connection, $sqlquery_edit);
		if(!$stmt) {
			$_SESSION['error_message'] = 'Error preparing query: ' . mysqli_error($connection);
			header('Location: ./?v=cards&f=alert');
			exit;
		}
		mysqli_stmt_bind_param($stmt, "ssssssi", $edit_karta, $edit_ctecka, $edit_cas_plan, $edit_povoleni, $edit_smazano, $edit_pozn, $cardid);
		if(mysqli_stmt_execute($stmt)) {
			header('Location: ./?v=cards');
			exit;
		} else {
			$_SESSION['error_message'] = 'Error updating record: ' . mysqli_error($connection) . '!';
			header('Location: ./?v=cards&f=alert');
			exit;
		}
		mysqli_stmt_close($stmt);
	}
}
// mazani karty
if($key_view == $stat_view_array['c'] && $key_cond != $stat_cond_array['p'] && $key_cond != $stat_cond_array['e'] && $key_cond == $stat_cond_array['d']) {
// smazani zaznamu karty
	$cardid = $key_info;
	$sqlquery_delete = "UPDATE Karty SET Smazano = 1 WHERE cardid = ?";
	$stmt = mysqli_prepare($connection, $sqlquery_delete);
	mysqli_stmt_bind_param($stmt, "i", $cardid);
	if(mysqli_stmt_execute($stmt)) {
		header('Location: ./?v=cards');
		exit;
	} else {
		$_SESSION['error_message'] = 'Error deleting record: ' . mysqli_error($connection) . '!';
		header('Location: ./?v=cards&f=alert');
		exit;
	}
	mysqli_stmt_close($stmt);
}
// ========== end CARDS
// ========== begin TIMEZONES
// funkce pro pripravu a provedeni dotazu
function execute_query($connection, $query, $params, $types) {
	$stmt = mysqli_prepare($connection, $query);
	if(!$stmt) {
		$_SESSION['error_message'] = 'Error preparing query: ' . mysqli_error($connection);
		header('location: ./?v=timezones&f=alert');
		exit;
	}
	mysqli_stmt_bind_param($stmt, $types, ...$params);
	if(mysqli_stmt_execute($stmt)) {
		return $stmt;
	} else {
		$_SESSION['error_message'] = 'Error executing query: ' . mysqli_stmt_error($stmt);
		header('location: ./?v=timezones&f=alert');
		exit;
	}
}
// funkce pro ziskani pouzitych cisel zony
function get_used_zone_numbers($connection) {
	$query = "SELECT Cislo FROM CasovePlany";
	$result = mysqli_query($connection, $query);
	$used_numbers = [];
	while($row = mysqli_fetch_assoc($result)) {
		$used_numbers[] = $row['Cislo'];
	}
	return $used_numbers;
}
// funkce pro ziskani volnych cisel zony
function get_free_zone_numbers($connection) {
	$used_numbers = get_used_zone_numbers($connection);
	$all_numbers = range(1, 62);
	$free_numbers = array_diff($all_numbers, $used_numbers);
	return $free_numbers;
}
// funkce pro ziskani nazvu zony
function get_zone_name_by_number($connection, $zone_number) {
	$query = "SELECT Nazev FROM CasovePlany WHERE Cislo = ?";
	$stmt = mysqli_prepare($connection, $query);
	mysqli_stmt_bind_param($stmt, 'i', $zone_number);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_bind_result($stmt, $zone_name);
	mysqli_stmt_fetch($stmt);
	mysqli_stmt_close($stmt);
	return $zone_name;
}
// novy zaznam casovezony
if($key_view == $stat_view_array['t'] && $key_cond == $stat_cond_array['p'] && $key_cond != $stat_cond_array['e'] && $key_cond != $stat_cond_array['d']) {
	if(isset($_POST['nova-zona'])) {
// ziskani dat z POST
		$nove_cislo = mysqli_real_escape_string($connection, $_POST['nove-cislo']);
		$novy_nazev = mysqli_real_escape_string($connection, $_POST['novy-nazev']);
		$novy_popis = mysqli_real_escape_string($connection, $_POST['novy-popis']);
		$novy_stav = mysqli_real_escape_string($connection, $_POST['novy-stav']);
		$times = ['mo' => [$_POST['mostart1v'], $_POST['moend1v'], $_POST['mostart2v'], $_POST['moend2v']], 'tu' => [$_POST['tustart1v'], $_POST['tuend1v'], $_POST['tustart2v'], $_POST['tuend2v']], 'we' => [$_POST['westart1v'], $_POST['weend1v'], $_POST['westart2v'], $_POST['weend2v']], 'th' => [$_POST['thstart1v'], $_POST['thend1v'], $_POST['thstart2v'], $_POST['thend2v']], 'fr' => [$_POST['frstart1v'], $_POST['frend1v'], $_POST['frstart2v'], $_POST['frend2v']], 'sa' => [$_POST['sastart1v'], $_POST['saend1v'], $_POST['sastart2v'], $_POST['saend2v']], 'su' => [$_POST['sustart1v'], $_POST['suend1v'], $_POST['sustart2v'], $_POST['suend2v']], 'ho' => [$_POST['hostart1v'], $_POST['hoend1v'], $_POST['hostart2v'], $_POST['hoend2v']]];
		$times_flat = [];
		foreach($times as $day_times) {
			foreach($day_times as $time) {
				$times_flat[] = mysqli_real_escape_string($connection, $time);
			}
		}
// priprava SQL dotazu
		$sqlquery_novy_zaznam = "INSERT INTO CasovePlany (Cislo, Nazev, Popis, RezimOtevirani, Po_PrvniZacatek, Po_PrvniKonec, Po_DruhyZacatek, Po_DruhyKonec, Ut_PrvniZacatek, Ut_PrvniKonec, Ut_DruhyZacatek, Ut_DruhyKonec, St_PrvniZacatek, St_PrvniKonec, St_DruhyZacatek, St_DruhyKonec, Ct_PrvniZacatek, Ct_PrvniKonec, Ct_DruhyZacatek, Ct_DruhyKonec, Pa_PrvniZacatek, Pa_PrvniKonec, Pa_DruhyZacatek, Pa_DruhyKonec, So_PrvniZacatek, So_PrvniKonec, So_DruhyZacatek, So_DruhyKonec, Ne_PrvniZacatek, Ne_PrvniKonec, Ne_DruhyZacatek, Ne_DruhyKonec, Svatky_PrvniZacatek, Svatky_PrvniKonec, Svatky_DruhyZacatek, Svatky_DruhyKonec) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
		$params = array_merge([$nove_cislo, $novy_nazev, $novy_popis, $novy_stav], $times_flat);
		$types = str_repeat('s', count($params));
		execute_query($connection, $sqlquery_novy_zaznam, $params, $types);
		header('location: ./?v=timezones');
		exit;
	}
}
if ($key_view == $stat_view_array['t'] && $key_cond != $stat_cond_array['p'] && $key_cond == $stat_cond_array['e'] && $key_cond != $stat_cond_array['d']) {
// nacteni dat casovezony do formulare
	$tzid = mysqli_real_escape_string($connection, $key_info);
	$sqlquery_select = "SELECT * FROM CasovePlany WHERE Id_CasovyPlan = ?";
	$stmt = execute_query($connection, $sqlquery_select, [$tzid], 'i');
	$result_select = mysqli_stmt_get_result($stmt);
	if(mysqli_num_rows($result_select) > 0) {
		$row_select = mysqli_fetch_array($result_select);
	} else {
		$_SESSION['error_message'] = 'Record not found!';
		header('location: ./?v=timezones;f=alert');
		exit;
	}
	if(isset($_POST['edit-zona'])) {
// ziskani dat z POST
		$id = mysqli_real_escape_string($connection, $_POST['id']);
		$edit_cislo = mysqli_real_escape_string($connection, $_POST['edit-cislo']);
		$edit_nazev = mysqli_real_escape_string($connection, $_POST['edit-nazev']);
		$edit_popis = mysqli_real_escape_string($connection, $_POST['edit-popis']);
		$edit_stav = mysqli_real_escape_string($connection, $_POST['edit-stav']);
		$times = ['mo' => [$_POST['mostart1v'], $_POST['moend1v'], $_POST['mostart2v'], $_POST['moend2v']], 'tu' => [$_POST['tustart1v'], $_POST['tuend1v'], $_POST['tustart2v'], $_POST['tuend2v']], 'we' => [$_POST['westart1v'], $_POST['weend1v'], $_POST['westart2v'], $_POST['weend2v']], 'th' => [$_POST['thstart1v'], $_POST['thend1v'], $_POST['thstart2v'], $_POST['thend2v']], 'fr' => [$_POST['frstart1v'], $_POST['frend1v'], $_POST['frstart2v'], $_POST['frend2v']], 'sa' => [$_POST['sastart1v'], $_POST['saend1v'], $_POST['sastart2v'], $_POST['saend2v']], 'su' => [$_POST['sustart1v'], $_POST['suend1v'], $_POST['sustart2v'], $_POST['suend2v']], 'ho' => [$_POST['hostart1v'], $_POST['hoend1v'], $_POST['hostart2v'], $_POST['hoend2v']]];
		$times_flat = [];
		foreach($times as $day_times) {
			foreach($day_times as $time) {
				$times_flat[] = mysqli_real_escape_string($connection, $time);
			}
		}
// priprava SQL dotazu
		$sqlquery_edit_zaznam = "UPDATE CasovePlany SET Cislo = ?, Nazev = ?, Popis = ?, RezimOtevirani = ?, Po_PrvniZacatek = ?, Po_PrvniKonec = ?, Po_DruhyZacatek = ?, Po_DruhyKonec = ?, Ut_PrvniZacatek = ?, Ut_PrvniKonec = ?, Ut_DruhyZacatek = ?, Ut_DruhyKonec = ?, St_PrvniZacatek = ?, St_PrvniKonec = ?, St_DruhyZacatek = ?, St_DruhyKonec = ?, Ct_PrvniZacatek = ?, Ct_PrvniKonec = ?, Ct_DruhyZacatek = ?, Ct_DruhyKonec = ?, Pa_PrvniZacatek = ?, Pa_PrvniKonec = ?, Pa_DruhyZacatek = ?, Pa_DruhyKonec = ?, So_PrvniZacatek = ?, So_PrvniKonec = ?, So_DruhyZacatek = ?, So_DruhyKonec = ?, Ne_PrvniZacatek = ?, Ne_PrvniKonec = ?, Ne_DruhyZacatek = ?, Ne_DruhyKonec = ?, Svatky_PrvniZacatek = ?, Svatky_PrvniKonec = ?, Svatky_DruhyZacatek = ?, Svatky_DruhyKonec = ? WHERE Id_CasovyPlan = ?";
		$params = array_merge([$edit_cislo, $edit_nazev, $edit_popis, $edit_stav], $times_flat, [$id]);
		$types = str_repeat('s', count($params) - 1) . 'i';
		execute_query($connection, $sqlquery_edit_zaznam, $params, $types);
		header('location: ./?v=timezones');
		exit;
	}
}
// ========== end TIMEZONES
// ========== begin USERS
// prace s uzivatelem
if($key_view == $stat_view_array['u'] && $key_cond == $stat_cond_array['p'] && $key_cond != $stat_cond_array['e'] && $key_cond != $stat_cond_array['d']) {
// novy zaznam uzivatele
	if(isset($_POST['novy-uzivatel'])) {
		$novy_user = $_POST['novy-user'];
		$plain_password = $_POST['novy-md5'];
		$novy_md5 = md5($plain_password);
		$nova_prava = $_POST['nova-prava'];
		$sqlquery_novy_zaznam = "INSERT INTO users (LogonName, MD5, rights) VALUES (?, ?, ?)";
		$stmt = mysqli_prepare($connection, $sqlquery_novy_zaznam);
		if($stmt) {
			mysqli_stmt_bind_param($stmt, "sss", $novy_user, $novy_md5, $nova_prava);
			if(mysqli_stmt_execute($stmt)) {
				header('Location: ./?v=users');
				exit;
			} else {
				$_SESSION['error_message'] = 'Error creating user: ' . mysqli_error($connection) . '!';
				header('Location: ./?v=users;f=alert');
				exit;
			}
		} else {
			$_SESSION['error_message'] = 'Error preparing the statement: ' . mysqli_error($connection) . '!';
			header('Location: ./?v=users;f=alert');
			exit;
		}
	}
}
// editace uzivatele
if($key_view == $stat_view_array['u'] && $key_cond != $stat_cond_array['p'] && $key_cond == $stat_cond_array['e'] && $key_cond != $stat_cond_array['d']) {
// nacteni dat uzivatele do formulare
	$id = $key_info;
	$sqlquery_select = "SELECT * FROM users WHERE id = ?";
	$stmt = mysqli_prepare($connection, $sqlquery_select);
	if($stmt) {
		mysqli_stmt_bind_param($stmt, "i", $id);
		if(mysqli_stmt_execute($stmt)) {
			$result_select = mysqli_stmt_get_result($stmt);
			if(mysqli_num_rows($result_select) > 0) {
				$row_select = mysqli_fetch_array($result_select);
			} else {
				$_SESSION['error_message'] = 'User not found!';
				header('Location: ./?v=users;f=alert');
				exit;
			}
		} else {
			$_SESSION['error_message'] = 'Error executing the query: ' . mysqli_error($connection) . '!';
			header('Location: ./?v=users;f=alert');
			exit;
		}
	} else {
		$_SESSION['error_message'] = 'Error preparing the statement: ' . mysqli_error($connection) . '!';
		header('Location: ./?v=users;f=alert');
		exit;
	}
// editace zaznamu uzivatele
	if(isset($_POST['edit-uzivatel'])) {
		$id = $_POST['id'];
		$edit_user = $_POST['edit-user'];
		$edit_plain_password = $_POST['edit-md5'];
		$edit_md5 = md5($edit_plain_password);
		$edit_prava = $_POST['edit-prava'];
		$sqlquery_edit = "UPDATE users SET LogonName = ?, MD5 = ?, rights = ? WHERE id = ?";
		$stmt = mysqli_prepare($connection, $sqlquery_edit);
		if($stmt) {
			mysqli_stmt_bind_param($stmt, "sssi", $edit_user, $edit_md5, $edit_prava, $id);
			if(mysqli_stmt_execute($stmt)) {
				header('Location: ./?v=users');
				exit;
			} else {
				$_SESSION['error_message'] = 'Error updating record: ' . mysqli_error($connection) . '!';
				header('Location: ./?v=users;f=alert');
				exit;
			}
		} else {
			$_SESSION['error_message'] = 'Error preparing the statement: ' . mysqli_error($connection) . '!';
			header('Location: ./?v=users;f=alert');
			exit;
		}
	}
}
// mazani uzivatele
if($key_view == $stat_view_array['u'] && $key_cond != $stat_cond_array['p'] && $key_cond != $stat_cond_array['e'] && $key_cond == $stat_cond_array['d']) {
// smazani zaznamu uzivatele
	$id = $key_info;
	$sqlquery_delete = "UPDATE users SET rights = 0 WHERE id = ?";
	$stmt = mysqli_prepare($connection, $sqlquery_delete);
	if($stmt) {
		mysqli_stmt_bind_param($stmt, "i", $id);
		if(mysqli_stmt_execute($stmt)) {
			header('Location: ./?v=users');
			exit;
		} else {
			$_SESSION['error_message'] = 'Error blocking user: ' . mysqli_error($connection) . '!';
			header('Location: ./?v=users;f=alert');
			exit;
		}
	} else {
		$_SESSION['error_message'] = 'Error preparing the statement: ' . mysqli_error($connection) . '!';
		header('Location: ./?v=users;f=alert');
		exit;
	}
}
// ========== end USERS
?><!DOCTYPE html>
<html lang="cs">
<head>
	<title><?php
echo $title_view_array[$key_view];
?> &ndash; MeritAccess</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
	<meta name="meritaccess-gui" content="v=<?php
echo date('ymdHi', getlastmod());
?>">
	<meta http-equiv="x-ua-compatible" content="ie=edge">
	<link rel="stylesheet" type="text/css" href="<?php
echo fileVersioning('./css/global.css');
?>" media="screen">
	<link rel="stylesheet" type="text/css" href="<?php
echo fileVersioning('./css/style.css');
?>" media="screen">
<script src="./lib/jquery-3.7.1.min.js"></script>
<script src="./lib/jquery-ui.min.js"></script>
<link rel="stylesheet" type="text/css" href="./lib/jquery-ui.min.css" media="screen">
<script src="./lib/datatables.min.js"></script>
<script src="./lib/dataTables.buttons.min.js"></script>
<script src="./js/meritaccess-head.min.js"></script>
</head>
<body id="meritaccess">
<?php
if($key_fact == $stat_fact_array['z']) {
?>
<div id="error-info">
	<p><?php
// chybove hlasky
	echo $_SESSION['error_message'];
?></p>
</div>
<?php
}
?>
<div id="main">
	<div id="header">
		<h1>MeritAccess</h1>
		<p id="logo"><a href="./">MeritAccess</a></p>
<?php
// jiny pohled nez login
if($key_view != $stat_view_array['l']) {
?>
		<ul id="header-nav">
<?php
	if($key_view == $stat_view_array['c']) {
?>
			<li id="add-card"><a href="./?v=cards;s=past">Card</a></li>
<?php
	}
	if($key_view == $stat_view_array['u']) {
?>
			<li id="add-user"><a href="./?v=users;s=past">User</a></li>
<?php
	}
	if($key_view == $stat_view_array['t']) {
?>
			<li id="add-timezone"><a href="./?v=timezones;s=past">Timezone</a></li>
<?php
	}
	if($key_view == $stat_view_array['o']) {
		if(isset($_SESSION['config_data_temp'])) {
?>
			<li id="save"><a href="./?v=config;s=past" onclick="return confirm('Are you sure you want to commit all changes?');">Confirm</a></li>
<?php
		}
	}
?>
			<li id="logout"><a href="./?v=drop">Logout</a></li>
		</ul>
<?php
}
?>
	</div>
	<div id="content">
<?php
// ========== begin POHLED LOGIN
if($key_view == $stat_view_array['l']) {
?>
		<h2>Login</h2>
		<p>Enter your login details, username and password.</p>
		<div id="form-container">
			<form action="<?php
echo $url;
?>" method="post" enctype="application/x-www-form-urlencoded" autocomplete="off">
				<label for="forusername">Username: </label><input type="text" id="forusername" name="username" value="" required="required" autocapitalize="off">
				<label for="forpassword">Password: </label><input type="password" id="forpassword" name="password" value="" required="required" autocapitalize="off">
				<label for=""></label><button type="submit" name="login">Login</button>
			</form>
		</div>
<?php
}
// ========== end POHLED LOGIN
// ========== begin POHLED HOME
if($key_view == $stat_view_array['h']) {
?>
		<h2>Dashboard</h2>
		<p>Welcome</p>
		<ul id="dashboard">
			<li id="access"><a href="./?v=access"><strong>Access</strong><br>Overview of access</a></li>
			<li id="cards"><a href="./?v=cards"><strong>Cards</strong><br>Card management</a></li>
			<li id="users"><a href="./?v=users"><strong>Users</strong><br>User management</a></li>
			<li id="more"><a href="./?v=more"><strong>More</strong><br>Other options</a></li>
		</ul>
<?php
}
// ========== end POHLED HOME
// ========== begin POHLED MORE
if($key_view == $stat_view_array['m']) {
?>
		<h2>More</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; More</p>
		<ul id="more">
			<li><strong id="more-title-first">Administration</strong></li>
			<li id="config"><a href="./?v=config">Config</a></li>
			<li id="running"><a href="./?v=running">Running</a></li>
			<li id="timezones"><a href="./?v=timezones">Timezones</a></li>
			<li><strong>New items</strong></li>
			<li id="add-card"><a href="./?v=cards;s=past">New card</a></li>
			<li id="add-user"><a href="./?v=users;s=past">New user</a></li>
			<li id="add-timezone"><a href="./?v=timezones;s=past">New timezone</a></li>
			<li><strong>Information</strong></li>
			<li id="logs"><a href="./?v=logs">Logs</a></li>
			<li id="version"><a href="./?v=more">Version <?php
echo date('y.m.di.H.s', $update_datetime);
?></a></li>
		</ul>
<?php
}
// ========== end POHLED MORE
// ========== begin POHLED ACCESS
if($key_view == $stat_view_array['a']) {
?>
		<h2>Access</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; Access</p>
<?php
// sql dotaz #1
	$sqlquery_1 = "SELECT Kdy, Karta, Ctecka, StavZpracovani, Pozn, Povoleni, Smazano, cardid FROM AccessDetails ORDER BY kdy DESC LIMIT 1000";
// ochrana dotazu #1
	if($result_1 = mysqli_query($connection, $sqlquery_1)) {
// roztrhani vysledku sql dotazu #1
		if(mysqli_num_rows($result_1) > 0) {
?>
		<div id="table-container">
			<table id="data-table">
				<thead>
					<tr>
						<th class="th-6">When</th>
						<th class="th-6">Card</th>
						<th class="th-6">Reader</th>
						<th class="th-6">State</th>
						<th class="th-6">Description</th>
						<th class="th-6">Access</th>
					</tr>
				</thead>
				<tbody>
<?php
// cyklus pro vypis dat #1
			while($row_1 = mysqli_fetch_array($result_1)) {
				echo '<tr';
				if($row_1['Smazano'] == 1) {
					echo ' class="tr-deleted"';
				}
				echo '>';
				echo '<td>' . $row_1['Kdy'] . '</td>';
				echo '<td>';
				if(is_null($row_1['cardid'])) {
					$row_1_edit = str_replace(' ', '-', $row_1['Karta']);
					echo '<a href="./?v=cards;s=past;i=' . $row_1_edit . '">' . $row_1['Karta'] . '</a>';
				} else {
					echo '<a href="./?v=cards;s=edit;i=' . $row_1['cardid'] . '">' . $row_1['Karta'] . '</a>';
				}
				echo '</td>';
				echo '<td class="td-centered">' . $row_1['Ctecka'] . '</td>';
				echo '<td class="td-centered">' . $row_1['StavZpracovani'] . '</td>';
				echo '<td>';
				if(is_null($row_1['Pozn'])) {
					echo 'Unknown';
				} else {
					echo $row_1['Pozn'];
				}
				echo '</td>';
				echo '<td class="td-centered">';
				if(is_null($row_1['Povoleni'])) {
					echo 'Unknown';
				} else {
					echo $row_1['Povoleni'];
				}
				echo '</td>';
				echo '</tr>';
				echo "\n";
			}
?>
				</tbody>
			</table>
		</div>
<?php
// uvolnění paměti #1
			mysqli_free_result($result_1);
		} else {
			echo '<p>No data to display!</p>';
		}
	} else {
		echo '<p>Unable to process query! "' . $sqlquery_1 . '", Error! ' . mysqli_error($connection) . '</p>';
	}
}
// ========== end POHLED ACCESS
// ========== begin POHLED CARDS
// ========== begin POHLED CARDS - LIST
if($key_view == $stat_view_array['c'] && $key_cond != $stat_cond_array['p'] && $key_cond != $stat_cond_array['e'] && $key_cond != $stat_cond_array['d']) {
?>
		<h2>Cards</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; Cards</p>
<?php
// sql dotaz #2
$sqlquery_2 = "SELECT * FROM Karty LIMIT 1000";
// ochrana dotazu #2
	if($result_2 = mysqli_query($connection, $sqlquery_2)) {
// roztrhani vysledku sql dotazu #2
		if(mysqli_num_rows($result_2) > 0) {
?>
		<div id="table-container">
			<table id="data-table">
				<thead>
					<tr>
						<th class="th-7">Card</th>
						<th class="th-7">Reader</th>
						<th class="th-7">Timezone</th>
						<th class="th-7">Access</th>
						<th class="th-7">Description</th>
						<th class="th-7">Management</th>
						<th class="th-7">Permission</th>
					</tr>
				</thead>
				<tbody>
<?php
// cyklus pro vypis dat #2
			while($row_2 = mysqli_fetch_array($result_2)) {
				echo '<tr';
				if($row_2['Smazano'] == 1) {
					echo ' class="tr-deleted"';
				}
				echo '>';
				echo '<td class="nowrap"><a href="./?v=cards;s=edit;i=' . $row_2['cardid'] . '">' . $row_2['Karta'] . '</a></td>';
				echo '<td class="td-centered">' . $row_2['Ctecka'] . '</td>';
				echo '<td class="td-centered">';
				$zone_number_2 = $row_2['CasPlan'];
				$zone_name_2 = get_zone_name_by_number($connection, $zone_number_2);
				if($zone_name_2) {
					echo $zone_name_2;
					echo ' [' . $zone_number_2 . ']';
				} else {
					echo 'There is no zone!';
				}
				echo '</td>';
				echo '<td class="td-centered">' . $row_2['Povoleni'] . '</td>';
				echo '<td>' . $row_2['Pozn'] . '</td>';
				echo '<td><a href="./?v=cards;s=edit;i=' . $row_2['cardid'] . '">Edit</a></td>';
				echo '<td><a href="./?v=cards;s=delete;i=' . $row_2['cardid'] . '" onclick="return confirm(\'Card - ' . $row_2['Karta'] . ' - will be marked as blocked!\');">Block</a></td>';
				echo '</tr>';
				echo "\n";
			}
?>
				</tbody>
			</table>
		</div>
<?php
// uvolneni pameti #2
			mysqli_free_result($result_2);
		} else {
			echo '<p>No data to display!</p>';
		}
	} else {
		echo '<p>Unable to process query! "' . $sqlquery_2 . '", Error! ' . mysqli_error($connection) . '</p>';
	}
}
// ========== end POHLED CARDS - LIST
// ========== begin POHLED CARDS - NOVA KARTA
if($key_view == $stat_view_array['c'] && $key_cond == $stat_cond_array['p'] && $key_cond != $stat_cond_array['e'] && $key_cond != $stat_cond_array['d']) {
?>
		<h2>New card</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; <a href="./?v=cards" onclick="return confirm('Are you sure you want to leave without saving?');">Cards</a> &rsaquo; New card</p>
			<div id="form-container">
			<form action="<?php
echo $url;
?>" method="post" enctype="application/x-www-form-urlencoded" autocomplete="off">
				<input type="hidden" name="nove-smazano" value="0">
				<label for="fornovakarta">Card <span class="required">*</span></label>
				<input type="text" id="fornovakarta" name="nova-karta" value="<?php
	if(!empty($key_info)) {
		$key_info = str_replace('-', ' ', $key_info);
		echo $key_info;
	}
?>" required="required">
				<label for="fornovactecka">Reader <span class="required">*</span></label>
				<input type="text" id="fornovactecka" name="nova-ctecka" value="1" placeholder="1 &ndash; default" required="required">
				<label for="fornovycasplan">Timezone <span class="required">*</span></label>
<?php
// ziskani casovych zon z databaze
	$sqlquery_timezone = "SELECT Cislo, Nazev FROM CasovePlany ORDER BY Cislo ASC";
	$result_timezone = mysqli_query($connection, $sqlquery_timezone);
?>
				<select id="fornovycasplan" name="novy-cas-plan" required="required">
					<option value="0" selected="selected">No timezone [0]</option>
<?php
// vypsani moznosti do select
while($row_timezone = mysqli_fetch_assoc($result_timezone)) {
	$timezone_value = $row_timezone['Cislo'];
	$timezone_name = $row_timezone['Nazev'];
	echo "					";
	echo '<option value="' . $timezone_value . '">' . $timezone_name . ' [' . $timezone_value . ']</option>';
	echo "\n";
}
?>
				</select>
				<label for="fornovepovoleni">Access <span class="required">*</span></label>
				<input type="text" id="fornovepovoleni" name="nove-povoleni" value="1" placeholder="1 &ndash; default" required="required">
				<label for="fornovepozn">Description</label>
				<input type="text" id="fornovepozn" name="nove-pozn" value="" required="required">
				<div></div>
				<button type="submit" name="pridat-karta">Create</button>
			</form>
		</div>
		<p><span class="required">* Required</span></p>
		<p><a href="./?v=cards" onclick="return confirm('Are you sure you want to leave without saving?');">&lsaquo; Back on Cards</a></p>
<?php
}
// ========== end POHLED CARDS - NOVA KARTA
// ========== begin POHLED CARDS - EDITACE KARTY
if($key_view == $stat_view_array['c'] && $key_cond != $stat_cond_array['p'] && $key_cond == $stat_cond_array['e'] && $key_cond != $stat_cond_array['d']) {
?>
		<h2>Edit card</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; <a href="./?v=cards" onclick="return confirm('Are you sure you want to leave without saving?');">Cards</a> &rsaquo; Edit card</p>
		<div id="form-container">
			<form action="<?php
echo $url;
?>" method="post" enctype="application/x-www-form-urlencoded" autocomplete="off">
				<input type="hidden" name="cardid" value="<?php
echo $row_select['cardid'];
?>">
				<label for="foreditkarta">Card (read only)</label>
				<input type="text" id="foreditkarta" name="edit-karta" value="<?php
echo $row_select['Karta'];
?>" readonly="readonly">
				<label for="foreditctecka">Reader <span class="required">*</span></label>
				<input type="text" id="foreditctecka" name="edit-ctecka" value="<?php
echo $row_select['Ctecka'];
?>" required="required">
				<label for="foreditcasplan">Timezone <span class="required">*</span></label>
<?php
// ziskani casovych zon z databaze
	$sqlquery_timezone = "SELECT Cislo, Nazev FROM CasovePlany ORDER BY Cislo ASC";
	$result_timezone = mysqli_query($connection, $sqlquery_timezone);
?>
				<select id="foreditcasplan" name="edit-cas-plan" required="required">
					<option value="0">No timezone [0]</option>
<?php
// vypsani moznosti do select
	while($row_timezone = mysqli_fetch_assoc($result_timezone)) {
		$timezone_value = $row_timezone['Cislo'];
		$timezone_name = $row_timezone['Nazev'];
		echo "					";
		echo '<option value="' . $timezone_value . '"';
		if($row_select['CasPlan'] == $timezone_value) {
			echo ' selected="selected"';
		}
		echo '>' . $timezone_name . ' [' . $timezone_value . ']</option>';
		echo "\n";
	}
?>
				</select>
				<label for="foreditpovoleni">Access <span class="required">*</span></label>
				<input type="text" id="foreditpovoleni" name="edit-povoleni" value="<?php
echo $row_select['Povoleni'];
?>" required="required">
				<label for="foreditsmazano">Rights <span class="required">*</span></label>
				<select id="foreditsmazano" name="edit-smazano" required="required">
					<option value="">&ndash;</option>
<?php
	$row_selected = $row_select['Smazano'];
	if($row_selected == '0') {
?>
					<option value="0" selected="selected">Enabled</option>
					<option value="1">Block</option>
<?php
	} elseif($row_selected == '1') {
?>
					<option value="0">Enable</option>
					<option value="1" selected="selected">Blocked</option>
<?php
	} else {
?>
					<option value="0">Enable</option>
					<option value="1">Block</option>
<?php
	}
?>
				</select>
				<label for="foreditpozn">Description</label>
				<input type="text" id="foreditpozn" name="edit-pozn" value="<?php
echo $row_select['Pozn'];
?>">
				<div></div>
				<button type="submit" name="edit-zaznam" onclick="return confirm('Are you sure you want to edit this listing?');">Commit changes</button>
			</form>
		</div>
		<p><span class="required">* Required</span></p>
		<p><a href="./?v=cards" onclick="return confirm('Are you sure you want to leave without saving?');">&lsaquo; Back on Cards</a></p>
<?php
}
// ========== end POHLED CARDS - EDITACE KARTY
// ========== end POHLED CARDS
// ========== begin POHLED TIMEZONES
// ========== begin POHLED TIMEZONES - LIST
if($key_view == $stat_view_array['t'] && $key_cond != $stat_cond_array['p'] && $key_cond != $stat_cond_array['e'] && $key_cond != $stat_cond_array['d']) {
?>
		<h2>Timezones</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; <a href="./?v=more">More</a> &rsaquo; Timezones</p>
<?php
// sql dotaz #7
	$sqlquery_7 = "SELECT * FROM CasovePlany ORDER BY Cislo ASC LIMIT 1000";
// ochrana dotazu #7
	if($result_7 = mysqli_query($connection, $sqlquery_7)) {
// roztrhani vysledku sql dotazu #7
		if(mysqli_num_rows($result_7) > 0) {
?>
		<div id="table-container">
			<table id="data-table">
				<thead>
					<tr>
						<th class="th-5">Number</th>
						<th class="th-5">Name</th>
						<th class="th-5">Description</th>
						<th class="th-5">State</th>
						<th class="th-5">Edit</th>
					</tr>
				</thead>
				<tbody>
<?php
// cyklus pro vypis dat #7
			while($row_7 = mysqli_fetch_array($result_7)) {
				$row_status_7 = ['0' => 'No system plan', '1' => 'Silent open', '2' => 'Pulz', '3' => 'Reverz'];
				echo '<tr';
				if($row_7['Smazano'] == 1) {
					echo ' class="tr-deleted"';
				}
				echo '>';
				echo '<td><a href="./?v=timezones;s=edit;i=' . $row_7['Id_CasovyPlan'] . '">' . $row_7['Cislo'] . '</a></td>';
				echo '<td>' . $row_7['Nazev'] . '</td>';
				echo '<td>' . $row_7['Popis'] . '</td>';
				echo '<td class="td-centered">' . $row_status_7[$row_7['RezimOtevirani']] . '</td>';
				echo '<td><a href="./?v=timezones;s=edit;i=' . $row_7['Id_CasovyPlan'] . '">Edit</a></td>';
				echo '</tr>';
				echo "\n";
			}
?>
				</tbody>
			</table>
		</div>
<?php
// uvolneni pameti #7
			mysqli_free_result($result_7);
		} else {
			echo '<p>No data to display!</p>';
		}
	} else {
		echo '<p>Unable to process query! "' . $sqlquery_7 . '", Error! ' . mysqli_error($connection) . '</p>';
	}
}
// ========== end POHLED TIMEZONES - LIST
// ========== begin POHLED TIMEZONES - NOVA TIMEZONE
if($key_view == $stat_view_array['t'] && $key_cond == $stat_cond_array['p'] && $key_cond != $stat_cond_array['e'] && $key_cond != $stat_cond_array['d']) {
?>
		<h2>Timezones</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; <a href="./?v=more">More</a> &rsaquo; <a href="./?v=timezones" onclick="return confirm('Are you sure you want to leave without saving?');">Timezones</a> &rsaquo; New timezone</p>
		<div id="form-container">
			<form action="<?php
echo $url;
?>" method="post" enctype="application/x-www-form-urlencoded" autocomplete="off">
				<label for="fornovynumber">Number <span class="required">*</span></label>
				<select id="nove-cislo" name="nove-cislo" required="required">
<?php
	$free_numbers = get_free_zone_numbers($connection);
	foreach($free_numbers as $number) {
		echo "					";
		echo '<option value="' . $number . '">' . $number . '</option>';
		echo "\n";
	}
?>
				</select>
				<label for="fornovyname">Name <span class="required">*</span></label>
				<input type="text" id="fornovyname" name="novy-nazev" value="" required="required">
				<label for="fornovydesc">Description <span class="required">*</span></label>
				<input type="text" id="fornovydesc" name="novy-popis" value="" required="required">
				<label for="fornovystate">State <span class="required">*</span></label>				
				<select id="fornovystate" name="novy-stav" required="required">
					<option value="0">No system plan</option>
					<option value="1">Silent open</option>
					<option value="2" selected="selected">Puls</option>
					<option value="3">Reverz</option>
				</select>
				<div></div>
				<div class="matrix-4">
					<label for="tempstart1">From</label>
					<label for="tempend1">To</label>
					<label for="tempstart2">From</label>
					<label for="tempend2">To</label>
				</div>
				<label for="">Template</label>
				<div class="matrix-4">
					<input type="text" id="tempstart1" name="tempstart1n" value="" placeholder="HH:MM:SS – required" class="time-hhmmss">
					<input type="text" id="tempend1" name="tempend1n" value="" placeholder="HH:MM:SS – required" class="time-hhmmss">
					<input type="text" id="tempstart2" name="tempstart2n" value="" placeholder="HH:MM:SS – optional" class="time-hhmmss">
					<input type="text" id="tempend2" name="tempend2n" value="" placeholder="HH:MM:SS – optional" class="time-hhmmss">
				</div>
				<div></div>
				<button type="button" id="copy-data">Copy template</button>
<?php
// pole pro generovani hodnot
	$timezones_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'Holiday'];
	$timezones_days_short = ['mo', 'tu', 'we', 'th', 'fr', 'sa', 'su', 'ho'];
// cyklus pro generovani
	for($i = 0;$i < 8;$i++) {
// definice hodnot
		$timezones_day_week = $timezones_days[$i];
		$timezones_input_var = $timezones_days_short[$i];
		echo '				<div></div>';
		echo "\n";
		echo '				<div class="matrix-4">';
		echo "\n";
		echo '					<label for="' . $timezones_input_var . 'start1">From <span class="required">*</span></label>';
		echo "\n";
		echo '					<label for="' . $timezones_input_var . 'end1">To <span class="required">*</span></label>';
		echo "\n";
		echo '					<label for="' . $timezones_input_var . 'start2">From</label>';
		echo "\n";
		echo '					<label for="' . $timezones_input_var . 'end2">To</label>';
		echo "\n";
		echo '				</div>';
		echo "\n";
		echo '				<label for="">' . $timezones_day_week . '</label>';
		echo "\n";
		echo '				<div class="matrix-4">';
		echo "\n";
		echo '					<input type="text" id="' . $timezones_input_var . 'start1" name="' . $timezones_input_var . 'start1v" value="" placeholder="HH:MM:SS – required" required="required" class="time-hhmmss">';
		echo "\n";
		echo '					<input type="text" id="' . $timezones_input_var . 'end1" name="' . $timezones_input_var . 'end1v" value="" placeholder="HH:MM:SS – required" required="required" class="time-hhmmss">';
		echo "\n";
		echo '					<input type="text" id="' . $timezones_input_var . 'start2" name="' . $timezones_input_var . 'start2v" value="" placeholder="HH:MM:SS – optional" class="time-hhmmss">';
		echo "\n";
		echo '					<input type="text" id="' . $timezones_input_var . 'end2" name="' . $timezones_input_var . 'end2v" value="" placeholder="HH:MM:SS – optional" class="time-hhmmss">';
		echo "\n";
		echo '				</div>';
		echo "\n";
	}
?>
				<div></div>
				<button type="submit" name="nova-zona">Create</button>
			</form>
		</div>
		<p><span class="required">* Required</span></p>
		<p><a href="./?v=timezones" onclick="return confirm('Are you sure you want to leave without saving?');">&lsaquo; Back on Timezones</a></p>
<?php
}
// ========== end POHLED TIMEZONES - NOVA TIMEZONE
// ========== begin POHLED TIMEZONES - EDITACE TIMEZONE
if($key_view == $stat_view_array['t'] && $key_cond != $stat_cond_array['p'] && $key_cond == $stat_cond_array['e'] && $key_cond != $stat_cond_array['d']) {
?>
		<h2>Timezones</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; <a href="./?v=more">More</a> &rsaquo; <a href="./?v=timezones" onclick="return confirm('Are you sure you want to leave without saving?');">Timezones</a> &rsaquo; Edit timezone</p>
		<div id="form-container">
			<form action="<?php
echo $url;
?>" method="post" enctype="application/x-www-form-urlencoded" autocomplete="off">
				<input type="hidden" name="id" value="<?php
echo $row_select['Id_CasovyPlan'];
?>">
				<label for="foreditnumber">Number</label>
				<select id="edit-cislo" name="edit-cislo" required="required">
<?php
	$free_numbers = get_free_zone_numbers($connection);
	$current_number = $row_select['Cislo'];
	echo "					";
	echo '<option value="' . $current_number . '" selected="selected">' . $current_number . '</option>';
	echo "\n";
	foreach($free_numbers as $number) {
		echo "					";
		echo '<option value="' . $number . '">' . $number . '</option>';
		echo "\n";
	}
?>
				</select>
				<label for="foreditname">Name <span class="required">*</span></label>
				<input type="text" id="foreditname" name="edit-nazev" value="<?php
echo $row_select['Nazev'];
?>" required="required">
				<label for="foreditdesc">Description <span class="required">*</span></label>
				<input type="text" id="foreditdesc" name="edit-popis" value="<?php
echo $row_select['Popis'];
?>" required="required">
				<label for="foreditstate">State <span class="required">*</span></label>				
				<select id="foreditstate" name="edit-stav" required="required">
<?php
	$row_selected = $row_select['RezimOtevirani'];
	if($row_selected == '0') {
?>
					<option value="0" selected="selected">No system plan</option>
					<option value="1">Silent open</option>
					<option value="2">Puls</option>
					<option value="3">Reverz</option>
					<?php
	} elseif($row_selected == '1') {
?>
					<option value="0">No system plan</option>
					<option value="1" selected="selected">Silent open</option>
					<option value="2">Puls</option>
					<option value="3">Reverz</option>
					<?php
	} elseif($row_selected == '2') {
?>
					<option value="0">No system plan</option>
					<option value="1">Silent open</option>
					<option value="2" selected="selected">Puls</option>
					<option value="3">Reverz</option>
<?php
	} elseif($row_selected == '3') {
?>
					<option value="0">No system plan</option>
					<option value="1">Silent open</option>
					<option value="2">Puls</option>
					<option value="3" selected="selected">Reverz</option>
<?php
	} else {
?>
					<option value="0">No system plan</option>
					<option value="1">Silent open</option>
					<option value="2">Puls</option>
					<option value="3">Reverz</option>
<?php
	}
?>
				</select>
				<div></div>
				<div class="matrix-4">
					<label for="tempstart1">From</label>
					<label for="tempend1">To</label>
					<label for="tempstart2">From</label>
					<label for="tempend2">To</label>
				</div>
				<label for="">Template</label>
				<div class="matrix-4">
					<input type="text" id="tempstart1" name="tempstart1n" value="00:00:00" placeholder="HH:MM:SS – required" class="time-hhmmss">
					<input type="text" id="tempend1" name="tempend1n" value="00:00:00" placeholder="HH:MM:SS – required" class="time-hhmmss">
					<input type="text" id="tempstart2" name="tempstart2n" value="00:00:00" placeholder="HH:MM:SS – optional" class="time-hhmmss">
					<input type="text" id="tempend2" name="tempend2n" value="00:00:00" placeholder="HH:MM:SS – optional" class="time-hhmmss">
				</div>
				<div></div>
				<button type="button" id="copy-data">Copy template</button>
<?php
// pole pro generovani hodnot
	$timezones_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'Holiday'];
	$timezones_days_short = ['mo', 'tu', 'we', 'th', 'fr', 'sa', 'su', 'ho'];
	$timezones_days_sql = ['Po', 'Ut', 'St', 'Ct', 'Pa', 'So', 'Ne', 'Svatky'];
// cyklus pro generovani
	for($i = 0;$i < 8;$i++) {
// definice hodnot
		$timezones_day_week = $timezones_days[$i];
		$timezones_input_var = $timezones_days_short[$i];
		$timezones_day_sql = $timezones_days_sql[$i];
		$start1 = isset($row_select[$timezones_day_sql . '_PrvniZacatek']) ? $row_select[$timezones_day_sql . '_PrvniZacatek'] : '';
		$end1 = isset($row_select[$timezones_day_sql . '_PrvniKonec']) ? $row_select[$timezones_day_sql . '_PrvniKonec'] : '';
		$start2 = isset($row_select[$timezones_day_sql . '_DruhyZacatek']) ? $row_select[$timezones_day_sql . '_DruhyZacatek'] : '';
		$end2 = isset($row_select[$timezones_day_sql . '_DruhyKonec']) ? $row_select[$timezones_day_sql . '_DruhyKonec'] : '';
		echo '				<div></div>';
		echo "\n";
		echo '				<div class="matrix-4">';
		echo "\n";
		echo '					<label for="' . $timezones_input_var . 'start1">From <span class="required">*</span></label>';
		echo "\n";
		echo '					<label for="' . $timezones_input_var . 'end1">To <span class="required">*</span></label>';
		echo "\n";
		echo '					<label for="' . $timezones_input_var . 'start2">From</label>';
		echo "\n";
		echo '					<label for="' . $timezones_input_var . 'end2">To</label>';
		echo "\n";
		echo '				</div>';
		echo "\n";
		echo '				<label for="">' . $timezones_day_week . '</label>';
		echo "\n";
		echo '				<div class="matrix-4">';
		echo "\n";
		echo '					<input type="text" id="' . $timezones_input_var . 'start1" name="' . $timezones_input_var . 'start1v" value="' . htmlspecialchars($start1) . '" placeholder="HH:MM:SS – required" required="required" class="time-hhmmss">';
		echo "\n";
		echo '					<input type="text" id="' . $timezones_input_var . 'end1" name="' . $timezones_input_var . 'end1v" value="' . htmlspecialchars($end1) . '" placeholder="HH:MM:SS – required" required="required" class="time-hhmmss">';
		echo "\n";
		echo '					<input type="text" id="' . $timezones_input_var . 'start2" name="' . $timezones_input_var . 'start2v" value="' . htmlspecialchars($start2) . '" placeholder="HH:MM:SS – optional" class="time-hhmmss">';
		echo "\n";
		echo '					<input type="text" id="' . $timezones_input_var . 'end2" name="' . $timezones_input_var . 'end2v" value="' . htmlspecialchars($end2) . '" placeholder="HH:MM:SS – optional" class="time-hhmmss">';
		echo "\n";
		echo '				</div>';
		echo "\n";
	}
?>
				<div></div>
				<button type="submit" name="edit-zona" onclick="return confirm('Are you sure you want to edit this listing?');">Commit changes</button>
			</form>
		</div>
		<p><span class="required">* Required</span></p>
		<p><a href="./?v=timezones" onclick="return confirm('Are you sure you want to leave without saving?');">&lsaquo; Back on Timezones</a></p>
<?php
}
// ========== end POHLED TIMEZONES - EDITACE TIMEZONE
// ========== end POHLED TIMEZONES
// ========== begin POHLED USERS
// ========== begin POHLED USERS - LIST
if($key_view == $stat_view_array['u'] && $key_cond != $stat_cond_array['p'] && $key_cond != $stat_cond_array['e'] && $key_cond != $stat_cond_array['d']) {
?>
		<h2>Users</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; Users</p>
<?php
// sql dotaz #4
	$sqlquery_4 = "SELECT * FROM users LIMIT 1000";
// ochrana dotazu #4
	if($result_4 = mysqli_query($connection, $sqlquery_4)) {
// roztrhani vysledku sql dotazu #4
		if(mysqli_num_rows($result_4) > 0) {
?>
		<div id="table-container">
			<table id="data-table">
				<thead>
					<tr>
						<th class="th-3">User</th>
						<th class="th-3">Management</th>
						<th class="th-3">Rights</th>
					</tr>
				</thead>
				<tbody>
<?php
// cyklus pro vypis dat #4
			while($row_4 = mysqli_fetch_array($result_4)) {
				echo '<tr';
				if($row_4['rights'] == 0) {
					echo ' class="tr-deleted"';
				}
				echo '>';
				echo '<td>' . $row_4['LogonName'] . '</td>';
				echo '<td><a href="./?v=users;s=edit;i=' . $row_4['id'] . '">Edit</a></td>';
				echo '<td><a href="./?v=users;s=delete;i=' . $row_4['id'] . '" onclick="return confirm(\'User - ' . $row_4['LogonName'] . ' - will be marked as blocked!\');">Block</a></td>';
				echo '</tr>';
				echo "\n";
			}
?>
				</tbody>
			</table>
		</div>
<?php
// uvolneni pameti #4
			mysqli_free_result($result_4);
		} else {
			echo '<p>No data to display!</p>';
		}
	} else {
		echo '<p>Unable to process query! "' . $sqlquery_4 . '", Error! ' . mysqli_error($connection) . '</p>';
	}
}
// ========== end POHLED USERS - LIST
// ========== begin POHLED USERS - NOVY USER
if($key_view == $stat_view_array['u'] && $key_cond == $stat_cond_array['p'] && $key_cond != $stat_cond_array['e'] && $key_cond != $stat_cond_array['d']) {
	?>
		<h2>New user</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; <a href="./?v=users" onclick="return confirm('Are you sure you want to leave without saving?');">Users</a> &rsaquo; New user</p>
		<div id="form-container">
			<form action="<?php
echo $url;
?>" method="post" enctype="application/x-www-form-urlencoded" autocomplete="off">
				<label for="fornovyuser">Username <span class="required">*</span></label>
				<input type="text" id="fornovyuser" name="novy-user" value="" required="required">
				<label for="fornovymd5">Password <span class="required">*</span></label>
				<input type="password" id="fornovymd5" name="novy-md5" value="" required="required" onkeyup="checkPasswordStrength();">
				<div></div>
				<div id="password-strength-status"></div>
				<input type="hidden" name="nova-prava" value="255">
				<div></div>
				<button type="submit" name="novy-uzivatel">Create</button>
			</form>
		</div>
		<p><span class="required">* Required</span></p>
		<p><a href="./?v=users" onclick="return confirm('Are you sure you want to leave without saving?');">&lsaquo; Back on Users</a></p>
<?php
}
// ========== end POHLED USERS - NOVY USER
// ========== begin POHLED USERS - EDITACE USERA
if($key_view == $stat_view_array['u'] && $key_cond != $stat_cond_array['p'] && $key_cond == $stat_cond_array['e'] && $key_cond != $stat_cond_array['d']) {
?>
		<h2>Edit user</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; <a href="./?v=users" onclick="return confirm('Are you sure you want to leave without saving?');">Users</a> &rsaquo; Edit user</p>
		<div id="form-container">
			<form action="<?php
echo $url;
?>" method="post" enctype="application/x-www-form-urlencoded" autocomplete="off">
				<input type="hidden" name="id" value="<?php
echo $row_select['id'];
?>">
				<label for="foredituser">Username (read only)</label>
				<input type="text" id="foredituser" name="edit-user" value="<?php
echo $row_select['LogonName'];
?>" readonly="readonly">
				<label for="foreditmd5">Password (new only) <span class="required">*</span></label>
				<input type="password" id="foreditmd5" name="edit-md5" value="" required="required" onkeyup="checkPasswordStrength();">
				<div></div>
				<div id="password-strength-status"></div>
				<label for="foreditprava">Rights</label>
				<select id="foreditprava" name="edit-prava" required="required">
					<option value="">&ndash;</option>
<?php
	$row_selected = $row_select['rights'];
	if($row_selected == '255') {
?>
					<option value="255" selected="selected">Enabled</option>
					<option value="0">Block</option>
<?php
	} elseif($row_selected == '0') {
?>
					<option value="255">Enable</option>
					<option value="0" selected="selected">Blocked</option>
<?php
	} else {
?>
					<option value="255">Enable</option>
					<option value="0">Block</option>
<?php
	}
?>
				</select>
				<div></div>
				<button type="submit" name="edit-uzivatel">Commit changes</button>
			</form>
		</div>
		<p><span class="required">* Required</span></p>
		<p><a href="./?v=users" onclick="return confirm('Are you sure you want to leave without saving?');">&lsaquo; Back on Users</a></p>
<?php
}
// ========== end POHLED USERS - EDITACE USERA
// ========== end POHLED USERS
// ========== begin POHLED CONFIGDU
// ========== begin POHLED CONFIGDU - LIST
// pohled pro potvrzeni vsech zmen
if($key_view == $stat_view_array['o'] && $key_cond != $stat_cond_array['e'] && $key_cond != $stat_cond_array['p']) {
?>
		<h2>Configuration</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; <a href="./?v=more">More</a> &rsaquo; Configuration</p>
<?php
// sql dotaz #5
	$sqlquery_5 = "SELECT * FROM ConfigDU LIMIT 1000";
// ochrana dotazu #5
	if($result_5 = mysqli_query($connection, $sqlquery_5)) {
// roztrhani vysledku sql dotazu #5
		if(mysqli_num_rows($result_5) > 0) {
			while($row_5 = mysqli_fetch_array($result_5)) {
// pridani radku do pole
				$config_data[] = $row_5;
			}
// ulozeni dat do session
			$_SESSION['config_data'] = $config_data;
// uvolneni pameti #5
			mysqli_free_result($result_5);
		} else {
			echo '<p>No data to display!</p>';
		}
	} else {
		echo '<p>Unable to process query! "' . $sqlquery_5 . '", Error! ' . mysqli_error($connection) . '</p>';
	}
?>
		<div id="table-container">
			<table id="data-table">
				<thead>
					<tr>
						<th class="th-3">Property</th>
						<th class="th-3">Value</th>
						<th class="th-3">Management</th>
					</tr>
				</thead>
				<tbody>
<?php
// urci, ktera pole pouzit pro vypis do tabulky
	$config_data_view = isset($_SESSION['config_data_temp']) ? $_SESSION['config_data_temp'] : $_SESSION['config_data'];
// pokud jsou data ulozena v session
	if(!empty($config_data_view)) {
// vypsat data ulozena v session
		foreach($config_data_view as $row_5) {
			echo '<tr>';
			echo '<td class="td-upper">' . $row_5['property'] . '</td>';
			echo '<td class="td-centered">' . $row_5['value'] . '</td>';
			echo '<td><a href="./?v=config;s=edit;i=' . $row_5['id'] . '">Edit</a></td>';
			echo '</tr>';
			echo "\n";
		}
	} else {
		echo '<p>No data in SESSION!</p>';
	}
?>
				</tbody>
			</table>
		</div>
<?php
}
// ========== end POHLED CONFIGDU - LIST
// ========== begin POHLED CONFIGDU - EDITACE KONFIGURACE
if($key_view == $stat_view_array['o'] && $key_cond == $stat_cond_array['e']) {
?>
		<h2>Edit configuration</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; <a href="./?v=more">More</a> &rsaquo; <a href="./?v=config" onclick="return confirm('Are you sure you want to leave without saving?');">Configuration</a> &rsaquo; Edit configuration</p>
		<div id="form-container">
			<form action="<?php
echo $url;
?>" method="post" enctype="application/x-www-form-urlencoded" autocomplete="off">
<?php
// pokud pole config_data existuje v session
	if(isset($_SESSION['config_data'])) {
// projit vsechny polozky pole
		foreach($_SESSION['config_data'] as $row_5) {
// pokud se ID polozky shoduje s ID, ktere chcete ponechat
			if($row_5['id'] == $key_info) {
?>
				<input type="hidden" name="id" value="<?php
echo $row_5['id'];
?>">
				<label for="foreditproperty">Property</label>
				<input type="text" id="foreditproperty" name="edit-property" value="<?php
echo $row_5['property'];
?>" readonly="readonly">
				<label for="foreditvalue">Value <span class="required">*</span></label>
				<input type="text" id="foreditvalue" name="edit-value" value="<?php
echo $row_5['value'];
?>" title="Example <?php
echo $row_5['value'];
?>" required="required">
				<input type="hidden" name="configdu-valid" value="<?php
echo $row_5['regex'];
?>">
				<div></div>
				<button type="submit" name="edit-config" onclick="return confirm('Are you sure you want to edit this listing?');">Commit changes</button>
<?php
			}
		}
	} else {
		echo '<p>No data in SESSION!</p>';
	}
?>
			</form>
		</div>
		<p><span class="required">* Required</span></p>
		<p><a href="./?v=config" onclick="return confirm('Are you sure you want to leave without saving?');">&lsaquo; Back on Configuration</a></p>
<?php
}
// ========== end POHLED CONFIGDU - EDITACE KONFIGURACE
// ========== end POHLED CONFIGDU
// ========== begin POHLED RUNNING
if($key_view == $stat_view_array['r']) {
?>
		<h2>Running</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; <a href="./?v=more">More</a> &rsaquo; Running</p>
<?php
// sql dotaz #3
	$sqlquery_3 = "SELECT * FROM running order by lastchange DESC LIMIT 1000";
// ochrana dotazu #3
	if($result_3 = mysqli_query($connection, $sqlquery_3)) {
// roztrhani vysledku sql dotazu #3
		if(mysqli_num_rows($result_3) > 0) {
?>
		<div id="table-container">
			<table id="data-table">
				<thead>
					<tr>
						<th class="th-3">Property</th>
						<th class="th-3">Value</th>
						<th class="th-3">Last change</th>
					</tr>
				</thead>
				<tbody>
<?php
// cyklus pro vypis dat #3
			while($row_3 = mysqli_fetch_array($result_3)) {
				echo '<tr>';
				echo '<td>' . $row_3['property'] . '</td>';
				echo '<td>' . $row_3['value'] . '</td>';
				echo '<td>' . $row_3['lastchange'] . '</td>';
				echo '</tr>';
				echo "\n";
			}
?>
				</tbody>
			</table>
		</div>
<?php
// uvolneni pameti #3
			mysqli_free_result($result_3);
		} else {
			echo '<p>No data to display!</p>';
		}
	} else {
		echo '<p>Unable to process query! "' . $sqlquery_3 . '", Error! ' . mysqli_error($connection) . '</p>';
	}
}
// ========== end POHLED RUNNING
// ========== begin POHLED LOGS
if($key_view == $stat_view_array['v']) {
	?>
			<h2>Logs</h2>
			<p><a href="./?v=home">Dashboard</a> &rsaquo; <a href="./?v=more">More</a> &rsaquo; Logs</p>
<?php
// sql dotaz #6
	$sqlquery_6 = "SELECT * FROM logs order by ts DESC LIMIT 1000";
// ochrana dotazu #6
	if($result_6 = mysqli_query($connection, $sqlquery_6)) {
// roztrhani vysledku sql dotazu #6
		if(mysqli_num_rows($result_6) > 0) {
?>
			<div rows="5" id="textarea-container">
				<pre><?php
// cyklus pro vypis dat #6
			while($row_6 = mysqli_fetch_array($result_6)) {
				echo '' . $row_6['ts'] . ' ';
				echo '' . $row_6['severity'] . ' ';
				echo '' . $row_6['message'] . '';
				echo "\n";
			}
?></pre>
			</div>
<?php
// uvolneni pameti #6
			mysqli_free_result($result_6);
		} else {
			echo '<p>No data to display!</p>';
		}
	} else {
		echo '<p>Unable to process query! "' . $sqlquery_6 . '", Error! ' . mysqli_error($connection) . '</p>';
	}
}
?>
	</div>
	<div id="footer">
<?php

// ========== end POHLED LOGS
// ========== begin JINE POHLED NEZ LOGIN
if($key_view != $stat_view_array['l']) {
?>
		<ul id="footer-navigation">
			<li id="navigation-home"><a href="./?v=home">Dash</a></li>
			<li id="navigation-access"><a href="./?v=access">Access</a></li>
			<li id="navigation-cards"><a href="./?v=cards">Cards</a></li>
			<li id="navigation-users"><a href="./?v=users">Users</a></li>
			<li id="navigation-more"><a href="./?v=more">More</a></li>
		</ul>
<?php
}
// ========== end JINE POHLED NEZ LOGIN
?>
	</div>
</div>
<script src="./js/meritaccess-body.min.js"></script>
</body>
</html><?php
// ukonceni spojeni
mysqli_close($connection);
?>