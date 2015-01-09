# Respect\Data

[![Build Status](https://secure.travis-ci.org/Respect/Data.png)](http://travis-ci.org/Respect/Data) [![Latest Stable Version](https://poser.pugx.org/respect/data/v/stable.png)](https://packagist.org/packages/respect/data) [![Total Downloads](https://poser.pugx.org/respect/data/downloads.png)](https://packagist.org/packages/respect/data) [![Latest Unstable Version](https://poser.pugx.org/respect/data/v/unstable.png)](https://packagist.org/packages/respect/data) [![License](https://poser.pugx.org/respect/data/license.png)](https://packagist.org/packages/respect/data)

Respect\Data allows you to use multiple, cooperative database mapping with a single solid API. You can
even mix out MySQL and MongoDB databases in a single model.

**This project is a work in progress**

## Installation

The package is available on [Packagist](https://packagist.org/packages/arara/process).
You can install it using [Composer](http://getcomposer.org).

```bash
composer require respect/data
```

## Collections

The main component for Respect\Data are Collections. They define how data is grouped in your application.

In the example below, we're declaring two collections for dealing with a news portal:

```php
$articles = Collection::article();
$authors = Collection::article()->author();
```

## Backends

Currently, Respect\Data has two planned backend implementations: Respect\Relational for relational databases
like MySQL and SQLite and Respect\Structural for MongoDB databases. These are different mappers that use
the Respect\Data model.

Below is a sample of how to retrieve all authors from the author 5:

```php
$mapper->article->author[5]->fetchAll();
```

 * On the Relational backend, Respect would automatically build a query similar to
   `SELECT * FROM article INNER JOIN author ON article.author_id = author.id WHERE author.id = 5`.
 * On the Structural backend for MongoDB, the generated internal query would be something
   like `db.article.find({"author.id":5}, {"author":1});`.

## Features

Besides fetching data from databases, Respect\Data is expected to deal with several other scenarios:

  * Persisting data into collections
  * Using backend-native extra commands in queries
  * Declaring shortcuts for large collection declarations
  * Handling composite mapper backends

## License

See [LICENSE](LICENSE) file.
