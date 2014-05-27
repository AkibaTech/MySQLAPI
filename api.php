<?php

// DSN for MySQL (see PDO doc)
$dsn = 'mysql://[user]:[pass]@[host]:[port]/[db]/';
// IP whitelist
$clients = array();

if (strcmp(PHP_SAPI, 'cli') === 0)
{
	exit('MySQLAPI should not be run from CLI.' . PHP_EOL);
}

if ((empty($clients) !== true) && (in_array($_SERVER['REMOTE_ADDR'], (array) $clients) !== true))
{
	$result = array
	(
		'error' => array
		(
			'code' => 403,
			'status' => 'Forbidden',
		),
	);

	exit(MySQLAPI::Reply($result));
}
else if (MySQLAPI::Query($dsn) === false)
{
	$result = array
	(
		'error' => array
		(
			'code' => 503,
			'status' => 'Service Unavailable',
		),
	);

	exit(MySQLAPI::Reply($result));
}

if (array_key_exists('_method', $_GET) === true)
{
	$_SERVER['REQUEST_METHOD'] = strtoupper(trim($_GET['_method']));
}

MySQLAPI::Serve('GET', '/(#any)/(#any)/(#any)', function ($table, $id, $data)
{
	MySQLAPI::Protect($table);

	$query = array
	(
		sprintf('SELECT * FROM "%s"', $table),
		sprintf('WHERE "%s" %s ?', $id, (ctype_digit($data) === true) ? '=' : 'LIKE'),
	);

	if (isset($_GET['by']) === true)
	{
		if (isset($_GET['order']) !== true)
		{
			$_GET['order'] = 'ASC';
		}

		$query[] = sprintf('ORDER BY "%s" %s', $_GET['by'], $_GET['order']);
	}

	if (isset($_GET['limit']) === true)
	{
		$query[] = sprintf('LIMIT %u', $_GET['limit']);

		if (isset($_GET['offset']) === true)
		{
			$query[] = sprintf('OFFSET %u', $_GET['offset']);
		}
	}

	$query = sprintf('%s;', implode(' ', $query));
	$result = MySQLAPI::Query($query, $data);

	if ($result === false)
	{
		$result = array
		(
			'error' => array
			(
				'code' => 404,
				'status' => 'Not Found',
			),
		);
	}
	else if (empty($result) === true)
	{
		$result = array
		(
			'error' => array
			(
				'code' => 204,
				'status' => 'No Content',
			),
		);
	}

	return MySQLAPI::Reply($result);
});

MySQLAPI::Serve('GET', '/(#any)/(#num)?', function ($table, $id = null)
{
	MySQLAPI::Protect($table);

	$query = array
	(
		sprintf('SELECT * FROM "%s"', $table),
	);

	if (isset($id) === true)
	{
		$query[] = sprintf('WHERE "%s" = ? LIMIT 1', 'id');
	}
	else
	{
		if (isset($_GET['by']) === true)
		{
			if (isset($_GET['order']) !== true)
			{
				$_GET['order'] = 'ASC';
			}

			$query[] = sprintf('ORDER BY "%s" %s', $_GET['by'], $_GET['order']);
		}

		if (isset($_GET['limit']) === true)
		{
			$query[] = sprintf('LIMIT %u', $_GET['limit']);

			if (isset($_GET['offset']) === true)
			{
				$query[] = sprintf('OFFSET %u', $_GET['offset']);
			}
		}
	}

	$query = sprintf('%s;', implode(' ', $query));
	$result = (isset($id) === true) ? MySQLAPI::Query($query, $id) : MySQLAPI::Query($query);

	if ($result === false)
	{
		$result = array
		(
			'error' => array
			(
				'code' => 404,
				'status' => 'Not Found',
			),
		);
	}
	else if (empty($result) === true)
	{
		$result = array
		(
			'error' => array
			(
				'code' => 204,
				'status' => 'No Content',
			),
		);
	}

	else if (isset($id) === true)
	{
		$result = array_shift($result);
	}

	return MySQLAPI::Reply($result);
});

$result = array
(
	'error' => array
	(
		'code' => 400,
		'status' => 'Bad Request',
	),
);

exit(MySQLAPI::Reply($result));

class MySQLAPI
{
	// Authorized tables
	static $authorized_table = array();
	// Auto-removed columns
	static $removed_columns = array('pass_compte_cop');

	public static function Query($query = null)
	{
		static $db = null;
		static $result = array();

		try
		{
			if (isset($db, $query) === true)
			{
				if (strncasecmp($db->getAttribute(\PDO::ATTR_DRIVER_NAME), 'mysql', 5) === 0)
				{
					$query = strtr($query, '"', '`');
				}

				if (empty($result[$hash = crc32($query)]) === true)
				{
					$result[$hash] = $db->prepare($query);
				}

				$data = array_slice(func_get_args(), 1);

				if (count($data, COUNT_RECURSIVE) > count($data))
				{
					$data = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data)), false);
				}

				if ($result[$hash]->execute($data) === true)
				{
					$sequence = null;

					if ((strncmp($db->getAttribute(\PDO::ATTR_DRIVER_NAME), 'pgsql', 5) === 0) && (sscanf($query, 'INSERT INTO %s', $sequence) > 0))
					{
						$sequence = sprintf('%s_id_seq', trim($sequence, '"'));
					}

					switch (strstr($query, ' ', true))
					{
						case 'INSERT':
						case 'REPLACE':
							return $db->lastInsertId($sequence);

						case 'UPDATE':
						case 'DELETE':
							return $result[$hash]->rowCount();

						case 'SELECT':
						case 'EXPLAIN':
						case 'PRAGMA':
						case 'SHOW':
							return $result[$hash]->fetchAll();
					}

					return true;
				}

				return false;
			}

			else if (isset($query) === true)
			{
				$options = array
				(
					\PDO::ATTR_CASE => \PDO::CASE_NATURAL,
					\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
					\PDO::ATTR_EMULATE_PREPARES => false,
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
					\PDO::ATTR_ORACLE_NULLS => \PDO::NULL_NATURAL,
					\PDO::ATTR_STRINGIFY_FETCHES => false,
				);

				if (preg_match('~^(mysql)://(?:(.+?)(?::(.+?))?@)?([^/:@]++)(?::(\d++))?/(\w++)/?$~i', $query, $dsn) > 0)
				{
					if (strncasecmp($query, 'mysql', 5) === 0)
					{
						$options += array
						(
							\PDO::ATTR_AUTOCOMMIT => true,
							\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "utf8" COLLATE "utf8_general_ci", time_zone = "+00:00";',
							\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
						);
					}

					$db = new \PDO(sprintf('%s:host=%s;port=%s;dbname=%s', $dsn[1], $dsn[4], $dsn[5], $dsn[6]), $dsn[2], $dsn[3], $options);
				}
			}
		}

		catch (\Exception $exception)
		{
			return false;
		}

		return (isset($db) === true) ? $db : false;
	}

	public static function Reply($data)
	{
		$bitmask = 0;
		$options = array('UNESCAPED_SLASHES', 'UNESCAPED_UNICODE');

		if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) === true)
		{
			$options[] = 'PRETTY_PRINT';
		}

		foreach ($options as $option)
		{
			$bitmask |= (defined('JSON_' . $option) === true) ? constant('JSON_' . $option) : 0;
		}

		if (!empty(self::$removed_columns))
		{
			foreach ($data as $key => $value)
			{
				foreach (self::$removed_columns as $to_remove)
				{
					if (array_key_exists($to_remove, $value))
					{
						unset($data[$key][$to_remove]);
					}
				}
			}
		}

		if (($result = json_encode($data, $bitmask)) !== false)
		{
			$callback = null;

			if (array_key_exists('callback', $_GET) === true)
			{
				$callback = trim(preg_replace('~[^[:alnum:]\[\]_.]~', '', $_GET['callback']));

				if (empty($callback) !== true)
				{
					$result = sprintf('%s(%s);', $callback, $result);
				}
			}

			if (headers_sent() !== true)
			{
				header(sprintf('Content-Type: application/%s; charset=utf-8', (empty($callback) === true) ? 'json' : 'javascript'));
			}
		}

		return $result;
	}

	public static function Protect($table)
	{
		if (!empty(self::$authorized_table) AND in_array($table, self::$authorized_table) === false)
		{
			$result = array
			(
				'error' => array
				(
					'code' => 401,
					'status' => 'Unauthorized',
				),
			);

			exit(MySQLAPI::Reply($result));
		}
	}

	public static function Serve($on = null, $route = null, $callback = null)
	{
		static $root = null;

		if (isset($_SERVER['REQUEST_METHOD']) !== true)
		{
			$_SERVER['REQUEST_METHOD'] = 'CLI';
		}

		if ((empty($on) === true) || (strcasecmp($_SERVER['REQUEST_METHOD'], $on) === 0))
		{
			if (is_null($root) === true)
			{
				$root = preg_replace('~/++~', '/', substr($_SERVER['PHP_SELF'], strlen($_SERVER['SCRIPT_NAME'])) . '/');
			}

			if (preg_match('~^' . str_replace(array('#any', '#num'), array('[^/]++', '[0-9]++'), $route) . '~i', $root, $parts) > 0)
			{
				return (empty($callback) === true) ? true : exit(call_user_func_array($callback, array_slice($parts, 1)));
			}
		}

		return false;
	}
}
