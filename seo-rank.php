<?php

abstract class Search_Engine
{
	abstract public function get_serp_url($keyword, $page = 0);
	abstract public function get_serp_link_regex();
	abstract public function get_serp_referer_url();
}

class Google_Search extends Search_Engine
{
	public function get_serp_url($keyword, $page = 0)
	{
		return "https://www.google.com.au/search?hl=en&q={$keyword}&aqs=chrome.0.69i59j69i60l2j0l3.1394j0j7&sourceid=chrome&start={$page}0&ie=UTF-8";
	}

	public function get_serp_link_regex()
	{
		//return '/<h3\s+class="r"><a\s+(?:[^>]*?\s+)?href="\/url\?q=([^"]*)"/';
		return '/<h3\s+class="r"><a\s+(?:[^>]*?\s+)?href="([^"]*)"/';
	}

	public function get_serp_referer_url()
	{
		return 'https://www.google.com.au';
	}
}

class Yahoo_Search extends Search_Engine
{
	public function get_serp_url($keyword, $page = 0)
	{
		return "https://search.yahoo.com/search?ei=UTF-8&p={$keyword}&b={$page}1&xargs=0";
	}

	public function get_serp_link_regex()
	{
		return '/<a\s+(?:[^>]*?\s+)?class=" ac-algo ac-21th(?:\s+lh-15)?"\s+(?:data-sb="(?:[^"]*)"\s+)?href="([^"]*)"/';
	}

	public function get_serp_referer_url()
	{
		return 'https://www.yahoo.com/';
	}
}

class Bing_Search extends Search_Engine
{
	public function get_serp_url($keyword, $page = 0)
	{
		return "http://www.bing.com/search?q={$keyword}&go=Submit&qs=n&pq={$keyword}&first={$page}1";
	}

	public function get_serp_link_regex()
	{
		return '/<h2><a\s+(?:[^>]*?\s+)?href="([^"]*)"(?:\s+class="(?:[^"]*)")?(?:\s+id="(?:[^"]*)")?\s+h="ID=SERP,[\d]{4}.[\d]{1}(?:,Ads)?">/';
	}

	public function get_serp_referer_url()
	{
		return 'http://www.bing.com';
	}
}

?>
<?php

class SERP_Scraper
{
	private $_search_engine = null;
	private $_user_agents   = array();
	private $_proxies = array(
		//'user:password@173.234.11.134:54253',   // Some proxies require user, password, IP and port number
		//'user:password@173.234.120.69:54253',
		//'user:password@173.234.46.176:54253',
		//'190.116.28.26',                        // Some proxies only require IP
		//'202.159.42.246',
		//'186.249.7.205:80',
		//'64.20.48.83:8080',                     // Some proxies require IP and port number
		'115.127.32.170:8080',
		'117.135.250.133:8083',
		'112.5.220.199:83',
	);

	public function __construct($search_engine, $user_agents)
	{
		$this->_search_engine = new $search_engine();
		$this->_user_agents   = $user_agents;
	}

	public function get_serp_results($keyword, $page = 0)
	{
		$keyword = urlencode($keyword);
		$scraped = $this->scrape_serp($keyword, $page);

		if ($this->_search_engine instanceof Google_Search)
		{
			$scraped_output = __DIR__ . "/google-serp-page-$page.html";
			file_put_contents($scraped_output, $scraped);
		}

		$test_pattern = $this->_search_engine->get_serp_link_regex();
		preg_match_all($test_pattern, $scraped, $results);

		return $results[1];
	}

	protected function scrape_serp($keyword, $page = 0)
	{
		$serp_url = $this->_search_engine->get_serp_url($keyword, $page);
		$timeout  = $this->get_random_timeout();
		return $this->fetch($serp_url, $timeout);
	}

	protected function get_random_timeout()
	{
		return mt_rand(20, 60);
	}

	protected function fetch($url, $header = true, $timeout = 20)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,               $url);
		curl_setopt($ch, CURLOPT_HEADER,            $header);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,    true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION,    true);
		curl_setopt($ch, CURLOPT_COOKIESESSION,     true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,    false);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,    $timeout);
		curl_setopt($ch, CURLOPT_USERAGENT,         $this->get_random_user_agent());
		curl_setopt($ch, CURLOPT_REFERER,           $this->_search_engine->get_serp_referer_url());
		//curl_setopt($ch, CURLOPT_PROXY,           $this->get_random_proxy());
		//curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, true);

		$result['EXE'] = curl_exec($ch);
		$result['INF'] = curl_getinfo($ch);
		$result['ERR'] = curl_error($ch);

		curl_close($ch);
		return $result['EXE'];
	}

	protected function get_random_user_agent()
	{
		return $this->_user_agents[array_rand($this->_user_agents)];
	}

	protected function get_random_proxy()
	{
		return $this->_proxies[array_rand($this->_proxies)];
	}

	public function clean_serp_url($url)
	{
		$url = $this->strip_protocol($url);
		if ($this->_search_engine instanceof Google_Search)
		{
			return strtok($url, '&');
		}
		else
		{
			return $url;
		}
	}

	public static function strip_protocol($url)
	{
		$url = filter_var($url, FILTER_SANITIZE_URL);
		return preg_replace('(^http://|https://|/$)', '', $url);
	}

	public function is_url_from_domain($url, $domain)
	{
		$url    = filter_var($url,    FILTER_SANITIZE_URL);
		$domain = filter_var($domain, FILTER_SANITIZE_URL);
		return (strpos($url, $domain) !== false);
	}
}

?>

<?php

function init_serp_scrapers($search_engines, $user_agents)
{
	$serp_scrapers = array();
	foreach ($search_engines as $search_engine)
	{
		$search_engine .= '_Search';
		$serp_scrapers[$search_engine] = new SERP_Scraper($search_engine, $user_agents);
	}

	return $serp_scrapers;
}

function init_serp_positions($search_engines, $keywords)
{
	$serp_positions = array();
	foreach ($keywords as $keyword)
	{
		foreach ($search_engines as $search_engine)
		{
			$search_engine .= '_Search';
			$serp_positions[$keyword][$search_engine] = 0;
		}
	}

	return $serp_positions;
}

?>
<?php

$search_engines = array(
	'Google',
	'Yahoo',
	'Bing',
);

$keywords = array(
	// TOOO* add your keywords here -->
);

$user_agents = array(
	'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.94 Safari/537.36',
	'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36',
	'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:46.0) Gecko/20100101 Firefox/46.0',
	'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:48.0) Gecko/20100101 Firefox/48.0',
	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.10240',
	'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.94 Safari/537.36 OPR/37.0.2178.43',
	'Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko',
);

$serp_scrapers   = init_serp_scrapers($search_engines, $user_agents);
$serp_positions  = init_serp_positions($search_engines, $keywords);
$number_of_pages = 3;

set_time_limit(5 * 3600);
ini_set('implicit_flush', 1); 
ini_set('zlib.output_compression', 0);
ini_set('user_agent', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.94 Safari/537.36');

?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<meta name="robots" content="noindex,nofollow">
	<meta http-equiv="X-UA-Compatible" content="IE=Edge">

	<script src="https://code.jquery.com/jquery-1.12.0.min.js"></script>
	<script src="https://code.jquery.com/jquery-migrate-1.2.1.min.js"></script>

	<!-- Latest compiled and minified CSS -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">

	<!-- Optional theme -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap-theme.min.css" integrity="sha384-fLW2N01lMqjakBkx3l/M9EahuwpSfeNvV63J5ezn3uZzapT0u7EYsXMjQV+0En5r" crossorigin="anonymous">

	<!-- Latest compiled and minified JavaScript -->
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous"></script>

	<style>
		.vertical-middle
		{
			vertical-align: middle;
		}
	</style>
</head>
<body>
	<table class="table table-bordered table-striped table-condensed table-responsive">
		<thead>
			<tr>
				<th rowspan="3" class="text-center vertical-middle">Top Keywords</th>
				<th colspan="6" class="text-center">Position on Web Search Page</th>
			</tr>
			<tr>
				<?php
					foreach ($search_engines as $search_engine):
				?>
				<th colspan="2" class="text-center"><?php echo $search_engine; ?></th>
				<?php
					endforeach;
				?>
			</tr>
			<tr>
				<?php
					foreach ($search_engines as $search_engine):
				?>
				<th class="text-center">Previous Week</th>
				<th class="text-center">This Week</th>
				<?php
					endforeach;
				?>
			</tr>
		</thead>
		<tbody>
			<?php
				if (isset($_GET['domain'])):
					$domain = SERP_Scraper::strip_protocol($_GET['domain']);
					foreach ($keywords as $index => $keyword):
			?>
			<tr>
				<td><?php echo $keyword; ?></td>
					<?php
						foreach ($search_engines as $search_engine):
							$search_engine .= '_Search';
							$serp_scraper = $serp_scrapers[$search_engine];

							for ($page = 0; $page < $number_of_pages; $page++)
							{
								$serp_results = $serp_scraper->get_serp_results($keyword, $page);
								foreach ($serp_results as $index => $serp_result)
								{
									$serp_result = $serp_scraper->clean_serp_url($serp_result);
									if ($serp_scraper->is_url_from_domain($serp_result, $domain))
									{
										$serp_positions[$keyword][$search_engine] = ($page + 1) . ' (#' . ($index + 1) . ')';
										break;
									}
								}

								$delay = mt_rand(2, 5);
								sleep($delay);
							}

							$error_message = 'Not in top 100 search results';
					?>
				<td class="text-center">0</td>
				<td class="text-center"><?php echo $serp_positions[$keyword][$search_engine]; ?></td>
					<?php
						endforeach;

						while (@ob_end_flush());
						ob_implicit_flush(true);
					?>
			</tr>
			<?php
					endforeach;
				endif;
			?>
		</tbody>
	</table>
</body>
