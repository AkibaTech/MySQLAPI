#MySQLAPI

MySQLAPI is a "plug-n-play" RESTful API for MySQL databases.

MySQLAPI provides a REST API that maps directly to your database stucture with no configuation.

Lets suppose you have set up MySQLAPI at `http://api.example.com/` and a table named `customers`.

To get a list of customers you would simply need to do:

	GET http://api.example.com/customers/

Where `customers` is the table name. As a response you would get a JSON formatted list of customers.

Or, if you only want to get one customer, then you would append the customer `id` to the URL:

	GET http://api.example.com/customers/123/

##Requirements

- PHP 5.3+ & PDO
- MySQL

##Installation

Edit `index.php` and change the `$dsn` variable located at the top, here are some examples:

- MySQL: `$dsn = 'mysql://[user[:pass]@]host[:port]/db/';`

If you want to restrict access to allow only specific IP addresses, add them to the `$clients` array:

```php
$clients = array
(
	'127.0.0.1',
	'127.0.0.2',
	'127.0.0.3',
);
```

After you're done editing the file, place it in a public directory (feel free to change the filename).

If you're using Apache, you can use the following `mod_rewrite` rules in a `.htaccess` file:

```apache
<IfModule mod_rewrite.c>
	RewriteEngine	On
	RewriteCond		%{REQUEST_FILENAME}	!-d
	RewriteCond		%{REQUEST_FILENAME}	!-f
	RewriteRule		^(.*)$ index.php/$1	[L,QSA]
</IfModule>
```

***Nota bene:*** You must access the file directly, including it from another file won't work.

##API Design

The actual API design is very straightforward and follows the design patterns of the majority of APIs.

	(R)ead   > GET    /table[/id]
	(R)ead   > GET    /table[/column/content]

To put this into practice below are some example of how you would use the ArrestDB API:

	# Get all rows from the "customers" table
	GET http://api.example.com/customers/

	# Get a single row from the "customers" table (where "123" is the ID)
	GET http://api.example.com/customers/123/

	# Get all rows from the "customers" table where the "country" field matches "Australia" (`LIKE`)
	GET http://api.example.com/customers/country/Australia/

	# Get 50 rows from the "customers" table
	GET http://api.example.com/customers/?limit=50

	# Get 50 rows from the "customers" table ordered by the "date" field
	GET http://api.example.com/customers/?limit=50&by=date&order=desc

Please note that `GET` calls accept the following query string variables:

- `by` (column to order by)
  - `order` (order direction: `ASC` or `DESC`)
- `limit` (`LIMIT x` SQL clause)
  - `offset` (`OFFSET x` SQL clause)

Alternatively, you can also override the HTTP method by using the `_method` query string parameter.

##Responses

All responses are in the JSON format. A `GET` response from the `customers` table might look like this:

```json
[
    {
        "id": "114",
        "customerName": "Australian Collectors, Co.",
        "contactLastName": "Ferguson",
        "contactFirstName": "Peter",
        "phone": "123456",
        "addressLine1": "636 St Kilda Road",
        "addressLine2": "Level 3",
        "city": "Melbourne",
        "state": "Victoria",
        "postalCode": "3004",
        "country": "Australia",
        "salesRepEmployeeNumber": "1611",
        "creditLimit": "117300"
    },
    ...
]
```

Errors are expressed in the format:

```json
{
    "error": {
        "code": 400,
        "status": "Bad Request"
    }
}
```

Ajax-like requests will be minified, whereas normal browser requests will be human-readable.

##Credits

MySQLAPI is a lighter version of [ArrestDB](https://github.com/alixaxel/ArrestDB) with some additionnal features like a configuration for restrict table and columns.

##License (MIT)

All right are for Alix Axel from ArrestDB (under MIT Licence).