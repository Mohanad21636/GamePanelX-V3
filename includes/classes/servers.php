<?php
class Servers
{
    // Query for info on a server (assumes $srvid is already escaped)
    public function getinfo($srvid)
    {
        if(empty($srvid)) return 'No server ID provided';
        
        $srv_info   = array();
        
        $result_srv = @mysql_query("SELECT 
                                      s.id,
                                      s.userid,
                                      s.netid,
                                      s.port,
                                      s.startup,
                                      DATE_FORMAT(s.date_created, '%c/%e/%Y %h:%i%p') AS date_added,
                                      DATE_FORMAT(s.last_updated, '%c/%e/%Y %h:%i%p') AS last_updated,
                                      s.description,
                                      s.status,
                                      s.working_dir,
                                      s.pid_file,
                                      s.update_cmd,
                                      s.simplecmd,
                                      n.ip,
                                      u.username,
                                      p.id AS parentid,
                                      d.gameq_name,
                                      d.banned_chars 
                                    FROM servers AS s 
                                    LEFT JOIN network AS n ON 
                                      s.netid = n.id 
                                    JOIN network AS p ON 
                                      n.parentid = p.id 
                                      OR n.parentid = '0' 
                                    LEFT JOIN users AS u ON 
                                      s.userid = u.id 
                                    LEFT JOIN default_games AS d ON 
                                      s.defid = d.id 
                                    WHERE 
                                      s.id = '$srvid' 
                                    LIMIT 1") or die('Failed to query for servers: '.mysql_error());
        
        while($row_srv = mysql_fetch_assoc($result_srv))
        {
            $srv_info[] = $row_srv;
        }
        
        // Return array of data
        return $srv_info;
    }
    
    
    
    
    
    
    
    
    
    
    
    
    // Run GameQ server queries for status info etc
    public function gamequery($info)
    {
        // Require GameQ library
        require_once(DOCROOT . '/includes/classes/gameq.php');
        $query_timeout = 12;
        
        ########################################################################

        // Run GameQ specifics
        $gq = new GameQ();
        $gq->addServers($info);

            
        // You can optionally specify some settings
        $gq->setOption('timeout', $query_timeout);


        // You can optionally specify some output filters,
        // these will be applied to the results obtained.
        $gq->setFilter('normalise');
        $gq->setFilter('sortplayers', 'gq_ping');

        // Send requests, and parse the data
        $results = $gq->requestData();
        
        /*
        echo '<pre>';
        var_dump($results);
        echo '</pre>';
        exit;
        */
        
        ########################################################################


        // Give the array back to the other page with online status
        $count_array = count($info) - 1;
        
        
        // Create new '$current' array for just current status info
        $current = array();
        
        // Only check if results
        if($results)
        {
            // If there's more than 1 array, loop through all of them
            if($count_array >= 1)
            {
                for($i = 0; $i <= $count_array; $i++)
                {
                    // If online is true, set status to online            
                    if($results[$i]['gq_online'])
                    {
                        $current_status = 'online';
                    }
                    else
                    {
                        $current_status = 'offline';
                    }
                    
                    // Add these values to the new '$current' array
                    $current[$i]['current_address']     = $results[$i]['gq_address'];
                    $current[$i]['current_port']        = $results[$i]['gq_port'];
                    $current[$i]['current_status']      = $current_status;
                    $current[$i]['current_numplayers']  = $results[$i]['gq_numplayers'];
                    $current[$i]['current_maxplayers']  = $results[$i]['gq_maxplayers'];
                }
            }
            // Otherwise, just use the 1 array
            else
            {
                // If online is true, set status to online
                if($results[0]['gq_online'])
                {
                    $current_status = 'online';
                }
                else
                {
                    $current_status = 'offline';
                }
                
                // Operating System
                if($results[0]['os'] == 'l')
                {
                    $current_os = 'Linux';
                }
                elseif($results[0]['os'] == 'w')
                {
                    $current_os = 'Windows';
                }
                else
                {
                    $current_os = 'Unknown';
                }
                
                // Add these values to the new '$current' array
                $current[0]['current_status']      = $current_status;
                $current[0]['current_address']     = $results[0]['gq_address'];
                $current[0]['current_port']        = $results[0]['gq_port'];
                $current[0]['current_hostname']    = $results[0]['gq_hostname'];
                $current[0]['current_mapname']     = $results[0]['gq_mapname'];
                $current[0]['current_maxplayers']  = $results[0]['gq_maxplayers'];
                $current[0]['current_numplayers']  = $results[0]['gq_numplayers'];
                $current[0]['current_mod']         = $results[0]['gq_mod'];
                
                // Extras
                $current[0]['current_os']          = $current_os;
                $current[0]['current_game_dir']    = $results[0]['game_dir'];
                $current[0]['current_mod']         = $results[0]['gq_mod'];
                $current[0]['current_has_pw']      = $results[0]['gq_password'];
                $current[0]['current_protocol']    = $results[0]['gq_prot'];
                $current[0]['current_num_bots']    = $results[0]['num_bots'];
                
                // Players
                $current[0]['current_players']     = $results[0]['players'];
            }
        }
        
        // Return array of values
        return $current;
    }
    
    
    //
    // Get current server statuses (gameQ)
    // Create a full array of servers (query name, ip, port) and send it to GameQ
    //
    public function getarr_gamequery($sql_arr)
    {
        // Count the SQL array
        $total_servers = count($sql_arr);

        if($total_servers > 0)
        {
            // Subtract 1 from total servers since it starts at 0
            $total_servers = $total_servers - 1;

            // Create status array
            $status_info = array();

            for($i = 0; $i <= $total_servers; $i++)
            {
                // GPX Server short name
                $gpx_server = $sql_arr[$i]['intname'];
                
                // Swap GPX game name for GameQ game name
                $result_name  = @mysql_query("SELECT gameq_name FROM default_games WHERE intname = '$gpx_server'");
                $row_name     = mysql_fetch_row($result_name);
                $status_info[$i]['server']  = $row_name[0];
                
                ################################################################
                
                // Add IP and Port to array
                $status_info[$i]['ip']    = $sql_arr[$i]['ip'];
                $status_info[$i]['port']  = $sql_arr[$i]['port'];
            }
            
            
            //
            // Query server (gameQ) for current status info
            //
            $result_array = $this->gamequery($status_info);
            
            /*
            echo '<pre>';
            var_dump($result_array);
            echo '</pre>';
            */


            // Add all server values back into the mix
            for($i = 0; $i <= $total_servers; $i++)
            {
                /*
                // No query name; do generic query
                if(empty($sql_arr[$i]['gameq_name']))
                {
                    // Run a generic query
                    if(gpx_query_generic($sql_arr[$i]['ip'],$sql_arr[$i]['port']))
                    {
                        $result_array[$i]['current_status'] = 'online';
                    }
                    else
                    {
                        $result_array[$i]['current_status'] = 'offline';
                    }
                }
                */
                
                // Current player num
                if(!$result_array[$i]['current_numplayers'])
                {
                    $result_array[$i]['current_numplayers'] = '0';
                }
                
                // Add regular db stuff back in
                $result_array[$i]['id']               = $sql_arr[$i]['id'];
                $result_array[$i]['userid']           = $sql_arr[$i]['userid'];
                $result_array[$i]['ip']               = $sql_arr[$i]['ip'];
                $result_array[$i]['port']             = $sql_arr[$i]['port'];
                $result_array[$i]['status']           = $sql_arr[$i]['status'];
                $result_array[$i]['description']      = $sql_arr[$i]['description'];
                $result_array[$i]['name']             = $sql_arr[$i]['name'];
                $result_array[$i]['intname']          = $sql_arr[$i]['intname'];
                $result_array[$i]['username']         = $sql_arr[$i]['username'];
            }
            
            
            // Return array
            return $result_array;
        }
    }
    
    
    
    
    // Restart a gameserver (change status)
    public function restart($srvid)
    {
        error_reporting(E_ERROR);
        
        if(empty($srvid)) return 'No server ID given';
        
        $srv_info = $this->getinfo($srvid);
        
        $srv_username   = $srv_info[0]['username'];
        $srv_ip         = $srv_info[0]['ip'];
        $srv_port       = $srv_info[0]['port'];
        $srv_cmd        = $srv_info[0]['simplecmd'];
        $srv_work_dir   = $srv_info[0]['working_dir'];
        $srv_pid_file   = $srv_info[0]['pid_file'];
        $srv_netid      = $srv_info[0]['parentid'];
        
        #var_dump($srv_info);
        
        // Double-check required
        if(empty($srv_username) || empty($srv_ip) || empty($srv_port) || empty($srv_cmd)) return 'restart class: Required values were left out';
        
        // Working dir, PID file
        if($srv_work_dir) $srv_work_dir = '-w ' . $srv_work_dir;
        if($srv_pid_file) $srv_pid_file = '-P ' . $srv_pid_file;
        
        // Run the command
        $ssh_cmd      = "Restart -u $srv_username -i $srv_ip -p $srv_port $srv_pid_file $srv_work_dir -o '$srv_cmd'";
        
        require('network.php');
        $Network  = new Network;
        $net_info = $Network->netinfo($srv_netid);
        
        $ssh_response = $Network->runcmd($srv_netid,$net_info,$ssh_cmd,true);
        
        // Should return 'success'
        return $ssh_response;
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    // Stop a gameserver (change status)
    public function stop($srvid)
    {
        error_reporting(E_ERROR);
        
        if(empty($srvid)) return 'No server ID given';
        
        // Get network info to SSH in
        $srv_info = $this->getinfo($srvid);
        
        $srv_username   = $srv_info[0]['username'];
        $srv_ip         = $srv_info[0]['ip'];
        $srv_port       = $srv_info[0]['port'];
        $srv_netid      = $srv_info[0]['parentid'];
        
        #var_dump($srv_info);
        
        // Double-check required
        if(empty($srv_username) || empty($srv_ip) || empty($srv_port)) return 'stop class: Required values were left out';
        
        // Force back to completed if updating
        if($srv_info[0]['status'] == 'updating')
        {
            @mysql_query("UPDATE servers SET status = 'complete' WHERE id = '$srvid'");
        }        
        
        
        // Run the command
        $ssh_cmd      = "Stop -u $srv_username -i $srv_ip -p $srv_port";
        
        require('network.php');
        $Network  = new Network;
        $net_info = $Network->netinfo($srv_netid);
        
        $ssh_response = $Network->runcmd($srv_netid,$net_info,$ssh_cmd,true);
        
        // Should return 'success'
        return $ssh_response;
    }
    
    
    // Update a gameserver
    public function update($srvid)
    {
        error_reporting(E_ERROR);
        
        if(empty($srvid)) return 'No server ID given';
        
        // Get network info to SSH in
        $srv_info = $this->getinfo($srvid);
        
        $srv_username     = $srv_info[0]['username'];
        $srv_ip           = $srv_info[0]['ip'];
        $srv_port         = $srv_info[0]['port'];
        $srv_update_cmd   = $srv_info[0]['update_cmd'];
        $srv_netid        = $srv_info[0]['parentid'];
        
        #var_dump($srv_info);
        
        // Double-check required
        if(empty($srv_username) || empty($srv_ip) || empty($srv_port) || empty($srv_update_cmd)) return 'update class: Required values were left out';
        
        // Generate and store random token for remote server callback
        $Core = new Core;
        $remote_token = $Core->genstring('16');
        @mysql_query("UPDATE servers SET token = '$remote_token' WHERE id = '$srvid'") or die('Failed to update token!');
        
        // Get callback page
        $this_url   = $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
        $this_page  = str_replace('ajax/ajax.php', '', $this_url);
        $this_page  .= '/includes/callback.php?token='.$remote_token.'&id='.$srvid;
        $this_page  = preg_replace('/\/+/', '/', $this_page); // Remove extra slashes
        $this_page  = 'http://' . $this_page;
        
        #echo "Callback: $this_page<br>";
        
        // Set as updating
        #@mysql_query("UPDATE servers SET status = 'updating' WHERE id = '$srvid'") or die('Failed to update status!');
        
        // Run the command
        $ssh_cmd      = "UpdateServer -u $srv_username -i $srv_ip -p $srv_port -c \"$this_page\" -o \"$srv_update_cmd\"";
        
        require('network.php');
        $Network  = new Network;
        $net_info = $Network->netinfo($srv_netid);
        
        $ssh_response = $Network->runcmd($srv_netid,$net_info,$ssh_cmd,true);
        
        // Should return 'success'
        return $ssh_response;
    }
    
    
    
    
    
    // Check for used IP/Port combination
    public function checkcombo($netid,$port)
    {
        if(!$netid || !$port) return 'CheckCombo: No IP or Port specified!';
        
        $result_ck  = @mysql_query("SELECT id FROM servers WHERE netid = '$netid' AND port = '$port' LIMIT 1");
        $row_ck     = mysql_fetch_row($result_ck);
        
        // Return false if exists already
        if($row_ck[0]) return false;
        else return true;
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    // Create a new server
    public function create($netid,$gameid,$ownerid,$port,$description)
    {
        if(empty($netid) || empty($gameid) || empty($ownerid)) return 'Servers: Insufficient info provided';
        
        // Generate random token for remote server callback
        $Core = new Core;
        $remote_token = $Core->genstring('16');
        
        // Check for uses IP/Port combo (false if used)
        if(!$this->checkcombo($netid,$port)) return 'Servers: That IP/Port combination is already in use!  Please choose a different IP or Port and try again.';
        
        // Get owner username
        $result_name  = @mysql_query("SELECT username FROM users WHERE id = '$ownerid' LIMIT 1");
        $row_name     = mysql_fetch_row($result_name);
        $this_usrname = $row_name[0];
        
        // Get default template
        $result_tpl  = @mysql_query("SELECT id FROM templates WHERE cfgid = '$gameid' AND status = 'complete' AND is_default = '1' ORDER BY id LIMIT 1");
        $row_tpl     = mysql_fetch_row($result_tpl);
        $this_tplid  = $row_tpl[0];
        
        
        // Setup to create on remote server
        require(DOCROOT.'/includes/classes/network.php');
        $Network  = new Network;
        $net_arr  = $Network->netinfo($netid);
                
        if(empty($net_arr['game_ip'])) $this_ip = $net_arr['ssh_ip'];
        else $this_ip  = $net_arr['game_ip'];
        
        // Double check everything
        if(empty($this_usrname)) return 'Servers: No username specified!';
        elseif(empty($this_ip)) return 'Servers: No IP Address specified!';
        elseif(empty($port)) return 'Servers: No port specified!';
        elseif(empty($this_tplid)) return 'Servers: No default template found for this game!';
        
        ############################################################################################
        
        // Get some defaults
        $result_dfts  = @mysql_query("SELECT working_dir,pid_file,update_cmd FROM default_games WHERE id = '$gameid' LIMIT 1");
        $row_dfts     = mysql_fetch_row($result_dfts);
        $def_working_dir  = mysql_real_escape_string($row_dfts[0]);
        $def_pid_file     = mysql_real_escape_string($row_dfts[1]);
        $def_update_cmd   = mysql_real_escape_string($row_dfts[2]);
        
        // Insert into db
        @mysql_query("INSERT INTO servers (userid,netid,defid,port,status,date_created,token,working_dir,pid_file,update_cmd,description) VALUES('$ownerid','$netid','$gameid','$port','installing',NOW(),'$remote_token','$def_working_dir','$def_pid_file','$def_update_cmd','$description')") or die('Failed to insert server: '.mysql_error());
        $srv_id = mysql_insert_id();
        
        // Insert default srv settings
        $result_smp = @mysql_query("SELECT * FROM default_startup WHERE defid = '$gameid' ORDER BY sort_order ASC");
        $total_strt = mysql_num_rows($result_smp);
        
        $insert_new = 'INSERT INTO servers_startup (srvid,sort_order,single,usr_edit,cmd_item,cmd_value) VALUES ';
        $simplecmd  = '';
        
        while($row_smp  = mysql_fetch_array($result_smp))
        {
            $cmd_sort   = $row_smp['sort_order'];
            $cmd_single = $row_smp['single'];
            $cmd_usred  = $row_smp['usr_edit'];
            $cmd_item   = $row_smp['cmd_item'];
            $cmd_val    = $row_smp['cmd_value'];
            
            $insert_new .= "('$srv_id','$cmd_sort','$cmd_single','$cmd_usred','$cmd_item','$cmd_val'),";
            
            // Replace %vars% for simplecmd
            $cmd_val  = str_replace('%IP%', $this_ip, $cmd_val);
            $cmd_val  = str_replace('%PORT%', $port, $cmd_val);
            
            // Update simplecmd
            $simplecmd .= $cmd_item . ' ';
            if($cmd_val || $cmd_val == '0') $simplecmd .= $cmd_val . ' ';
        }
        
        
        // Run multi-insert (only if there were default startup items)
        if($total_strt)
        {
            // Remove last comma
            $insert_new = substr($insert_new, 0, -1);
            
            @mysql_query($insert_new) or die('Failed to insert startup items: '.mysql_error());
        }
        
        // Add simplecmd
        @mysql_query("UPDATE servers SET simplecmd = '$simplecmd' WHERE id = '$srv_id'");
        
        ############################################################################################
        
        // Get callback page
        $this_url   = $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
        $this_page  = str_replace('ajax/ajax.php', '', $this_url);
        $this_page  .= '/includes/callback.php?token='.$remote_token.'&id='.$srv_id;
        $this_page  = preg_replace('/\/+/', '/', $this_page); // Remove extra slashes
        $this_page  = 'http://' . $this_page;
        
        
        // Setup create command
        $net_cmd  = "CreateServer -u $this_usrname -i $this_ip -p $port -x $this_tplid -c \"$this_page\"";
        
        return $Network->runcmd($netid,$net_arr,$net_cmd,true);
    }
    
    
    
    
    
    
    
    
    // Delete a gameserver
    public function delete($srvid)
    {
        if(empty($srvid)) return 'No server ID given';
        
        // Get network info to SSH in
        $srv_info = $this->getinfo($srvid);
        
        $srv_username   = $srv_info[0]['username'];
        $srv_ip         = $srv_info[0]['ip'];
        $srv_port       = $srv_info[0]['port'];
        $srv_netid      = $srv_info[0]['parentid'];
        
        
        // Run deletion on server-side
        $ssh_cmd  = "DeleteServer -u $srv_username -i $srv_ip -p $srv_port";
        
        require('network.php');
        $Network  = new Network;
        $net_info = $Network->netinfo($srv_netid);
        
        $ssh_response = $Network->runcmd($srv_netid,$net_info,$ssh_cmd,true);
        
        // If actually deleted files...
        if($ssh_response == 'success')
        {
            // Delete from db
            @mysql_query("DELETE FROM servers WHERE id = '$srvid'") or die('Failed to delete server from database!');
            @mysql_query("DELETE FROM servers_startup WHERE srvid = '$srvid'") or die('Failed to delete server startup items from database!');
            
            return 'success';
        }
        else
        {
            return 'Failed to delete files: '.$ssh_response;
        }
        
    }
    
    
    
    
    
    
    
    
    
    
    
    // Get PID(s), CPU and Memory info for a server
    public function getcpuinfo($srvid)
    {
        // All output needs to be JSON for javascript to pick it up properly
        if(empty($srvid)) return '{"error":"restart class: No server ID given"}';
        
        $srv_info = $this->getinfo($srvid);
        
        $srv_username   = $srv_info[0]['username'];
        $srv_ip         = $srv_info[0]['ip'];
        $srv_port       = $srv_info[0]['port'];
        $srv_netid      = $srv_info[0]['parentid'];
        
        // Double-check required
        if(empty($srv_username) || empty($srv_ip) || empty($srv_port)) return '{"error":"restart class: Required values were left out"}';
        
        require('network.php');
        $Network  = new Network;
        $net_info = $Network->netinfo($srv_netid);
        
        // Run the command
        $ssh_cmd      = "CheckGame -u $srv_username -i $srv_ip -p $srv_port";
        $ssh_response = $Network->runcmd($srv_netid,$net_info,$ssh_cmd,true);
        
        // If invalid json, make it a JSON error msg
        if(!json_decode($ssh_response))
        {
            $ssh_response = addslashes($ssh_response);
            $ssh_response = '{"error":"'.$ssh_response.'"}';
        }
        
        // Should return 'success'
        return $ssh_response;
    }
    
    
    
    
    
    // Update simplecmd with most recent order
    public function update_startup_cmd($srvid,$srv_ip,$srv_port)
    {
        if(empty($srvid) || empty($srv_ip) || empty($srv_port)) return 'Insufficient info given!';
        
        $simplecmd  = '';
        $result_smp = @mysql_query("SELECT cmd_item,cmd_value FROM servers_startup WHERE srvid = '$srvid' ORDER BY sort_order ASC") or die('Failed to get startup item list!');
        
        while($row_smp  = mysql_fetch_array($result_smp))
        {
            $cmd_item = $row_smp['cmd_item'];
            $cmd_val  = $row_smp['cmd_value'];
            
            // Replace %vars%
            $cmd_val  = str_replace('%IP%', $srv_ip, $cmd_val);
            $cmd_val  = str_replace('%PORT%', $srv_port, $cmd_val);
            
            $simplecmd .= $cmd_item . ' ';
            if($cmd_val || $cmd_val == '0') $simplecmd .= $cmd_val . ' ';
        }
        
        // Update new simplecmd
        @mysql_query("UPDATE servers SET simplecmd = '$simplecmd' WHERE id = '$srvid'") or die('Failed to update cmd!');
        
        return 'success';
    }
    
    
    
    // Updating a userid or IP/Port for a server - Move server to new area
    public function moveserver($srvid,$orig_userid,$orig_username,$orig_netid,$orig_ip,$orig_port,$new_userid,$new_netid,$new_port)
    {
        // Get new username
        if($new_userid != $orig_userid)
        {
            $result_nu    = @mysql_query("SELECT username FROM users WHERE id = '$new_userid' LIMIT 1");
            $row_nu       = mysql_fetch_row($result_nu);
            $new_username = $row_nu[0];
        }
        // Not moving users, just use original username
        else
        {
            $new_username = $orig_username;
        }
        
        // Get new IP
        $result_nip = @mysql_query("SELECT ip FROM network WHERE id = '$new_netid' LIMIT 1");
        $row_nip    = mysql_fetch_row($result_nip);
        $new_ip     = $row_nip[0];
        
        // Check required
        if(empty($orig_username) || empty($orig_ip) || empty($orig_port) || empty($new_username) || empty($new_ip) || empty($new_port)) return 'Sorry, did not receive all required options!';
        
        $ssh_cmd      = "MoveServerLocal -u $orig_username -i $orig_ip -p $orig_port -U $new_username -I $new_ip -P $new_port";
        
        require('network.php');
        $Network  = new Network;
        $net_info = $Network->netinfo($orig_netid);
        
        $ssh_response = $Network->runcmd($orig_netid,$net_info,$ssh_cmd,true);
        
        // Should return 'success'
        return $ssh_response;
    }
    
}
