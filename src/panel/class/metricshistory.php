<?php

require_once(__DIR__."/metrics.php");

/**
 * This class saves daily metrics in a alternc_metrics table 
 * and keep 90 days of daily, and 5 years of monthly statistics
 */
class metricshistory {

    var $daily = 90; // keep that many daily values. at least 31 values are necessary for monthly aggregates.
    var $monthly= 5*12; // keep that many monthly aggregates. 

    private $mdb=null; // handle to the DB alternc_metrics
    var $conf=[ "debug" => true ] ;


    public function __construct() {
        global $db,$L_MYSQL_DATABASE,$L_MYSQL_HOST,$L_MYSQL_LOGIN, $L_MYSQL_PWD;
        $this->mdb = new DB_Sql($L_MYSQL_DATABASE."_metrics", $L_MYSQL_HOST, $L_MYSQL_LOGIN, $L_MYSQL_PWD);
    }
    
    /** 
     * function called at install time to install the metric history 
     * database and tables if needed. should be idem-potent
     */
    public static function install() {
        global $db,$L_MYSQL_DATABASE,$L_MYSQL_HOST,$L_MYSQL_LOGIN, $L_MYSQL_PWD;

        $db->query("CREATE DATABASE ".$L_MYSQL_DATABASE."_metrics");
        $mdb = new DB_Sql($L_MYSQL_DATABASE."_metrics", $L_MYSQL_HOST, $L_MYSQL_LOGIN, $L_MYSQL_PWD);
        
        $mdb->query("SHOW TABLES LIKE 'metrics_names';");
        if (!$mdb->next_record()) {
            $mdb->query("
      CREATE TABLE `metrics_names` (
      `id` bigint(20) unsigned AUTO_INCREMENT,
      `name` varchar(128) NOT NULL,
      `type` varchar(32) NOT NULL,
      `account_id` bigint(20) unsigned DEFAULT NULL,
      `domain_id` bigint(20) unsigned DEFAULT NULL,
      `object_id` bigint(20) unsigned DEFAULT NULL,
      `account` varchar(255) DEFAULT NULL,
      `domain` varchar(255) DEFAULT NULL,
      `object` varchar(255) DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE metricid (`name`,`object_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
      ");
            echo date("Y-m-d H:i:s")." metricshistory: installed table metrics_names\n";
        }

        $mdb->query("SHOW TABLES LIKE 'metrics_history_values';");
        if (!$mdb->next_record()) {
            $mdb->query("
      CREATE TABLE `metrics_history_values` (
      `id` bigint(20) unsigned,
      `mdate` DATE,
      `value` bigint(20) unsigned,
      PRIMARY KEY (`mdate`,`id`),
      KEY `id` (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
      ");
            echo date("Y-m-d H:i:s")." metricshistory: installed table metrics_history_values\n";
        }

        $mdb->query("SHOW TABLES LIKE 'metrics_history_aggregates';");
        if (!$mdb->next_record()) {
            $mdb->query("
      CREATE TABLE `metrics_history_aggregates` (
      `id` bigint(20) unsigned,
      `mdate` DATE,
      `min` bigint(20) unsigned,
      `sum` bigint(20) unsigned,
      `count` bigint(20) unsigned,
      `max` bigint(20) unsigned,
      PRIMARY KEY (`mdate`,`id`),
      KEY `id` (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
      ");
            echo date("Y-m-d H:i:s")." metricshistory: installed table metrics_history_aggregates\n";
        }

        // if no user has the quota "metrics" enabled, enable it for everyone
        $db->query("SELECT COUNT(*) AS ct FROM quotas WHERE name='metrics';");
        $db->next_record();
        if ($db->Record['ct']==0) {
            echo date("Y-m-d H:i:s")." metricshistory: no user can access metrics, allowing it to everyone\n";
            $db->query("INSERT INTO quotas (uid,name,total) SELECT uid,'metrics',1 FROM membres;");
        }
        
} // install


    /**
     * collect metrics from yesterday that have been just computed, 
     * and store them in the metrics_history_values for ~90days.
     */
    function daily_collect() {
        global $db;
        $m=new metrics();
        // get all metrics and dereference them 
        if ($this->conf["debug"]) echo date("Y-m-d H:i:s")." metricshistory: collecting metrics ...\n";
        $all = $m->get([],["domain","account","object"]);
        
        if ($this->conf["debug"]) echo date("Y-m-d H:i:s")." metricshistory: filling metrics_names ...\n";
        $sql=""; $count=0;
        foreach($all as $one) {
            if (!$sql || strlen($sql)>1048576) { // should be a bit less than max_packet_size for MySQL ...
                if ($sql && $this->conf["debug"]) echo date("Y-m-d H:i:s")." metricshistory: collected $count metric names\n";
                $this->mdb->query($sql);
                $sql="INSERT IGNORE INTO metrics_names (name,type,account_id,domain_id,object_id,account,domain,object) VALUES ";
                $first=true;
            }
            if (!$first) $sql.=",";
            $sql.=" ('".addslashes($one["name"])."','".addslashes($one["type"])."', ".intval($one["account_id"]).",".intval($one["domain_id"]).",".intval($one["object_id"]).", '".addslashes($one["account"])."','".addslashes($one["domain"])."','".addslashes($one["object"])."')";
            $first=false;
            $count++;
        }
        if (!$first) $this->mdb->query($sql);
        if ($this->conf["debug"]) echo date("Y-m-d H:i:s")." metricshistory: collected $count metric names total\n";

        // now fill the values: 
        $date=date("Y-m-d",time()-86400);
        if ($this->conf["debug"]) echo date("Y-m-d H:i:s")." metricshistory: filling metrics_values ...\n";
        $sql=""; $count=0;
        foreach($all as $one) {
            if (!$sql || strlen($sql)>1048576) { // should be a bit less than max_packet_size for MySQL ...
                if ($sql && $this->conf["debug"]) echo date("Y-m-d H:i:s")." metricshistory: collected $count metric values\n";
                $this->mdb->query($sql);
                $sql="INSERT INTO metrics_history_values (id,mdate,value) VALUES ";
                $first=true;
            }
            if ($one["object_id"]) {
                $oin="AND object_id=".intval($one["object_id"]);
            } else {
                $oin="";
            }
            $this->mdb->query("SELECT id FROM metrics_names WHERE name='".addslashes($one["name"])."' $oin;");
            if ($this->mdb->next_record()) {
                if (!$first) $sql.=",";
                $sql.=" (".intval($this->mdb->Record["id"]).",'$date', ".$one["value"].")";
                $first=false;
                $count++;
            } else {
                if ($this->conf["debug"]) echo date("Y-m-d H:i:s")." metricshistory: metric not found, weird :/ \n";
                echo str_replace("\n"," ",print_r($one,true))."\n";
            }
        }
        if (!$first) $this->mdb->query($sql);

    }



    /**
     * collect metrics from last month, compute aggregeates
     * and store them in the metrics_history_aggregates for 5 years
     * you should launch this daily too: it does nothing if nothing is to be made (idempotent)
     */
    function monthly_collect() {
        if (date("j")>10) return; // only try to do anything on the first 10 days of the month...

        $startdate=date("Y-m",time()-86400*20)."-01"; // this is the first day of *last month* 
        $dayslastmonth=date("t",time()-86400*20);
        $enddate=date("Y-m",time()-86400*20)."-".$dayslastmonth; // this is the last day of *last month* 

        $this->mdb->query("SELECT COUNT(*) already FROM metrics_history_aggregates WHERE mdate = '$startdate';"); 
        $this->mdb->next_record();
        if ($this->mdb->Record["already"]>0) 
            return; // already computed 

        if ($this->conf["debug"]) echo date("Y-m-d H:i:s")." metricshistory: will aggregates last month data\n";

        // compute aggregate for last month, using SQL power \o/
        $this->mdb->query("
  INSERT INTO metrics_history_aggregates (`id`,`mdate`,`min`,`sum`,`count`,`max`) 
  SELECT id, '$startdate', min(value), sum(value),count(*), max(value) FROM metrics_history_values 
  WHERE mdate BETWEEN '$startdate' AND '$enddate' GROUP BY id;
");
        $aff=$this->mdb->affected_rows();
        if ($this->conf["debug"]) echo date("Y-m-d H:i:s")." metricshistory: inserted $aff rows in the aggregates table for $startdate\n";
        
    }    

    /** 
     * cleanup old data according to configured values 
     */
    function cleanup() {
        $this->mdb->query("DELETE FROM metrics_history_values WHERE mdate < DATE_SUB(NOW(), INTERVAL ".$this->daily." DAY);");
        $aff=$this->mdb->affected_rows();
        if ($this->conf["debug"]) echo date("Y-m-d H:i:s")." metricshistory: deleted $aff rows from daily data\n";
        $this->mdb->query("DELETE FROM metrics_history_aggregates WHERE mdate < DATE_SUB(NOW(), INTERVAL ".$this->monthly." MONTH);");
        $aff=$this->mdb->affected_rows();
        if ($this->conf["debug"]) echo date("Y-m-d H:i:s")." metricshistory: deleted $aff rows from aggregates data\n";
    }


} // metricshistory
