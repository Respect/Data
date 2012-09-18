Respect\Data [![Build Status](https://secure.travis-ci.org/Respect/Data.png)](http://travis-ci.org/Respect/Data)
================
 
Respect\Data allows you to use multiple, cooperative database mapping with a single solid API. You can 
even mix out MySQL and MongoDB databases in a single model.

**This project is a work in progress**

Installation
------------

Please use PEAR. More instructions on the [Respect PEAR channel](http://respect.li/pear)

Collections
-----------

The main component for Respect\Data are Collections. They define how data is grouped in your application.

In the example below, we're declaring two collections for dealing with a news portal:

```php
<?php
$articles = Collection::article();
$authors = Collection::article()->author();
```

Backends
--------

Currently, Respect\Data has two planned backend implementations: Respect\Relational for relational databases 
like MySQL and SQLite and Respect\Structural for MongoDB databases. These are different mappers that use 
the Respect\Data model.

Below is a sample of how to retrieve all authors from the author 5:

```php
<?php
$mapper->article->author[5]->fetchAll();
```

 * On the Relational backend, Respect would automatically build a query similar to 
   `SELECT * FROM article INNER JOIN author ON article.author_id = author.id WHERE author.id = 5`. 
 * On the Structural backend for MongoDB, the generated internal query would be something 
   like `db.article.find({"author.id":5}, {"author":1});`.

Features
--------

Besides fetching data from databases, Respect\Data is expected to deal with several other scenarios:

  * Persisting data into collections
  * Using backend-native extra commands in queries
  * Declaring shortcuts for large collection declarations
  * Handling composite mapper backends

License Information
===================

Copyright (c) 2009-2012, Alexandre Gomes Gaigalas.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice,
  this list of conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above copyright notice,
  this list of conditions and the following disclaimer in the documentation
  and/or other materials provided with the distribution.

* Neither the name of Alexandre Gomes Gaigalas nor the names of its
  contributors may be used to endorse or promote products derived from this
  software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

