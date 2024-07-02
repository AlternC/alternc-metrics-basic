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
 Purpose of file: Show informations on a metric for a user or an admin
 ----------------------------------------------------------------------
*/

require_once("../class/config.php");

if (!$quota->cancreate("metrics")) {
    header("Location: /");
    exit();
}

include_once("head.php");

?>
<h3><?php __("Showing a metric"); ?></h3>
<hr id="topbar"/>
<br />
 <?php 
    echo $msg->msg_html_all();

require_once(__DIR__."/../class/metricshistory.php");
require_once(__DIR__."/../class/metrics.php");
$mh=new metricshistory();
$m=new metrics();

$all = $m->info();
$modules = $m->modules();

$metric = trim(strtolower($_GET["m"]));
$limit = trim(strtolower($_GET["limit"]));
if (empty($limit)) {
       $limit = 100;
}

// get the metric ids & cardinality 
// the default = get the LAST dated value and get the top 20 by descreasing value.
$top = $metrics->get_top($metric,$limit);

// TODO : show units (Kb/Mb/Gb ?) 
// show proper domains for subdomains-metrics

?>
<h3><?php 
$all = $m->info();
foreach($all as $name=>$attr) 
    if ($name==$metric) { echo $attr["description"]; 
 ?></h3>
<p><?php
        printf(_("Metric dated %s"),$top["date"]); 
        $attrs=$attr;
}

?></p>

        <table class="tlist" style="clear:both;">
            <tr>
                <th></th>
                <th><?php __("Account"); ?></th>
                <th><?php __("Domain"); ?></th>
                <th><?php __("Object"); ?></th>
                <th><?php __("Value"); ?></th>
            </tr>
<?php 
$i=1;
foreach($top["data"] as $one) {
?>
<tr class="lst<?php echo $lst; $lst=3-$lst;?>">
    <td><?php echo $i++; ?></td>
    <td><?php echo $one["account"]; ?></td>
    <td><?php echo $one["domain"]; ?></td>
    <td><?php echo $one["object"]; ?></td>
    <td><?php 
if ($attrs["unit"]=="bytes") {
    echo format_size($one["value"]);
} else {
    echo $one["value"]; 
}

?></td>
</tr>
<?php } ?>
</table>

<?php include_once("foot.php"); ?>
