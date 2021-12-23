OFX Parser
=================

[![Build Status](https://travis-ci.org/gitmathias/ofxparser.svg?branch=main)](https://travis-ci.org/gitmathias/ofxparser) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/gitmathias/ofxparser/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/gitmathias/ofxparser/?branch=main) [![Code Coverage](https://scrutinizer-ci.com/g/gitmathias/ofxparser/badges/coverage.png?b=main)](https://scrutinizer-ci.com/g/gitmathias/ofxparser/?branch=main) [![Latest Stable Version](https://poser.pugx.org/gitmathias/ofxparser/v/stable)](https://packagist.org/packages/gitmathias/ofxparser) [![License](https://poser.pugx.org/gitmathias/ofxparser/license)](https://packagist.org/packages/gitmathias/ofxparser)

OFX Parser is a PHP library designed to parse an OFX file downloaded from a financial institution into simple PHP objects.

It supports multiple Bank Accounts, the required "Sign On" response, and recognises OFX timestamps.

## Installation

Simply require the package using [Composer](https://getcomposer.org/):

```bash
$ composer require gitmathias/ofxparser
```

## Usage

You can access the nodes in your OFX file as follows:

```php
$ofxParser = new \OfxParser\Parser();
$ofx = $ofxParser->loadFromFile('/path/to/your/bankstatement.ofx');

$bankAccount = reset($ofx->bankAccounts);

// Get the statement start and end dates
$startDate = $bankAccount->statement->startDate;
$endDate = $bankAccount->statement->endDate;

// Get the statement transactions for the account
$transactions = $bankAccount->statement->transactions;
```

Most common nodes are support. If you come across an inaccessible node in your OFX file, please submit a pull request!

## Investments Support

Investments look much different than bank / credit card transactions. This version supports a subset of the nodes in the OFX 2.0.3 spec, per the immediate needs of the author(s). You may want to reference the OFX documentation if you choose to implement this library. In particular, this does not currently process investment positions (INVPOSLIST) or referenced security definitions (SECINFO).

This is not a pure pass-through of fields, such as this implementation in python: [csingley/ofxtools](https://github.com/csingley/ofxtools). This package contains fields that have been "translated" on occasion to make it more friendly to those less-familiar with the investments OFX spec.

To load investments from a Quicken (QFX) file or a MS Money (OFX / XML) file:

```php
// You'll probably want to alias the namespace:
use OfxParser\Entities\Investment as InvEntities;

// Load the OFX file
$ofxParser = new \OfxParser\Parsers\Investment();
$ofx = $ofxParser->loadFromFile('/path/to/your/investments_file.ofx');

// Loop over investment accounts (named bankAccounts from base lib)
foreach ($ofx->bankAccounts as $accountData) {
    // Loop over transactions
    foreach ($accountData->statement->transactions as $ofxEntity) {
        // Keep in mind... not all properties are inherited for all transaction types...

        // Maybe you'll want to do something based on the transaction properties:
        $nodeName = $ofxEntity->nodeName;
        if ($nodeName == 'BUYSTOCK') {
            // @see OfxParser\Entities\Investment\Transaction...

            $amount = abs($ofxEntity->total);
            $cusip = $ofxEntity->securityId;

            // ...
        }

        // Maybe you'll want to do something based on the entity:
        if ($ofxEntity instanceof InvEntities\Transaction\BuyStock) {
            // ...
        }

    }
}
```

## Fork & Credits

Fork of archived project [asgrim/ofxparser](https://github.com/asgrim/ofxparser).

Archive was originally forked from [grimfor/ofxparser](https://github.com/Grimfor/ofxparser) and made to be framework independent. Heavily refactored by [Oliver Lowe](https://github.com/loweoj) and loosely based on the ruby [ofx-parser by Andrew A. Smith](https://github.com/aasmith/ofx-parser).
