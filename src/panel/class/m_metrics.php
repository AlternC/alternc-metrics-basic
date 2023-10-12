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
 Purpose of file: Manage the display of metrics data to users.
 ----------------------------------------------------------------------
*/

class m_metrics {


    /* ----------------------------------------------------------------- */
    /** 
     * Hook called by AlternC to tell which main menu element need adding for this module.
     */ 
    function hook_menu() {
        global $quota;
        if ($quota->cancreate("metrics")) {
            $obj = array(
                'title'       => _("Metrics"),
                'ico'         => 'images/logs.png',
                'link'        => 'metrics_list.php',
                'pos'         => 140,
            ) ;
            return $obj;
        }
        return false;
    }

    private $mdb=null;

    private function connect() {
        global $db,$L_MYSQL_DATABASE,$L_MYSQL_HOST,$L_MYSQL_LOGIN, $L_MYSQL_PWD;
        if (is_null($this->mdb)) {
            $this->mdb = new DB_Sql($L_MYSQL_DATABASE."_metrics", $L_MYSQL_HOST, $L_MYSQL_LOGIN, $L_MYSQL_PWD);        
        }
    }
    

    /* ----------------------------------------------------------------- */
    /** Get the TOP20 for the last value of a metric
     */
    public function get_top($name,$limit=20) {
        $limit=max(1,intval($limit));
        $this->connect();
        $this->mdb->query("SELECT id FROM metrics WHERE name='".addslashes($name)."';");
        if (!$this->mdb->next_record()) {
            $this->error=_("Can't find this metric");
            return false;
        }
        $mid=$this->mdb->Record["id"];
        // now get the latest metrics of that name:
        $this->mdb->query("SELECT max(mdate) AS d FROM metrics_history_values;");
        $this->mdb->next_record();
        $date=$this->mdb->Record["d"];

        $this->mdb->query("SELECT v.*,n.object,n.domain,n.account FROM metrics_history_values v, metrics_names n WHERE n.id=v.id AND v.mid=".$mid." AND v.mdate='$date' ORDER BY v.value DESC LIMIT ".$limit.";");
        while($this->mdb->next_record()) {
            $data[]=$this->mdb->Record;
        }
        return ["name"=>$name,"id"=>$mid,"date"=>$date,"data"=>$data];
    }


    /* ----------------------------------------------------------------- */
    /** Quota name
     */
    function hook_quota_names() {
        return array("metrics"=>_("Access to metrics"));
    }

    
    /* ----------------------------------------------------------------- */
    /** Returns the quota for the current account as an array
     * @return array an array with used (key 'u') and totally available (key 't') quota for the current account.
     * or FALSE if an error occured
     * @access private
     */ 
    function hook_quota_get() {
        global $msg,$cuid,$db;        
        $msg->log("metrics","getquota");
        $q=Array("name"=>"metrics", "description"=>_("Access to metrics"), "used"=>0);
        /* // as of now we don't manage the number of lists via alternc, so the "used" quota should always be 0
        $db->query("SELECT COUNT(*) AS cnt FROM sympa WHERE uid='$cuid'");
        if ($db->next_record()) {
            $q['used']=($db->f("cnt")!=0);
        }
        */
        return $q;
    }




} /* Class m_metrics */

