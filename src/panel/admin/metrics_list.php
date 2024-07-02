<?php
/*
 ----------------------------------------------------------------------
 AlternC - Web Hosting System
 Copyright (C) 2000-2022 by the AlternC Development Team.
 https://alternc.org/
 ----------------------------------------------------------------------
 LICENSE

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License (GPL)
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 To read the license please visit http://www.gnu.org/copyleft/gpl.html
 ----------------------------------------------------------------------
 Purpose of file: Show the list of available metrics to users
 ----------------------------------------------------------------------
*/

require_once("../class/config.php");

if (!$quota->cancreate("metrics")) {
    header("Location: /");
    exit();
}

include_once("head.php");

?>
<h3><?php __("Available metrics for your account"); ?></h3>
<hr id="topbar"/>
<br />
 <?php 
    echo $msg->msg_html_all();

/*
if ($admin->enabled) {
?>
 <h3><?php __("Metrics for administrators"); ?></h3>
<ul>
 <li class="lst"><a href="metrics_adm.php"><?php __("General server statistics"); ?></a></li>
</ul>
 <h3><?php __("Metrics for your account"); ?></h3>
<?php } ?>
<ul>
 <li class="lst"><a href="metrics_my.php"><?php __("General statistics for my account"); ?></a></li>
</ul>
<?php
*/

require_once(__DIR__."/../class/metricshistory.php");
require_once(__DIR__."/../class/metrics.php");
$mh=new metricshistory();
$m=new metrics();

/* example of getting ONE metric by name, filtering by domains, dereferencing everything:
echo "<pre>";
$test = $m->get(["names" => ["dom_subdomain_count"] ],["object", "domain", "account"]);
 print_r($test); echo "</pre>";
*/

$all = $m->info();
$modules = $m->modules(); 
ksort($all);
$last="";
$first=true;

foreach($all as $name => $attr) {
    list($cat)=explode("_",$name,2);
    if ($cat!=$last) {
        if (!$first) echo "</ul>";
        $last=$cat;
        echo "<h3>".$modules[$cat]."</h3>";
        echo "<ul id=\"adm_panel\">";
    }
    echo "<li class=\"lst\"><a href=\"metrics_details.php?m=".$name."&limit=100000\">"._($attr["description"])."</a></li>";
    $first=false;
}
echo "</ul>";

?>

<?php include_once("foot.php"); ?>
