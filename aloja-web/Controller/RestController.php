<?php

namespace alojaweb\Controller;

use alojaweb\inc\Utils;
use alojaweb\inc\DBUtils;

class RestController extends AbstractController
{
    public function benchExecutionsDataAction()
    {
        $show_in_result = array(
            'id_exec' => 'ID',
            'bench' => 'Benchmark',
            'exe_time' => 'Exe Time',
            'exec' => 'Exec Conf',
            'cost' => 'Running Cost $',
            'net' => 'Net',
            'disk' => 'Disk',
            'maps' => 'Maps',
            'iosf' => 'IO SFac',
            'replication' => 'Rep',
            'iofilebuf' => 'IO FBuf',
            'comp' => 'Comp',
            'blk_size' => 'Blk size',
            'id_cluster' => 'Cluster',
            'files' => 'Files',
            'prv' => 'PARAVER',
            //'version' => 'Hadoop v.',
            'init_time' => 'End time',
        );

        try {
            $dbUtils = $this->container->getDBUtils();
            $exec_rows = $dbUtils->get_execs();

            if (count($exec_rows) > 0) {
                $jsonData = Utils::generateJSONTable($exec_rows, $show_in_result);
            } else {
                throw new \Exception("No results for query!");
            }

            header('Content-Type: application/json');
            ob_start('ob_gzhandler');
            echo $jsonData;
        } catch (Exception $e) {
            $noData = array();
            for($i = 0; $i<=sizeof($show_in_result); ++$i)
            	$noData[] = 'error';
            
            echo json_encode(array('aaData' => array($noData)));
        }
    }

    public function countersDataAction()
    {
        $db = $this->container->getDBUtils();
        try {
            //check the URL
            $execs = Utils::get_GET_execs();

            if (!($type = Utils::get_GET_string('type')))
                $type = 'SUMMARY';

            $join = "JOIN execs e using (id_exec) WHERE e.valid = TRUE AND JOBNAME NOT IN
        ('TeraGen', 'random-text-writer', 'mahout-examples-0.7-job.jar', 'Create pagerank nodes', 'Create pagerank links')".
                ($execs ? ' AND id_exec IN ('.join(',', $execs).') ':''). " LIMIT 10000";

            if ($type == 'SUMMARY') {
                $query = "SELECT e.bench, exe_time, c.id_exec, c.JOBID, c.JOBNAME, c.SUBMIT_TIME, c.LAUNCH_TIME,
                c.FINISH_TIME, c.TOTAL_MAPS, c.FAILED_MAPS, c.FINISHED_MAPS, c.TOTAL_REDUCES, c.FAILED_REDUCES
                FROM JOB_details c $join";
            } elseif ($type == 'MAP') {
                $query = "SELECT e.bench, exe_time, c.id_exec, JOBID, JOBNAME, c.SUBMIT_TIME, c.LAUNCH_TIME,
                c.FINISH_TIME, c.TOTAL_MAPS, c.FAILED_MAPS, c.FINISHED_MAPS, `Launched map tasks`,
                `Data-local map tasks`,
                `Rack-local map tasks`,
                `Spilled Records`,
                `Map input records`,
                `Map output records`,
                `Map input bytes`,
                `Map output bytes`,
                `Map output materialized bytes`
                FROM JOB_details c $join";
            } elseif ($type == 'REDUCE') {
                $query = "SELECT e.bench, exe_time, c.id_exec, c.JOBID, c.JOBNAME, c.SUBMIT_TIME, c.LAUNCH_TIME,
                c.FINISH_TIME, c.TOTAL_REDUCES, c.FAILED_REDUCES,
                `Launched reduce tasks`,
                `Reduce input groups`,
                `Reduce input records`,
                `Reduce output records`,
                `Reduce shuffle bytes`,
                `Combine input records`,
                `Combine output records`
                FROM JOB_details c $join";
            } elseif ($type == 'FILE-IO') {
                $query = "SELECT e.bench, exe_time, c.id_exec, c.JOBID, c.JOBNAME, c.SUBMIT_TIME, c.LAUNCH_TIME,
                c.FINISH_TIME,
                `SLOTS_MILLIS_MAPS`,
                `SLOTS_MILLIS_REDUCES`,
                `SPLIT_RAW_BYTES`,
                `FILE_BYTES_WRITTEN`,
                `FILE_BYTES_READ`,
                `HDFS_BYTES_WRITTEN`,
                `HDFS_BYTES_READ`,
                `Bytes Read`,
                `Bytes Written`
                FROM JOB_details c $join";
            } elseif ($type == 'DETAIL') {
                $query = "SELECT e.bench, exe_time, c.* FROM JOB_details c $join";
            } elseif ($type == 'TASKS') {
                $query = "SELECT e.bench, exe_time, j.JOBNAME, c.* FROM JOB_tasks c
                JOIN JOB_details j USING(id_exec, JOBID) $join ";
            } else {
                throw new \Exception('Unknown type!');
            }

            $exec_rows = $db->get_rows($query);

            if (count($exec_rows) > 0) {

                $show_in_result_counters = array(
                    'id_exec'   => 'ID',
                    //'job_name'  => 'Job Name',
                    //'exe_time' => 'Total Time',

                    'JOBID'     => 'JOBID',
                    'bench'     => 'Bench',
                    'JOBNAME'   => 'JOBNAME',
                );

                $show_in_result_counters = Utils::generate_show($show_in_result_counters, $exec_rows, 4);
                $jsonData = Utils::generateJSONTable($exec_rows, $show_in_result_counters, 0, 'COUNTER');

                header('Content-Type: application/json');
                echo $jsonData;
                //         if (count($exec_rows) > 10000) {
                //             $message .= 'WARNING, large resulset, please limit the query! Rows: '.count($exec_rows);
                //         }

            } else {
                $noData = array();
                for($i = 0; $i<18; ++$i)
                    $noData[] = 'No Data found';

                ob_start('ob_gzhandler');
                echo json_encode(array('aaData' => $noData));
            }

        } catch (Exception $e) {
            $noData = array();
            for($i = 0; $i<=sizeof($show_in_result); ++$i)
            	$noData[] = 'error';
            
            echo json_encode(array('aaData' => array($noData)));
        }
    }

    public function export2prvAction()
    {
        ini_set('memory_limit', '256M');
        error_reporting(512);

        try {
            $dbUtils = $this->container->getDBUtils();
            if (Utils::get_GET_int('id_exec')) {
                $id_exec = Utils::get_GET_string('id_exec');
            } else {
                throw new \Exception('id_execution not set!');
            }

            $exec_name = $dbUtils->get_exec_details($id_exec, 'exec');
            $exec_name = str_replace('/', '_', $exec_name);

            $dir = '/tmp/prv';
            $zip_file = "$exec_name.zip";

            $full_name = "$dir/$zip_file";

            //check if it needs to be created
            if (!(file_exists($full_name) && is_readable($full_name) && file_exists($full_name))) {
                $query = 'SELECT
                    concat(
                    "2:",
                    substring(host, -1),
                    ":1:",
                    substring(host, -1),
                    ":1:",
                    (unix_timestamp(date) -
                    (select unix_timestamp(min(date)) FROM SAR_cpu t WHERE id_exec = "'.$id_exec.'"))*1000000000,
                    ":2001:",round(AVG(`%user`)),
                    ":2002:",round(AVG(`%system`)),
                    ":2003:",round(AVG(`%steal`)),
                    ":2004:",round(AVG(`%iowait`)),
                    ":2005:",round(AVG(`%nice`))
                    ) prv
                    FROM SAR_cpu t WHERE id_exec = "'.$id_exec.'"
                    GROUP BY date, host ORDER by date, host;';

                $prv_rows = $dbUtils->get_rows($query);

                if (!isset($prv_rows)) throw new \Exception('No data returned!');

                $query_job_history = 'select time, maps, reduce from JOB_job_history where id_exec = "'.$id_exec.'" ORDER by time';

                $job_history_rows = $dbUtils->get_rows($query_job_history);

                $test = "count ".count($prv_rows)." count2 ".count($job_history_rows);

                if ($job_history_rows) {
                    $key_job_history = 0;
                    $current_time = 0; //skip first second
                    foreach ($prv_rows as $key_prv_row=>$prv_row) {
                        $parts = explode(':', $prv_row['prv']);

                        //only one time per time and group of hosts
                        if (!$current_time || $parts[5] != $current_time) {
                            $current_time = $parts[5];
                            if (isset($job_history_rows[$key_job_history])) {
                                $prv_rows[$key_prv_row]['prv'] =
                                $prv_rows[$key_prv_row]['prv'].
                                ":1001:".round($job_history_rows[$key_job_history]['maps']).
                                ":1002:".round($job_history_rows[$key_job_history]['reduce'])
                                ;
                            }
                            $key_job_history++;
                        }
                    }
                }

                //get the ending time
                $end = end($prv_rows)['prv'];
                $end = explode(':', $end);

                $header = "#Paraver (".date('d/m/y \a\t H:i')."):".$end[5].":4(1,1,1,1):1:4(1:1,1:2,1:3,1:4),0";

                $row_file =
                'LEVEL CPU SIZE 4
CPU-MASTER
CPU-SLV1
CPU-SLV2
CPU-SLV3

LEVEL APPL SIZE 1
'.$exec_name.'

TASK LEVEL SIZE 4
JT+NN
TT1+DN1
TT2+DN2
TT3+DN3

LEVEL NODE SIZE 4
MASTER-NODE
SLAVE-NODE-1
SLAVE-NODE-2
SLAVE-NODE-3

LEVEL THREAD SIZE 4
THREAD-JT+NN
THREAD-TT1+DN1
THREAD-TT2+DN2
THREAD-TT3+DN3';

                $pcf_file =
                'DEFAULT_OPTIONS

LEVEL               NODE
UNITS               NANOSEC
LOOK_BACK           100
SPEED               1
FLAG_ICONS          ENABLED
NUM_OF_STATE_COLORS 1000
YMAX_SCALE          37


DEFAULT_SEMANTIC

THREAD_FUNC          State As Is


STATES
0    Idle
1    Running
2    Not created
3    Waiting a message
4    Blocking Send
5    Synchronization
6    Test/Probe
7    Scheduling and Fork/Join
8    Wait/WaitAll
9    Blocked
10    Immediate Send
11    Immediate Receive
12    I/O
13    Group Communication
14    Tracing Disabled
15    Others
16    Send Receive
17    Memory transfer

STATES_COLOR
0    {117,195,255}
1    {0,0,255}
2    {255,255,255}
3    {255,0,0}
4    {255,0,174}
5    {179,0,0}
6    {0,255,0}
7    {255,255,0}
8    {235,0,0}
9    {0,162,0}
10    {255,0,255}
11    {100,100,177}
12    {172,174,41}
13    {255,144,26}
14    {2,255,177}
15    {192,224,0}
16    {66,66,66}
17    {255,0,96}

EVENT_TYPE
6   1001        Number of running Maps
6   1002        Number of running Reduces

EVENT_TYPE
6   2001        CPU Usage - User (%)
6   2002        CPU Usage - System (%)
6   2003        CPU Usage - Steal (%)
6   2004        CPU Usage - IOwait (%)
6   2005        CPU Usage - Nice (%)

EVENT_TYPE
6   3001        System Load Avg-1min
6   3001        System Load Avg-5min
6   3001        System Load Avg-15min

EVENT_TYPE
6   4001        Mem KB used
6   4002        Mem KB free

EVENT_TYPE
6   5001        Page faults per second
6   5002        Major Page faults per second
6   5003        Pages freed per second



GRADIENT_COLOR
0    {0,255,2}
1    {0,244,13}
2    {0,232,25}
3    {0,220,37}
4    {0,209,48}
5    {0,197,60}
6    {0,185,72}
7    {0,173,84}
8    {0,162,95}
9    {0,150,107}
10    {0,138,119}
11    {0,127,130}
12    {0,115,142}
13    {0,103,154}
14    {0,91,166}


GRADIENT_NAMES
0    Gradient 0
1    Grad. 1/MPI Events
2    Grad. 2/OMP Events
3    Grad. 3/OMP locks
4    Grad. 4/User func
5    Grad. 5/User Events
6    Grad. 6/General Events
7    Grad. 7/Hardware Counters
8    Gradient 8
9    Gradient 9
10    Gradient 10
11    Gradient 11
12    Gradient 12
13    Gradient 13
14    Gradient 14


EVENT_TYPE
9    40000018    Tracing mode:
VALUES
1      Detailed
2      CPU Bursts';

                $prv_file = $header."\n";
                foreach ($prv_rows as $prv_row) {
                    $prv_file .= $prv_row['prv']."\n";
                }

                //create the file
                if (!is_dir("$dir/$exec_name/")) {
                    mkdir("$dir/$exec_name/", 0777, true);
                }
                file_put_contents($dir."/$exec_name/$exec_name.pcf", $pcf_file);
                file_put_contents($dir."/$exec_name/$exec_name.row", $row_file);
                file_put_contents($dir."/$exec_name/$exec_name.prv", $prv_file);

                if (!exec("cd $dir &&  zip -r $zip_file $exec_name && rm -rf $exec_name") || !file_exists("$dir/$zip_file")) {
                    throw new \Exception('Could not create .zip');
                }
            }

            //donwload the file
            if (file_exists($full_name) && is_readable($full_name) && file_exists($full_name)) {
                header("Content-Disposition: attachment; filename=".basename(str_replace(' ', '_', $full_name)));
                header("Content-Type: application/force-download");
                header("Content-Type: application/octet-stream");
                header("Content-Type: application/download");
                header("Content-Description: File Transfer");
                header("Content-Length: " . filesize($full_name));
                flush(); // this doesn't really matter.

                $fp = fopen($full_name, "r");
                while (!feof($fp)) {
                    echo fread($fp, 65536);
                    flush(); // this is essential for large downloads
                }
                fclose($fp);
                exit;
            } else {
                throw new \Exception('Could not read zip file');
            }

        } catch (\Exception $e) {
            die('Unexpected error: '.$e->getMessage());
            $message .= $e->getMessage()."\n";
            echo $message;
            exit;
        }
    }
    
    public function metricsDataAction()
    {
        $show_in_result_metrics = array();
        $query = '';
        $dbUtil = $this->container->getDBUtils();
        
        try {
            $type = Utils::get_GET_string('type');
            if(!$type || $type == 'CPU') {
                $query = 'SELECT e.id_exec, e.exec, e.bench, e.net, e.disk, e.maps, e.comp, e.replication, e.blk_size, '.
                'AVG(s.`%user`), MAX(s.`%user`), MIN(s.`%user`), STDDEV_POP(s.`%user`), VAR_POP(s.`%user`), 
                 AVG(s.`%nice`), MAX(s.`%nice`), MIN(s.`%nice`), STDDEV_POP(s.`%nice`), VAR_POP(s.`%nice`),
                 AVG(s.`%system`), MAX(s.`%system`), MIN(s.`%system`), STDDEV_POP(s.`%system`), VAR_POP(s.`%system`),
                 AVG(s.`%iowait`), MAX(s.`%iowait`), MIN(s.`%iowait`), STDDEV_POP(s.`%iowait`), VAR_POP(s.`%iowait`),
                 AVG(s.`%steal`), MAX(s.`%steal`), MIN(s.`%steal`), STDDEV_POP(s.`%steal`), VAR_POP(s.`%steal`),
                 AVG(s.`%idle`), MAX(s.`%idle`), MIN(s.`%idle`), STDDEV_POP(s.`%idle`), VAR_POP(s.`%idle`),e.id_cluster,e.end_time'.
                ' FROM SAR_cpu s JOIN execs e USING (id_exec) JOIN clusters USING (id_cluster) WHERE e.valid = TRUE GROUP BY (e.id_exec)';
        
            } else if($type == 'DISK') {
                $query = 'SELECT e.id_exec, e.exec, e.bench, e.net, e.disk, e.maps, e.comp, e.replication, e.blk_size, '.
                    's.DEV, AVG(s.tps), MAX(s.tps), MIN(s.tps), 
                    AVG(s.`rd_sec/s`), MAX(s.`rd_sec/s`), MIN(s.`rd_sec/s`), STDDEV_POP(s.`rd_sec/s`), VAR_POP(s.`rd_sec/s`), SUM(s.`rd_sec/s`),
                    AVG(s.`wr_sec/s`), MAX(s.`wr_sec/s`), MIN(s.`wr_sec/s`), STDDEV_POP(s.`wr_sec/s`), VAR_POP(s.`wr_sec/s`), SUM(s.`wr_sec/s`), 
                    AVG(s.`avgrq-sz`), MAX(s.`avgrq-sz`), MIN(s.`avgrq-sz`), STDDEV_POP(s.`avgrq-sz`), VAR_POP(s.`avgrq-sz`), '.
                    'AVG(s.`avgqu-sz`), MAX(s.`avgqu-sz`), MIN(s.`avgqu-sz`), STDDEV_POP(s.`avgqu-sz`), VAR_POP(s.`avgqu-sz`), 
                    AVG(s.await), MAX(s.`await`), MIN(s.`await`), STDDEV_POP(s.`await`), VAR_POP(s.`await`), 
                    AVG(s.`%util`), MAX(s.`%util`), MIN(s.`%util`), STDDEV_POP(s.`%util`), VAR_POP(s.`%util`),
                    AVG(s.svctm), MAX(s.`svctm`), MIN(s.`svctm`), STDDEV_POP(s.`svctm`), VAR_POP(s.`svctm`), e.id_cluster,e.end_time'.
                    ' FROM SAR_block_devices s JOIN execs e USING (id_exec) JOIN clusters USING (id_cluster) WHERE e.valid = TRUE GROUP BY (e.id_exec)';
            } else if($type == 'MEMORY') {
                $query = 'SELECT e.id_exec, e.exec, e.bench, e.net, e.disk, e.maps, e.comp, e.replication, e.blk_size, '.
                    'AVG(su.kbmemfree), MAX(su.kbmemfree), MIN(su.kbmemfree), STDDEV_POP(su.kbmemfree), VAR_POP(su.kbmemfree),  
                     AVG(su.kbmemused), MAX(su.kbmemused), MIN(su.kbmemused), STDDEV_POP(su.kbmemused), VAR_POP(su.kbmemused), 
                     AVG(su.`%memused`), MAX(su.`%memused`), MIN(su.`%memused`), STDDEV_POP(su.`%memused`), VAR_POP(su.`%memused`), '.
                    'AVG(su.kbbuffers), MAX(su.kbbuffers), MIN(su.kbbuffers), STDDEV_POP(su.kbbuffers), VAR_POP(su.kbbuffers), 
                     AVG(su.kbcached), MAX(su.kbcached), MIN(su.kbcached), STDDEV_POP(su.kbcached), VAR_POP(su.kbcached), 
                     AVG(su.kbcommit), MAX(su.kbcommit), MIN(su.kbcommit), STDDEV_POP(su.kbcommit), VAR_POP(su.kbcommit), 
                     AVG(su.`%commit`), MAX(su.`%commit`), MIN(su.`%commit`), STDDEV_POP(su.`%commit`), VAR_POP(su.`%commit`), 
                     AVG(su.kbactive), MAX(su.kbactive), MIN(su.kbactive), STDDEV_POP(su.kbactive), VAR_POP(su.kbactive), 
                     AVG(su.kbinact), MAX(su.kbinact), MIN(su.kbinact), STDDEV_POP(su.kbinact), VAR_POP(su.kbinact) ,e.id_cluster,e.end_time'.
                    ' FROM SAR_memory_util su '.
                    'JOIN execs e USING (id_exec) JOIN clusters USING (id_cluster) WHERE e.valid = TRUE GROUP BY (e.id_exec)';
            } else if($type == 'NETWORK') {
                $query = 'SELECT e.id_exec, e.exec, e.bench, e.net, e.disk, e.maps, e.comp, e.replication, e.blk_size, '.
                    's.IFACE,AVG(s.`rxpck/s`),MAX(s.`rxpck/s`),MIN(s.`rxpck/s`),STDDEV_POP(s.`rxpck/s`),VAR_POP(s.`rxpck/s`),SUM(s.`rxpck/s`),
                    AVG(s.`txpck/s`),MAX(s.`txpck/s`),MIN(s.`txpck/s`),STDDEV_POP(s.`txpck/s`),VAR_POP(s.`txpck/s`),SUM(s.`txpck/s`),
                    AVG(s.`rxkB/s`),MAX(s.`rxkB/s`),MIN(s.`rxkB/s`),STDDEV_POP(s.`rxkB/s`),VAR_POP(s.`rxkB/s`),SUM(s.`rxkB/s`),
                    AVG(s.`txkB/s`),MAX(s.`txkB/s`),MIN(s.`txkB/s`),STDDEV_POP(s.`txkB/s`),VAR_POP(s.`txkB/s`),SUM(s.`txkB/s`),
                    AVG(s.`rxcmp/s`),MAX(s.`rxcmp/s`),MIN(s.`rxcmp/s`),STDDEV_POP(s.`rxcmp/s`),VAR_POP(s.`rxcmp/s`),SUM(s.`rxcmp/s`),
                    AVG(s.`txcmp/s`),MAX(s.`txcmp/s`),MIN(s.`txcmp/s`),STDDEV_POP(s.`txcmp/s`),VAR_POP(s.`txcmp/s`),SUM(s.`txcmp/s`),
                    AVG(s.`rxmcst/s`),MAX(s.`rxmcst/s`),MIN(s.`rxmcst/s`),STDDEV_POP(s.`rxmcst/s`),VAR_POP(s.`rxmcst/s`),SUM(s.`rxmcst/s`),'.
                    'e.id_cluster,e.end_time FROM SAR_net_devices s '.
                    'JOIN execs e USING (id_exec) JOIN clusters USING (id_cluster) WHERE e.valid = TRUE GROUP BY (e.id_exec)';
            }
        
            $exec_rows = $dbUtil->get_rows($query);
            if (count($exec_rows) > 0) {          
               $show_in_result_metrics = Utils::generate_show($show_in_result_metrics,$exec_rows,0);     
               $jsonData = Utils::generateJSONTable($exec_rows, $show_in_result_metrics, 2);
            } else {
               throw new \Exception("No results for query!");
            }
        
            header('Content-Type: application/json');
            ob_start('ob_gzhandler');
            echo $jsonData;
                
        } catch (Exception $e) {
            $noData = array();
            $noData[] = $e->getMessage();

            echo json_encode(array('aaData' => $noData));
        }
    }
    
    public function histogramDataAction()
    {
		$db = $this->container->getDBUtils ();
		$execsDetails = array ();
		try {
			$idExec = Utils::get_GET_string('id_exec');
			if (!$idExec)
				throw new \Exception ( "No execution selected!" );
				
			// get the result rows
			$query = "SELECT e.bench,j.*
    		from JOB_tasks j JOIN execs e USING (id_exec) 
			where e.valid = TRUE AND j.id_exec = $idExec;";
			
			$this->getContainer ()->getLog ()->addInfo ( 'Histogram query: ' . $query );
			$rows = $db->get_rows ($query);
			if (!$rows) {
				throw new \Exception ( "No results for query!" );
			}
			
			$result = array();
			foreach ( $rows as $row ) {
				$result[$row['JOBID'].'/'.$row['bench']]['tasks'][$row['TASKID']] = $row;
			}
			header('Content-Type: application/json');
			ob_start('ob_gzhandler');
			echo json_encode($result);
		} catch ( \Exception $e ) {
			$noData = array();
            $noData[] = $e->getMessage();

            echo json_encode(array('error' => $noData));
		}
    }

    public function bestConfigDataAction()
    {
    	$db = $this->container->getDBUtils();
    	$rows_config = '';
    	try {
    		$configurations = array();
    		$where_configs = '';
    		$concat_config = "";
    		 
    		$benchs         = Utils::read_params('benchs',$where_configs,$configurations,$concat_config);
    		$nets           = Utils::read_params('nets',$where_configs,$configurations,$concat_config);
    		$disks          = Utils::read_params('disks',$where_configs,$configurations,$concat_config);
    		$blk_sizes      = Utils::read_params('blk_sizes',$where_configs,$configurations,$concat_config);
    		$comps          = Utils::read_params('comps',$where_configs,$configurations,$concat_config);
    		$id_clusters    = Utils::read_params('id_clusters',$where_configs,$configurations,$concat_config);
    		$mapss          = Utils::read_params('mapss',$where_configs,$configurations,$concat_config);
    		$replications   = Utils::read_params('replications',$where_configs,$configurations,$concat_config);
    		$iosfs          = Utils::read_params('iosfs',$where_configs,$configurations,$concat_config);
    		$iofilebufs     = Utils::read_params('iofilebufs',$where_configs,$configurations,$concat_config);
    		 
    		//$concat_config = join(',\'_\',', $configurations);
    		//$concat_config = substr($concat_config, 1);
    		 
    		//make sure there are some defaults
    		if (!$concat_config) {
    			$concat_config = 'disk';
    			$disks = array('HDD');
    		}
    		 
    		$filter_execs = "AND exe_time > 200 AND (id_cluster = 1 OR (bench != 'bayes' AND id_cluster=2))";
    		$order_conf = 'LENGTH(conf), conf';
    		 
    		//get best config
    		$query = "SELECT e.* from execs e WHERE e.id_exec IN ".
    				"(SELECT MIN(e2.exe_time) FROM execs e2 WHERE 1 $filter_execs $where_configs LIMIT 1);";
    		 
    		$rows = $db->get_rows($query);
    		if(!$rows)
    			throw new \Exception("No results for query!");
    		 
    	} catch (\Exception $e) {
    		$noData = array();
            for($i = 0; $i<=sizeof($show_in_result); ++$i)
            	$noData[] = 'error';
            
            echo json_encode(array('aaData' => array($noData)));
    	}
    }
}
