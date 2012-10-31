<?php
class Network
{
    // Get all relevant network info to connect to a server and properly run commands
    public function netinfo($srvid)
    {
        if(empty($srvid)) return 'No server ID given';
        
        if(!isset($settings['db_host'])) require(DOCROOT.'/configuration.php');
        
        $enc_key  = $settings['enc_key'];
        if(empty($enc_key)) return 'No encryption key found!  Check your /configuration.php file.';
        
        // Get all info in 1 query
        # AES_DECRYPT(n.homedir, '$enc_key') AS homedir,
        # s.port AS gameport,
        # u.username 
        $result_net = @mysql_query("SELECT 
                                      p.is_local,
                                      AES_DECRYPT(p.login_user, '$enc_key') AS login_user,
                                      AES_DECRYPT(p.login_pass, '$enc_key') AS login_pass,
                                      AES_DECRYPT(p.login_port, '$enc_key') AS login_port,
                                      p.homedir,
                                      p.ip,
                                      s.simplecmd,
                                      d.working_dir,
                                      d.pid_file,
                                      p.ip AS gameip 
                                    FROM network AS n 
                                    LEFT JOIN servers AS s ON 
                                      n.id = s.netid 
                                    LEFT JOIN users AS u ON 
                                      s.userid = u.id 
                                    LEFT JOIN default_games AS d ON 
                                      s.defid = d.id 
                                    LEFT JOIN network AS p ON 
                                      n.parentid = p.id OR 
                                      n.id = p.id
                                    WHERE 
                                      n.id = '$srvid' 
                                    LIMIT 1");
        # Was: WHERE p.id = '$srvid'
        # (join net): OR n.parentid = '0' 
        # OR p.id IS NULL 
        $net_arr  = array();
        while($row_net  = mysql_fetch_array($result_net))
        {
            $net_arr['is_local']          = $row_net['is_local'];
            $net_arr['ssh_user']          = $row_net['login_user'];
            $net_arr['ssh_pass']          = $row_net['login_pass'];
            $net_arr['ssh_port']          = $row_net['login_port'];
            $net_arr['ssh_homedir']       = $row_net['homedir'];
            $net_arr['ssh_ip']            = $row_net['ip'];
            $net_arr['game_ip']           = $row_net['gameip'];
            $net_arr['username']          = $row_net['username'];
        }
        
        // Return array of info
        return $net_arr;
    }
    
    
    
    
    
    
    // Run a command (SSH or locally) (Commands should be given WITHOUT path to /home/user/scripts/)
    public function runcmd($srvid,$netarr,$cmd,$output=false)
    {
        if(!isset($settings['enc_key'])) require(DOCROOT.'/configuration.php');
        if(empty($srvid))   return 'RunCMD: No server ID given';
        if(empty($cmd))     return 'RunCMD: No cmd given';
        if(empty($netarr) || !$netarr)  return 'RunCMD: No network info given';
        
        $ssh_ip       = $netarr['ssh_ip'];
        $ssh_port     = $netarr['ssh_port'];
        $ssh_user     = $netarr['ssh_user'];
        $ssh_pass     = $netarr['ssh_pass'];
        $ssh_homedir  = $netarr['ssh_homedir'];
        $net_local    = $netarr['is_local'];
        
        if($settings['debug'])
        {
            echo "Dumping network info on Net ID $srvid ...  <br />";
            echo '<pre>';
            var_dump($netarr);
            echo '</pre><br />';
        }
        
        // If not local, check if SSH stuff is empty
        if(!$net_local)
        {
            if(empty($ssh_ip) || empty($ssh_port) || empty($ssh_user) || empty($ssh_pass))
            {
                return "RunCMD: Required SSH values were left out (IP: $ssh_ip,Port: $ssh_port,User: $ssh_user,Password: $ssh_pass)";
            }
        }
        
        #############################################################################################
        
        // Remove anything with a .. in it
        $cmd  = str_replace('..', '', $cmd);
        
        // Safeguards on CMD-Line
        $cmd_arr  = explode(' ', $cmd);
        $new_cmd  = '';
        $count    = 0;
        
        foreach($cmd_arr as $item)
        {
            // Script name
            if($count == 0)
            {
                $new_cmd .= escapeshellcmd($item) . ' ';
            }
            // Skip if a -x flag (x being any letter)
            elseif(preg_match('/^\-[a-zA-Z]$/', $item))
            {
                $new_cmd .= $item . ' ';
                #continue;
            }
            // All other items
            else
            {
                $new_cmd .= escapeshellarg($item) . ' ';
            }
            
            $count++;
        }
        
        #############################################################################################
        
        // Local Server, use exec() or shell_exec()
        if($net_local)
        {
            if($settings['debug']) echo "Local Server.  <br />";
            
            // Ensure scripts are there
            if(!file_exists(DOCROOT.'/_SERVERS/scripts/Restart')) return 'RunCMD: The local scripts are not installed; scripts should be in "'.DOCROOT.'/_SERVERS/scripts".  Please check your installation and try again.';
            
            // Add correct path and set $HOME to docroot
            $cmd  = DOCROOT.'/_SERVERS/scripts/'.$cmd;
            $cmd  = 'export HOME='.DOCROOT.'/_SERVERS; ' . $cmd;
            
            // Try and capture STDERR output
            if(!preg_match('/2\>\&1/', $cmd)) $cmd .= ' 2>&1'; 
            
            
            if($settings['debug']) echo "Running Command: $cmd  <br />";
            
            // Try exec
            if(function_exists('exec')) return trim(exec($cmd));
            elseif(function_exists('shell_exec')) return trim(shell_exec($cmd));
            elseif(function_exists('system')) return trim(system($cmd));
            else return 'RunCMD: The "exec" function is not available!  You must enable exec() in your php.ini file or choose the "Remote Server" option.';
        }
        // Remote Server, SSH in
        else
        {
            if($settings['debug']) echo "Remote Server.  <br />";
            
            // PHPSecLib (Pure-PHP SSH Implementation)
            require_once(DOCROOT.'/includes/SSH/Net/SSH2.php');
            
            // Connect to the server
            $ssh = new Net_SSH2($ssh_ip, $ssh_port, 12);
            
            // Login
            if (!$ssh->login($ssh_user, $ssh_pass))
            {
                if($settings['debug']) echo "Login Failed.  <br />";
                
                // Not working.  Test connectivity
                if(!fsockopen($ssh_ip,$ssh_port,$errno,$errstr,12))
                {
                    return 'Remote: Unable to connect to the Remote IP Address (' . $ssh_ip . ') on Port (' . $ssh_port . ').  Check your connection settings and try again.';
                }
                else
                {
                    return 'Remote: Login to the Remote Server failed!';
                }
            }
            else
            {
                if($settings['debug']) echo "Successfully logged into Remote Server.  <br />";
            }
            
            // Add correct path to scripts
            if($cmd == 'echo $HOME') $ssh_cmd = 'echo $HOME';
            elseif($ssh_homedir) $ssh_cmd = $ssh_homedir . '/scripts/'.$cmd;
            else $ssh_cmd = '$HOME/scripts/'.$cmd;
            
            // Check if the function wants output back
            if($output)
            {
                if($settings['debug']) echo "Running Command: $ssh_cmd  <br />";
                
                return trim($ssh->exec($ssh_cmd));
            }
            else
            {
                if($settings['debug']) echo "Running Command: $cmd  <br /><br />";
                $ssh->exec($ssh_cmd); // was $cmd in 3.0.5?
                
                return true;
            }
        }
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    // Find out if a gameserver is running locally
    public function islocal($srvid)
    {
        if(empty($srvid)) return 'No server ID given';
        
        $result_net = @mysql_query("SELECT 
                                      p.is_local 
                                    FROM network AS n 
                                    LEFT JOIN servers AS s ON 
                                      n.id = s.netid 
                                    JOIN network AS p ON 
                                      n.parentid = p.id 
                                      OR n.parentid = '0' 
                                    WHERE 
                                      s.id = '$srvid' 
                                    LIMIT 1");
        
        $row_net  = mysql_fetch_row($result_net);
        
        // Return 1 or 0 for local
        return $row_net[0];
    }
    
    
    
    
    
    
    
    // Create Network Server
    public function create($ip,$is_local,$os,$datacenter,$location,$login_user,$login_pass,$login_port)
    {
        if(empty($ip)) return 'Create: No IP Address provided!';
        if(!preg_match("/[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+/", $ip)) return 'Create: Invalid IP Address ('.$ip.') specificed';
        
        require(DOCROOT.'/configuration.php');
        $enc_key  = $settings['enc_key'];
        if(empty($enc_key)) return 'Create: No encryption key found!  Check your /configuration.php file.';
        
        // Check if this IP Already exists
        $result_ck  = @mysql_query("SELECT id FROM network WHERE ip = '$ip'") or die('Failed to check network');
        $row_ck     = mysql_fetch_row($result_ck);
        if(!empty($row_ck[0])) return 'Create: That IP Address aready exists!';
        
        ################################################################
        
        // Local - ensure system functions are available
        if($is_local)
        {
            // Test PHP functions
            if(!function_exists('exec') && !function_exists('shell_exec') && !function_exists('system')) return 'Create: The "exec" function is not available!  You must enable exec() in your php.ini file or choose the "Remote Server" option.';
            
            // Test _SERVERS/scripts/* are executable
            if(!is_executable(DOCROOT.'/_SERVERS/scripts/Restart') || !is_executable(DOCROOT.'/_SERVERS/scripts/Stop') || !is_executable(DOCROOT.'/_SERVERS/scripts/CreateServer'))
            {
                return 'Create: All scripts inside "'.DOCROOT.'/_SERVERS/scripts/" must be executable!';
            }
        }
        // Remote
        else
        {
            // Test 'includes/SSH' permissions
            if(touch('/tmp/gpxnetworktest.txt'))
            {
                $tmp_owner  = fileowner('/tmp/gpxnetworktest.txt');
                $ssh_owner  = fileowner('../includes/SSH/Net/SSH2.php');
                
                if($tmp_owner != $ssh_owner)
                {
                    // Remove it
                    unlink('/tmp/gpxnetworktest.txt');
                    
                    return 'In order to use Remote SSH, the "includes/SSH" directory needs to be recursively owned by the webserver user (UserID '.$tmp_owner.')!<br /><br />Suggested command:<br /><pre>sudo chown '.$tmp_owner.' '.DOCROOT.'/includes/SSH -R</pre>';
                }
            }
        }
        
        ################################################################
        
        // Insert
        @mysql_query("INSERT INTO network (ip,is_local,os,datacenter,location,login_user,login_pass,login_port) VALUES('$ip','$is_local','$os','$datacenter','$location',AES_ENCRYPT('$login_user', '$enc_key'),AES_ENCRYPT('$login_pass', '$enc_key'),AES_ENCRYPT('$login_port', '$enc_key'))") or die('Failed to insert the network server: '.mysql_error());
        $this_netid = mysql_insert_id();
        
        ################################################################
        
        // Local - already tested
        if($is_local)
        {
            return 'success';
        }
        // Remote - Test network connection / get homedir
        else
        {
            $netarr = $this->netinfo($this_netid);
            $net_homedir  = $this->runcmd($this_netid,$netarr,'echo $HOME',true);
            
            // OK; update homedir
            if(preg_match('/^\//', $net_homedir))
            {
                // Add trailing slash just to be safe
                if(!preg_match('\/$', $net_homedir)) $net_homedir .= '/';
                
                @mysql_query("UPDATE network SET homedir = '$net_homedir' WHERE id = '$this_netid'") or die('Failed to update homedir: '.mysql_error());
                
                ########################################################
                
                // Check if installed correctly (eventually this and the above command should be combined into 1 net cmd...but whatever for now)
                $check_install  = $this->runcmd($this_netid,$netarr,'CheckInstall',true);
                
                if($check_install == 'success')
                {
                    return 'success';
                }
                else
                {
                    // Delete net server since this failed
                    @mysql_query("DELETE FROM network WHERE id = '$this_netid'") or die('Failed to delete network server: '.mysql_error());
                    
                    return 'Remote Install Check: '.$check_install;
                }
            }
            // Failed, give output
            else
            {
                // Delete net server since this failed
                @mysql_query("DELETE FROM network WHERE id = '$this_netid'") or die('Failed to delete network server: '.mysql_error());
                
                return $net_homedir;
            }
        }
    }
}
