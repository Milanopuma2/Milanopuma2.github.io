<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $option = $_POST['option'];
    $url = $_POST['url'];
    $type = $_POST['category'];
    $manual = "";
    if ($_POST['ipsTextBox']){
      $manual = $_POST['ipsTextBox'];
    }
    
    header('Content-Type: application/json');
	  echo replaceIpAddresses($url , $option , $type , $manual);
}

function isBase64($string)
{
    return base64_decode($string, true) !== false;
}

function clean_ip()
{
    $url =
        "https://raw.githubusercontent.com/yebekhe/cf-clean-ip-resolver/main/list.json";
    $data = json_decode(file_get_contents($url), true);
    $clean_ip = [];

    foreach ($data["ipv4"] as $address) {
        $operator = $address["operator"];
        $ip = $address["ip"];

        if (!isset($clean_ip[$operator])) {
            $clean_ip[$operator] = [];
        }
        $clean_ip[$operator][] = $ip;
    }
    return $clean_ip;
}

function manualIP($ipList){
  $IPs = explode("\n", $ipList);
  return $IPs;
}

function replaceIpAddresses($url , $option , $type , $manual = "") {
  if ($type === 'sub'){
    if (isBase64(file_get_contents($url))) {
        $data = base64_decode(file_get_contents($url));
    } else {
        $data = file_get_contents($url);
    }

    $lines = explode("\n", $data);

    $config_type = "";
	if ($manual === ""){
    $clean_ips = clean_ip()[$option];
  }
	else{
    $clean_ips = manualIP($manual);
  }
	$config_array = [];
	
    foreach ($lines as $config) {
        if (strpos($config, "vmess://") === 0) {
            $config_data = decode_vmess($config);
            $config_name = $config_data["ps"];
			foreach ($clean_ips as $clean_ip){
				$config_data["add"] = $clean_ip;
                $config_data["ps"] =  $config_name . "-" . $clean_ip;
                $config_array[] = encode_vmess($config_data);
			}
        } elseif (strpos($config, "ss://") === 0) {
            $config_data = ParseShadowsocks($config);
            $config_name = $config_data['name'];
			foreach ($clean_ips as $clean_ip){
				$config_data["server_address"] = $clean_ip;
                $config_data["name"] = $config_name . "-" . $clean_ip;
                $config_array[] = BuildShadowsocks($config_data);
			}
        } elseif (strpos($config, "trojan://") === 0) {
            $config_data = parseProxyUrl($config);
            $config_name = $config_data['hash'];
			foreach ($clean_ips as $clean_ip){
				$config_data["hostname"] = $clean_ip;
                $config_data["hash"] = $config_name . "-" . $clean_ip;
                $config_array[] = buildProxyUrl($config_data);
			}
        } elseif (strpos($config, "vless://") === 0) {
            $config_type = "vless";
            $config_data = parseProxyUrl($config, $config_type);
            $config_name = $config_data['hash'];
			foreach ($clean_ips as $clean_ip){
				$config_data["hostname"] = $clean_ip;
                $config_data["hash"] = $config_name . "-" . $clean_ip;
                $config_array[] = buildProxyUrl($config_data, "vless");
			}
        }
    }
    $config_string = "";
    foreach ($config_array as $config){
        if ($config_string === ""){
            $config_string .= $config;
        } else {
            $config_string .= "\n" . $config ;
        }
        
    }
  }
  elseif ($type === "single")
  {
    $config = $url ;
    if ($manual === ""){
       $clean_ips = clean_ip()[$option];
    }
   	else{
        $clean_ips = manualIP($manual);
    }
	  $config_array = [];
    
    if (strpos($config, "vmess://") === 0) {
            $config_data = decode_vmess($config);
            $config_name = $config_data["ps"];
			foreach ($clean_ips as $clean_ip){
				$config_data["add"] = $clean_ip;
                $config_data["ps"] =  $config_name . "-" . $clean_ip;
                $config_array[] = encode_vmess($config_data);
			}
        } elseif (strpos($config, "ss://") === 0) {
            $config_data = ParseShadowsocks($config);
            $config_name = $config_data['name'];
			foreach ($clean_ips as $clean_ip){
				        $config_data["server_address"] = $clean_ip;
                $config_data["name"] = $config_name . "-" . $clean_ip;
                $config_array[] = BuildShadowsocks($config_data);
			}
        } elseif (strpos($config, "trojan://") === 0) {
            $config_data = parseProxyUrl($config);
            $config_name = $config_data['hash'];
			foreach ($clean_ips as $clean_ip){
				$config_data["hostname"] = $clean_ip;
                $config_data["hash"] = $config_name . "-" . $clean_ip;
                $config_array[] = buildProxyUrl($config_data);
			}
        } elseif (strpos($config, "vless://") === 0) {
            $config_type = "vless";
            $config_data = parseProxyUrl($config, $config_type);
            $config_name = $config_data['hash'];
			foreach ($clean_ips as $clean_ip){
				$config_data["hostname"] = $clean_ip;
                $config_data["hash"] = $config_name . "-" . $clean_ip;
                $config_array[] = buildProxyUrl($config_data, $config_type);
			}
        }
    $config_string = "";
    foreach ($config_array as $config){
        if ($config_string === ""){
            $config_string .= $config;
        } else {
            $config_string .= "\n" . $config ;
        }
        
    }
  }
    return $config_string;
}

function parseProxyUrl($url, $type = "trojan") {
  // Parse the URL into components
  $parsedUrl = parse_url($url);

  // Extract the parameters from the query string
  $params = array();
  if (isset($parsedUrl['query'])) {
    parse_str($parsedUrl['query'], $params);
  }

  // Construct the output object
  $output = array(
    'protocol' => $type,
    'username' => isset($parsedUrl['user']) ? $parsedUrl['user'] : '',
    'hostname' => isset($parsedUrl['host']) ? $parsedUrl['host'] : '',
    'port' => isset($parsedUrl['port']) ? $parsedUrl['port'] : ($type === "trojan" ? 80 : 443),
    'params' => $params,
    'hash' => isset($parsedUrl['fragment']) ? $parsedUrl['fragment'] : ''
  );

  return $output;
}

function buildProxyUrl($obj, $type = "trojan") {
  // Construct the base URL
  $url = $type . "://";
  if ($obj['username'] !== '') {
    $url .= $obj['username'];
    if (isset($obj['pass']) && $obj['pass'] !== '') {
      $url .= ":" . $obj['pass'];
    }
    $url .= "@";
  }
  $url .= $obj['hostname'];
  if (isset($obj['port']) && $obj['port'] !== '') {
    $url .= ":" . $obj['port'];
  }

  // Add the query parameters
  if (!empty($obj['params'])) {
    $url .= "?" . http_build_query($obj['params']);
  }

  // Add the fragment identifier
  if (isset($obj['hash']) && $obj['hash'] !== '') {
    $url .= "#" . $obj['hash'];
  }

  return $url;
}

function decode_vmess($vmess_config) {
    $vmess_data = substr($vmess_config, 8); // remove "vmess://"
    $decoded_data = json_decode(base64_decode($vmess_data) , true);
    return $decoded_data;
}

function encode_vmess($config) {
    $encoded_data = base64_encode(json_encode($config));
    $vmess_config = "vmess://" . $encoded_data;
    return $vmess_config;
}

function ParseShadowsocks($config_str) {
  // Parse the config string as a URL
  $url = parse_url($config_str);

  // Extract the encryption method and password from the user info
  list($encryption_method, $password) = explode(':', base64_decode($url['user']));

  // Extract the server address and port from the host and path
  $server_address = $url['host'];
  $server_port = $url['port'];

  // Extract the name from the fragment (if present)
  $name = isset($url['fragment']) ? urldecode($url['fragment']) : null;

  // Create an array to hold the server configuration
  $server = array(
    'encryption_method' => $encryption_method,
    'password' => $password,
    'server_address' => $server_address,
    'server_port' => $server_port,
    'name' => $name,
  );

  // Return the server configuration as a JSON string
  return $server;
}

function BuildShadowsocks($server) {

  // Encode the encryption method and password as a Base64-encoded string
  $user = base64_encode($server['encryption_method'] . ':' . $server['password']);

  // Construct the URL from the server address, port, and user info
  $url = "ss://$user@{$server['server_address']}:{$server['server_port']}";

  // If the name is present, add it as a fragment to the URL
  if (!empty($server['name'])) {
    $url .= '#' . urlencode($server['name']);
  }

  // Return the URL as a string
  return $url;
}
?>
