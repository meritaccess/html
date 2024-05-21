<?php
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
$stat_view_array = ['l' => 'lock', 'h' => 'home', 'a' => 'access', 'c' => 'cards', 'u' => 'users', 'o' => 'config', 'r' => 'running', 'm' => 'more', 'x' => 'drop'];
$stat_cond_array = ['e' => 'edit', 'p' => 'past', 'd' => 'delete', 'b' => 'block'];
$stat_info_array = ['o' => 'order'];
$stat_fact_array = ['z' => 'alert'];
// titulky v title
$title_view_array = ['lock' => 'Login', 'home' => 'Dashboard', 'access' => 'Access', 'config' => 'Configuration', 'cards' => 'Cards', 'running' => 'Running', 'users' => 'Users', 'more' => 'More'];
// pole vyloucenych, ktere musi mit login
$keys_url_array_except = [$stat_view_array['h'], $stat_view_array['a'], $stat_view_array['o'], $stat_view_array['c'], $stat_view_array['r'], $stat_view_array['u'], $stat_view_array['d'], $stat_view_array['m']];
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
// SQL pripojeni
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'ma');
define('DB_PASSWORD', 'FrameWork5414*');
define('DB_NAME', 'MeritAccessLocal');
$connection = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
// pokud nefunguje spojeni - chyba
if($connection === false) {
	die('CONNECTION ERROR! ' . mysqli_connect_error());
}
// prihlasovaci proces
if(isset($_POST['login'])) {
	$input_username = $_POST['username'];
	$input_password = $_POST['password'];
// ochrana proti SQL injection
	$safe_username = $connection->real_escape_string($input_username);
	$safe_password = $connection->real_escape_string($input_password);
	$sql = "SELECT * FROM users WHERE LogonName = '$safe_username' AND MD5 = md5('$safe_password') AND rights = 255";
	$result = $connection->query($sql);
	if($result->num_rows == 1) {
		$_SESSION['access_granted'] = true;
		header('location: ./?v=home');
	} else {
// kontrola pro neplatne prava
		$sql_rights = "SELECT rights FROM users WHERE LogonName = '$safe_username'";
		$result_rights = $connection->query($sql_rights);
		if($result_rights && $result_rights->num_rows == 1) {
			$row_rights = $result_rights->fetch_assoc();
			if($row_rights['rights'] != 255) {
				$_SESSION['error_message'] = 'User does not have sufficient rights!';
				header('location: ./?v=lock;f=alert');
				exit;
			} else {
				$_SESSION['error_message'] = 'Wrong username or password!';
				header('location: ./?v=lock;f=alert');
				exit;
			}
		} else {
			$_SESSION['error_message'] = 'Error getting user rights!';
			header('location: ./?v=lock;f=alert');
			exit;
		}
	}
}
// odhlaseni
if($key_view == $stat_view_array['x']) {
	session_destroy();
	header('location: ./?v=lock');
}
// funkce pro verzovani
function fileVersioning($tracked_file_input) {
	$datetime_update = date('ymdHi', filemtime($tracked_file_input));
	$tracked_file_output = $tracked_file_input . '?v=' . $datetime_update;
	return $tracked_file_output;
}
// datum aktualizace
$update_datetime = filemtime(__FILE__);
// ========== cast configdu
// editace konfigurace
if($key_view == $stat_view_array['o'] && $key_cond == $stat_cond_array['e']) {
	$config_data = array();
	$config_data_temp = array();
// editace zaznamu konfigurace
	if(isset($_POST['edit-config'])) {
// pokud existuje SESSION, presue jej do TEMP_SESSION
		if(isset($_SESSION['config_data'])) {
			$_SESSION['config_data_temp'] = $_SESSION['config_data'];
		}
		$id = $_POST['id'];
		$edit_property = $_POST['edit-property'];
		$edit_value = $_POST['edit-value'];
		$configdu_valid = $_POST['configdu-valid'];
		if(!preg_match('/' . $configdu_valid . '/', $edit_value)) {
			$_SESSION['error_message'] = 'Error! Invalid format for property: ' . $edit_property . '!';
			header('location: ./?v=config;f=alert');
			exit;
		} else {
// najde index daneho ID v poli SESSION
			$index = array_search($id, array_column($_SESSION['config_data_temp'], 'id'));
// pokud index byl nalezen
			if($index !== false) {
// aktualizuje data v SESSION
				$_SESSION['config_data_temp'][$index]['property'] = $edit_property;
				$_SESSION['config_data_temp'][$index]['value'] = $edit_value;
			}
			header('location: ./?v=config');
			exit();
		}
	}
}
// editace konfigurace - ulozeni
if($key_view == $stat_view_array['o'] && $key_cond == $stat_cond_array['p']) {
	$sqlquery_edit = "UPDATE ConfigDU SET property = ?, value = ? WHERE id = ?";
	$stmt = mysqli_prepare($connection, $sqlquery_edit);
	if($stmt === false) {
		$_SESSION['error_message'] = 'Error in preparing SQL: ' . mysqli_error($connection) . '!';
		header('location: ./?v=config;f=alert');
		exit;
	}
// projde vsechny zaznamy v SESSION
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
			header('location: ./?v=config;f=alert');
			exit;
		}
	}
	unset($_SESSION['config_data_temp']);
	header('location: ./?v=config');
	exit();
}
// ========== cast karty
// novy zaznam karty
if($key_view == $stat_view_array['c'] && $key_cond == $stat_cond_array['p'] && $key_cond != $stat_cond_array['e'] && $key_cond != $stat_cond_array['d']) {
// ulozeni dat karty do db
	if(isset($_POST['pridat-karta'])) {
		$nova_karta = $_POST['nova-karta'];
		$nova_ctecka = $_POST['nova-ctecka'];
		$novy_cas_plan = $_POST['novy-cas-plan'];
		$nove_povoleni = $_POST['nove-povoleni'];
		$nove_smazano = $_POST['nove-smazano'];
		$nove_pozn = $_POST['nove-pozn'];
		$sqlquery_novy_zaznam = "INSERT INTO Karty (Karta, Ctecka, CasPlan, Povoleni, Smazano, Pozn) VALUES (?, ?, ?, ?, ?, ?)";
		$stmt = mysqli_prepare($connection, $sqlquery_novy_zaznam);
		mysqli_stmt_bind_param($stmt, "ssssss", $nova_karta, $nova_ctecka, $novy_cas_plan, $nove_povoleni, $nove_smazano, $nove_pozn);
		if(mysqli_stmt_execute($stmt)) {
			header('location: ./?v=cards');
		} else {
			$_SESSION['error_message'] = 'Error creating record: ' . mysqli_error($connection) . '!';
			header('location: ./?v=cards;f=alert');
			exit;
		}
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
			$_SESSION['error_message'] = 'Záznam nebyl nalezen!';
			header('location: ./?v=cards;f=alert');
			exit;
		}
	} else {
		$_SESSION['error_message'] = 'Error executing the query: ' . mysqli_error($connection) . '!';
		header('location: ./?v=cards;f=alert');
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
		$sqlquery_edit = "UPDATE Karty SET Karta = '$edit_karta', Ctecka = '$edit_ctecka', CasPlan = '$edit_cas_plan', Povoleni = '$edit_povoleni', Smazano = '$edit_smazano', Pozn = '$edit_pozn' WHERE cardid = $cardid";
		if(mysqli_query($connection, $sqlquery_edit)) {
			header('location: ./?v=cards');
		} else {
			$_SESSION['error_message'] = 'Error updating record: ' . mysqli_error($connection) . '!';
			header('location: ./?v=cards;f=alert');
			exit;
		}
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
		header('location: ./?v=cards');
	} else {
		$_SESSION['error_message'] = 'Error deleting record: ' . mysqli_error($connection) . '!';
		header('location: ./?v=cards;f=alert');
		exit;
	}
}
// ========== cast users
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
		mysqli_stmt_bind_param($stmt, "sss", $novy_user, $novy_md5, $nova_prava);
		if(mysqli_stmt_execute($stmt)) {
			header('location: ./?v=users');
		} else {
			$_SESSION['error_message'] = 'Error creating user: ' . mysqli_error($connection) . '!';
			header('location: ./?v=users;f=alert');
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
	mysqli_stmt_bind_param($stmt, "i", $id);
	if(mysqli_stmt_execute($stmt)) {
		$result_select = mysqli_stmt_get_result($stmt);
		if(mysqli_num_rows($result_select) > 0) {
			$row_select = mysqli_fetch_array($result_select);
		} else {
			$_SESSION['error_message'] = 'User not found!';
			header('location: ./?v=users;f=alert');
			exit;
		}
	} else {
		$_SESSION['error_message'] = 'Error executing the query: ' . mysqli_error($connection) . '!';
		header('location: ./?v=users;f=alert');
		exit;
	}
// editace zaznamu uzivatele
	if(isset($_POST['edit-uzivatel'])) {
		$id = $_POST['id'];
		$edit_user = $_POST['edit-user'];
		$edit_plain_password = $_POST['edit-md5'];
		$edit_md5 = md5($edit_plain_password);
		$edit_prava = $_POST['edit-prava'];
		$sqlquery_edit = "UPDATE users SET LogonName = '$edit_user', MD5 = '$edit_md5', rights = '$edit_prava' WHERE id = $id";
		if(mysqli_query($connection, $sqlquery_edit)) {
			header('location: ./?v=users');
		} else {
			$_SESSION['error_message'] = 'Error updating record: ' . mysqli_error($connection) . '!';
			header('location: ./?v=users;f=alert');
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
	mysqli_stmt_bind_param($stmt, "i", $id);
	if(mysqli_stmt_execute($stmt)) {
		header('location: ./?v=users');
	} else {
		$_SESSION['error_message'] = 'Error deleting user: ' . mysqli_error($connection) . '!';
		header('location: ./?v=users;f=alert');
		exit;
	}
}
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
// chybovy hlasky
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
			<li id="add-card"><a href="./?v=cards;s=past">New card</a></li>
<?php
	}
	if($key_view == $stat_view_array['u']) {
?>
			<li id="add-user"><a href="./?v=users;s=past">New user</a></li>
<?php
	}
?>
<?php
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
// ========== pohled login
if($key_view == $stat_view_array['l']) {
?>
		<h2>Login</h2>
		<p>Enter your login details, username and password.</p>
		<div id="form-container">
			<form action="<?php
echo $url;
?>" method="post" enctype="application/x-www-form-urlencoded" autocomplete="off">
				<label for="forusername">Username: </label><input type="text" id="forusername" name="username" required="required" autocapitalize="off">
				<label for="forpassword">Password: </label><input type="password" id="forpassword" name="password" required="required" autocapitalize="off">
				<label for=""></label><button type="submit" name="login">Login</button>
			</form>
		</div>
<?php
}
// ========== pohled home
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
// ========== pohled more
if($key_view == $stat_view_array['m']) {
?>
		<h2>More</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; More</p>
		<ul id="more">
			<li><strong id="more-title-first">Administration</strong></li>
			<li id="config"><a href="./?v=config">Config</a></li>
			<li id="running"><a href="./?v=running">Running</a></li>
			<li><strong>New items</strong></li>
			<li id="add-card"><a href="./?v=cards;s=past">New card</a></li>
			<li id="add-user"><a href="./?v=users;s=past">New user</a></li>
			<li><strong>Version</strong></li>
			<li><em id="more-version"><?php
echo date('y.m.di.H.s', $update_datetime);
?></em></li>
		</ul>
<?php
}
// ========== pohled access
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
<!--
						<th>Deleted</th>
// -->
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
//				echo '<td>' . $row_1['Smazano'] . '</td>';
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
// ========== pohled karty
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
<!--
						<th>cardid</th>
// -->
						<th class="th-7">Card</th>
						<th class="th-7">Reader</th>
						<th class="th-7">Timezone</th>
						<th class="th-7">Access</th>
<!--
						<th>Deleted</th>
// -->
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
//				echo '<td>' . $row_2['cardid'] . '</td>';
				echo '<td class="nowrap"><a href="./?v=cards;s=edit;i=' . $row_2['cardid'] . '">' . $row_2['Karta'] . '</a></td>';
				echo '<td class="td-centered">' . $row_2['Ctecka'] . '</td>';
				echo '<td class="td-centered">' . $row_2['CasPlan'] . '</td>';
				echo '<td class="td-centered">' . $row_2['Povoleni'] . '</td>';
//				echo '<td>' . $row_2['Smazano'] . '</td>';
				echo '<td>' . $row_2['Pozn'] . '</td>';
				echo '<td><a href="./?v=cards;s=edit;i=' . $row_2['cardid'] . '">Edit</a></td>';
				echo '<td><a href="./?v=cards;s=delete;i=' . $row_2['cardid'] . '" onclick="return confirm(\'Card - ' . $row_2['Karta'] . ' - will be marked as deleted!\');">Delete</a></td>';
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
// pohled karty - nova karta
if($key_view == $stat_view_array['c'] && $key_cond == $stat_cond_array['p'] && $key_cond != $stat_cond_array['e'] && $key_cond != $stat_cond_array['d']) {
?>
		<h2>New card</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; <a href="./?v=cards" onclick="return confirm('Are you sure you want to leave without saving?');">Cards</a> &rsaquo; New card</p>
			<div id="form-container">
			<form action="<?php
echo $url;
?>" method="post" enctype="application/x-www-form-urlencoded" autocomplete="off">
				<input type="hidden" name="nove-smazano" value="0">
				<label for="fornovakarta">Card <span class="required">*</span></label><input type="text" id="fornovakarta" name="nova-karta" value="<?php
if(!empty($key_info)) {
	$key_info = str_replace('-', ' ', $key_info);
	echo $key_info;
}
?>" required="required">
				<label for="fornovactecka">Reader <span class="required">*</span></label><input type="text" id="fornovactecka" name="nova-ctecka" value="" required="required">
				<label for="fornovycasplan">Timezone <span class="required">*</span></label><input type="text" id="fornovycasplan" name="novy-cas-plan" value="" required="required">
				<label for="fornovepovoleni">Access <span class="required">*</span></label><input type="text" id="fornovepovoleni" name="nove-povoleni" value="" required="required">
				<label for="fornovepozn">Description</label><input type="text" id="fornovepozn" name="nove-pozn" value="" required="required">
				<label for=""></label><button type="submit" name="pridat-karta">Create</button>
			</form>
		</div>
		<p><span class="required">* Required</span></p>
		<p><a href="./?v=cards" onclick="return confirm('Are you sure you want to leave without saving?');">&lsaquo; Back on Cards</a></p>
<?php
}
// pohled karty - editace karty
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
				<label for="foreditkarta">Card (read only)</label><input type="text" id="foreditkarta" name="edit-karta" value="<?php
echo $row_select['Karta'];
?>" readonly="readonly">
				<label for="foreditctecka">Reader <span class="required">*</span></label><input type="text" id="foreditctecka" name="edit-ctecka" value="<?php
echo $row_select['Ctecka'];
?>" required="required">
				<label for="foreditcasplan">Timezone <span class="required">*</span></label><input type="text" id="foreditcasplan" name="edit-cas-plan" value="<?php
echo $row_select['CasPlan'];
?>" required="required">
				<label for="foreditpovoleni">Access <span class="required">*</span></label><input type="text" id="foreditpovoleni" name="edit-povoleni" value="<?php
echo $row_select['Povoleni'];
?>" required="required">
				<label for="foreditsmazano">Rights <span class="required">*</span></label><select id="foreditsmazano" name="edit-smazano" required="required">
					<option value="">&ndash;</option>
<?php
	$row_selected = $row_select['Smazano'];
	if($row_selected == '0') {
?>
					<option value="0" selected="selected">Enabled</option>
					<option value="1">Delete</option>
<?php
	} elseif($row_selected == '1') {
?>
					<option value="0">Enable</option>
					<option value="1" selected="selected">Deleted</option>
<?php
	} else {
?>
					<option value="0">Enable</option>
					<option value="1">Delete</option>
<?php
	}
?>
				</select>
				<label for="foreditpozn">Description</label><input type="text" id="foreditpozn" name="edit-pozn" value="<?php
echo $row_select['Pozn'];
?>">
				<label for=""></label><button type="submit" name="edit-zaznam" onclick="return confirm('Are you sure you want to edit this listing?');">Commit changes</button>
			</form>
		</div>
		<p><span class="required">* Required</span></p>
		<p><a href="./?v=cards" onclick="return confirm('Are you sure you want to leave without saving?');">&lsaquo; Back on Cards</a></p>
<?php
}
// ========== pohled users
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
<!--
						<th>id</th>
// -->
						<th class="th-3">User</th>
<!--
						<th>MD5</th>
						<th>rights</th>
// -->
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
//				echo '<td>' . $row_4['id'] . '</td>';
				echo '<td>' . $row_4['LogonName'] . '</td>';
//				echo '<td>' . $row_4['MD5'] . '</td>';
//				echo '<td>' . $row_4['rights'] . '</td>';
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
// pohled users - novy user
if($key_view == $stat_view_array['u'] && $key_cond == $stat_cond_array['p'] && $key_cond != $stat_cond_array['e'] && $key_cond != $stat_cond_array['d']) {
	?>
		<h2>New user</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; <a href="./?v=users" onclick="return confirm('Are you sure you want to leave without saving?');">Users</a> &rsaquo; New user</p>
		<div id="form-container">
			<form action="<?php
echo $url;
?>" method="post" enctype="application/x-www-form-urlencoded" autocomplete="off">
				<label for="fornovyuser">Username <span class="required">*</span></label><input type="text" id="fornovyuser" name="novy-user" value="" required="required">
				<label for="fornovymd5">Password <span class="required">*</span></label><input type="password" id="fornovymd5" name="novy-md5" value="" required="required" onkeyup="checkPasswordStrength();">
				<div></div><div id="password-strength-status"></div>
				<input type="hidden" name="nova-prava" value="255">
				<div></div><button type="submit" name="novy-uzivatel">Create</button>
			</form>
		</div>
		<p><span class="required">* Required</span></p>
		<p><a href="./?v=users" onclick="return confirm('Are you sure you want to leave without saving?');">&lsaquo; Back on Users</a></p>
<?php
}
// pohled users - editace user
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
				<label for="foredituser">Username (read only)</label><input type="text" id="foredituser" name="edit-user" value="<?php
echo $row_select['LogonName'];
?>" readonly="readonly">
				<label for="foreditmd5">Password (change only) <span class="required">*</span></label><input type="password" id="foreditmd5" name="edit-md5" value="" required="required" onkeyup="checkPasswordStrength();">
				<div></div><div id="password-strength-status"></div>
				<label for="foreditprava">Rights</label><select id="foreditprava" name="edit-prava" required="required">
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
				<div></div><button type="submit" name="edit-uzivatel">Commit changes</button>
			</form>
		</div>
		<p><span class="required">* Required</span></p>
		<p><a href="./?v=users" onclick="return confirm('Are you sure you want to leave without saving?');">&lsaquo; Back on Users</a></p>
<?php
}
// ========== pohled configdu
if($key_view == $stat_view_array['o'] && $key_cond != $stat_cond_array['e']) {
?>
		<h2>Configuration</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; Configuration</p>
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
<!--
						<th>id</th>
// -->
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
//			echo '<td>' . $row_5['id'] . '</td>';
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
// pohled configdu - editace konfigurace
if($key_view == $stat_view_array['o'] && $key_cond == $stat_cond_array['e']) {
?>
		<h2>Edit configuration</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; <a href="./?v=config" onclick="return confirm('Are you sure you want to leave without saving?');">Configuration</a> &rsaquo; Edit configuration</p>
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
				<label for="foreditproperty">Property</label><input type="text" id="foreditproperty" name="edit-property" value="<?php
echo $row_5['property'];
?>" readonly="readonly">
				<label for="foreditvalue">Value <span class="required">*</span></label><input type="text" id="foreditvalue" name="edit-value" value="<?php
echo $row_5['value'];
?>" title="Example <?php
echo $row_5['value'];
?>" required="required">
				<input type="hidden" name="configdu-valid" value="<?php
echo $row_5['regex'];
?>">
				<label for=""></label><button type="submit" name="edit-config" onclick="return confirm('Are you sure you want to edit this listing?');">Commit changes</button>
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
// ========== pohled running
if($key_view == $stat_view_array['r']) {
?>
		<h2>Running</h2>
		<p><a href="./?v=home">Dashboard</a> &rsaquo; Running</p>
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
<!--
						<th>id</th>
// -->
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
//				echo '<td>' . $row_3['id'] . '</td>';
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
?>
	</div>
	<div id="footer">
<?php
// jiny pohled nez login
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
?>
	</div>
</div>
<script src="./js/meritaccess-body.min.js"></script>
</body>
</html><?php
// ukonceni spojeni
mysqli_close($connection);
?>